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
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

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
     * @var EventDispatcherInterface
     */
    protected $event;

    /**
     * @var WorkerForks[]
     */
    protected $workerForks;

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
     * @param ProcessManager           $manager
     * @param Logger                   $logger
     * @param EventDispatcherInterface $event
     */
    public function __construct(ProcessManager $manager, Logger $logger, EventDispatcherInterface $event)
    {
        $this->manager         = $manager;
        $this->logger          = $logger;
        $this->event           = $event;
        $this->shutdownSignal  = SIGQUIT;
        $this->shutdownTimeout = 5;
        $this->workerForks     = array();
    }

    /**
     * Executes daemon by starting all childs processes
     */
    public function addWorker(Worker $worker)
    {
        $name = $worker->getName();
        if (isset($this->workerForks[$name])) {
            throw new \InvalidArgumentException("Worker with name {$name} already registered");
        }
        $this->event->dispatch('worker.pre-add', new DaemonEvent($worker));
        $worker->setDaemon($this);
        $this->workerForks[$name] = new WorkerForks($worker);
        $this->event->dispatch('worker.post-add', new DaemonEvent($worker));
    }

    /**
     * Executes daemon by starting all childs processes
     *
     * @param int|bool $iterations If true, run infinite
     */
    public function loop($iterations = true)
    {
        $this->bindParentSignals();
        $this->logger->debug("Starting loop");

        while (true) {
            foreach ($this->getWorkerForks() as $workerFork) {
                $worker = $workerFork->getWorker();
                $this->logger->debug("Run instances of {$worker->getName()}");
                $this->event->dispatch('worker.pre-start', new DaemonEvent($worker));
                $this->runWorkerInstances($workerFork);
                $this->event->dispatch('worker.post-start', new DaemonEvent($worker));
            }
            if ($iterations !== true && --$iterations === 0) {
                break;
            }

            $interval = 20;
            for ($i = 0; $i < $interval; $i++) {
                if ($this->stopped) {
                    $this->event->dispatch('daemon.stopping', new DaemonEvent($this));
                    break 2;
                }
                BuiltIn::doUsleep(100000);
            }
        }
    }

    /**
     * Set stop (does not kill anything)
     */
    public function stop()
    {
        $this->stopped = true;
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
                    $this->event->dispatch('daemon.pre-shutdown', new DaemonEvent($this));
                    $this->shutdownWorkers();
                    $this->event->dispatch('daemon.post-shutdown', new DaemonEvent($this));
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
                $this->event->dispatch('daemon.child-pre-exit', new DaemonEvent($this));
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
     * @return WorkerForks[]
     */
    protected function getWorkerForks()
    {
        return array_values($this->workerForks);
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
     * @param WorkerForks $workerFork
     */
    protected function runWorkerInstances(WorkerForks $workerFork)
    {
        // start now required amount
        $workerFork->clearStopped();
        $amount = $workerFork->countMissing();
        for ($i = 0; $i < $amount; $i++) {
            $this->logger->info("Starting new instance of worker {$workerFork->getName()}");
            $this->event->dispatch('daemon.pre-fork', new DaemonEvent($workerFork));
            $this->makeNewFork($workerFork);
            $this->event->dispatch('daemon.post-fork', new DaemonEvent($workerFork));
        }
    }

    /**
     * Runs a worker in a fork
     *
     * @param WorkerForks $workerForks
     */
    protected function makeNewFork(WorkerForks $workerForks)
    {
        $this->logger->debug("Forking for {$workerForks->getName()}");
        $self = & $this;
        $fork = $this->manager->fork(function () use ($workerForks, $self) {
            $self->bindWorkerSignals();
            $worker = $workerForks->getWorker();
            if ($worker->hasStartup()) {
                $worker->runStartup();
            }

            $interval = ($worker->getInterval() ? : 1) * 10;
            while (true) {
                $worker->runLoop();
                for ($i = 0; $i < $interval; $i++) {
                    BuiltIn::doUsleep(100000);
                    if ($this->stopped) {
                        break 2;
                    }
                }
            }
        });
        $workerForks->addFork($fork);
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
        foreach ($this->getWorkerForks() as $workerForks) {
            $workerForks->clearStopped();
            $this->logger->debug("Sending shutdown to {$workerForks->getName()}");
            $workerForks->shutdownAll($this->shutdownSignal);
            $this->event->dispatch('worker.shutdown', new DaemonEvent($workerForks->getWorker()));
        }

        // wait for shutdown
        for ($try = $this->shutdownTimeout; $try > 0; $try--) {
            BuiltIn::doUsleep(1000000);
            $resisting = 0;
            foreach ($this->getWorkerForks() as $workerForks) {
                $workerForks->clearStopped();
                if (($running = $workerForks->countRunning()) > 0) {
                    $resisting += $running;
                    $this->logger->info("Found $running resisting processes of {$workerForks->getName()} - $try seconds until slaughter");
                }
            }

            if (!$resisting) {
                break;
            }

        }

        // slaughter survivors
        foreach ($this->getWorkerForks() as $workerForks) {
            $workerForks->clearStopped();
            if ($workerForks->countRunning() > 0) {
                $this->logger->alert("Need to slaughter {$workerForks->countRunning()} of {$workerForks->getName()}");
                $workerForks->shutdownAll(SIGKILL);
            }
            $this->event->dispatch('worker.killed', new DaemonEvent($workerForks->getWorker()));
        }

    }


}
