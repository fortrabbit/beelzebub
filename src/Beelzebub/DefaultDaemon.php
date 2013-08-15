<?php


/*
 * This file is part of Fortrabbit\Beelzebub.
 *
 * (c) Ulrich Kautz <uk@fortrabbit.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Beelzebub;

use Beelzebub\Daemon;
use Beelzebub\Wrapper\Builtin;
use Beelzebub\Wrapper\Posix;
use Monolog\Logger;
use Spork\Fork;
use Spork\ProcessManager;

declare(ticks = 1);

/**
 * Base class for daemon
 *
 * @author Ulrich Kautz <uk@fortrabbit.com>
 */

class DefaultDaemon implements Daemon
{

    /**
     * @var ProcessManager
     */
    protected $manager;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var Worker[]
     */
    protected $workers;

    /**
     * @var bool
     */
    protected $stopped;

    /**
     * @var int
     */
    protected $restartSignal;

    /**
     * @var int
     */
    protected $shutdownSignal;

    /**
     * @var int
     */
    protected $shutdownTimeout;

    /**
     * @var Worker
     */
    protected $currentWorker;

    /**
     * Create new daemon instance
     *
     * @param ProcessManager $manager
     * @param Logger         $logger
     */
    public function __construct(ProcessManager $manager, Logger $logger)
    {
        $this->manager         = $manager;
        $this->logger          = $logger;
        $this->shutdownSignal  = SIGQUIT;
        $this->shutdownTimeout = 30;
        $this->workers         = array();
    }

    /**
     * Executes daemon by starting all childs processes
     */
    public function addWorker(Worker $worker)
    {
        $name = $worker->getName();
        if (isset($this->workers[$name])) {
            throw new \InvalidArgumentException("Worker with name {$name} already registered");
        }
        $this->workers[$name] = $worker;
    }

    /**
     * Executes daemon by starting all childs processes
     *
     * @param int|bool $iterations If true, run infinite
     */
    public function loop($iterations = true)
    {
        $this->bindParentSignals();

        while (true) {
            foreach ($this->getWorkers() as $worker) {
                $this->runWorkerInstances($worker);
            }
            if ($iterations !== true && --$iterations === 0) {
                break;
            }

            $interval = 20;
            for ($i = 0; $i < $interval; $i++) {
                if ($this->stopped) {
                    break 2;
                }
                BuiltIn::doUsleep(100000);
            }
        }
    }

    /**
     * Called when signal is received in PARENT
     *
     * @param int $signo
     */
    public function handleParentShutdownSignal($signo)
    {
        $this->logger->debug("Received SIG $signo in parent");
        switch ($signo) {
            case SIGTERM:
            case SIGQUIT:
            case SIGINT:
                if (!$this->stopped) {
                    $this->logger->info("Shutting down parent, sending shutdown to all workers");
                    $this->stopped = true;
                    $this->shutdownWorkers();
                    Builtin::doExit();
                }
                break;
        }
    }

    /**
     * Called when signal is received in PARENT
     *
     * @param int $signo
     */
    public function handleWorkerShutdownSignal($signo)
    {
        $this->logger->debug("Received SIG $signo in worker");
        switch ($signo) {
            case SIGTERM:
            case SIGQUIT:
            case SIGINT:
                Builtin::doExit();
                break;
        }
    }

    /* Setters & Getters */

    /**
     * @param int $signo
     */
    public function setRestartSignal($signo)
    {
        $this->restartSignal = $signo;
    }

    /**
     * @return int
     */
    public function getRestartSignal()
    {
        return $this->restartSignal;
    }


    /* Internals */

    /**
     * Returns all registered workers
     *
     * @return Worker[]
     */
    protected function getWorkers()
    {
        return array_values($this->workers);
    }

    /**
     * Returns bool whether pid is runnign or not
     *
     * @param $pid
     *
     * @return bool
     */
    protected function isPidRunning($pid)
    {
        return Posix::kill($pid, 0);
    }

    /**
     * Runs all worker instances
     *
     * @param Worker $worker
     */
    protected function runWorkerInstances(Worker $worker)
    {
        $this->cleanupStoppedWorkerInstances($worker);

        // start now required amount
        $amount = $worker->getAmount() - $worker->countRunning();
        for ($i = 0; $i < $amount; $i++) {
            $this->logger->info("Starting new instance of worker {$worker->getName()}");
            $this->runWorkerInFork($worker);
        }
    }

    /**
     * Removes all stopped workers from pid list
     *
     * @param Worker $worker
     */
    protected function cleanupStoppedWorkerInstances(Worker $worker)
    {
        foreach ($worker->getPids() as $pid) {
            if (!$this->isPidRunning($pid)) {
                $worker->removePid($pid);
            }
        }
    }

    /**
     * Sends shutdown to all instances of a named worker
     *
     * @param Worker $worker
     */
    protected function shutdownWorkerInstances(Worker $worker)
    {
        foreach ($worker->getPids() as $pid) {
            Posix::kill($pid, $this->shutdownSignal);
        }
    }

    /**
     * Runs a worker in a fork
     *
     * @param Worker $worker
     */
    protected function runWorkerInFork(Worker $worker)
    {
        $self = & $this;
        $this->manager
            ->fork(function () use ($worker, $self) {
                $self->bindWorkerSignals();
                if ($worker->hasStartup()) {
                    $worker->runStartup();
                }

                while (true) {
                    $worker->runLoop();

                    $interval = $worker->getInterval() ? : 1;
                    for ($i = 0; $i < $interval * 10; $i++) {
                        if ($this->stopped) {
                            break;
                        }
                        BuiltIn::doUsleep(100000);
                    }
                }
            })
            ->then(function (Fork $fork) use ($worker) {
                $worker->addPid($fork->getPid());
            });
    }

    /**
     * Binds signals for parent to handlers
     */
    protected function bindParentSignals()
    {
        pcntl_signal(SIGTERM, array($this, 'handleParentShutdownSignal'));
        pcntl_signal(SIGQUIT, array($this, 'handleParentShutdownSignal'));
        pcntl_signal(SIGINT, array($this, 'handleParentShutdownSignal'));
        pcntl_signal(SIGCHLD, SIG_IGN);
    }

    /**
     * Binds signals for child (worker) to handlers
     */
    protected function bindWorkerSignals()
    {
        pcntl_signal(SIGTERM, array($this, 'handleWorkerShutdownSignal'));
        pcntl_signal(SIGQUIT, array($this, 'handleWorkerShutdownSignal'));
        pcntl_signal(SIGINT, array($this, 'handleWorkerShutdownSignal'));
        pcntl_signal(SIGCHLD, array($this, 'handleWorkerShutdownSignal'));
    }

    protected function shutdownWorkers()
    {
        $this->logger->info("Shutting down all workers");

        // send initial signal..
        foreach ($this->getWorkers() as $worker) {
            $this->cleanupStoppedWorkerInstances($worker);
            $this->shutdownWorkerInstances($worker);
        }

        // wait for shutdown
        for ($try = $this->shutdownTimeout; $try > 0; $try--) {
            BuiltIn::doSleep(1);
            $resisting = 0;
            foreach ($this->getWorkers() as $worker) {
                $this->cleanupStoppedWorkerInstances($worker);
                if (($running = $worker->countRunning()) > 0) {
                    #error_log("STILL RUNNING OF {$worker->getName()}: $running");
                    $resisting += $running;
                    $this->logger->info("Found $running resisting processes of {$worker->getName()} - $try seconds until slaughter");
                }
            }

            if (!$resisting) {
                break;
            }

        }

        // slaughter survivors
        foreach ($this->getWorkers() as $worker) {
            $this->cleanupStoppedWorkerInstances($worker);
            $this->logger->alert("Need to slaughter {$worker->countRunning()} of {$worker->getName()}");
            foreach ($worker->getPids() as $pid) {
                Posix::kill($pid, SIGKILL);
            }
        }

    }


}
