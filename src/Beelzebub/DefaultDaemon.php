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
use Beelzebub\Wrapper\Pcntl;
use Beelzebub\Wrapper\Posix;
use Monolog\Logger;
use Spork\Exception\ProcessControlException;
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
     * @var Worker[]
     */
    protected $workers;

    /**
     * @var Fork[]
     */
    protected $forks;

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
        $this->workers         = array();
        $this->forks           = array();
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
        $worker->setDaemon($this);
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
        $this->logger->debug("Starting loop");

        while (true) {
            foreach ($this->workers as $worker) {
                $this->logger->debug("Run instances of {$worker->getName()}");
                $this->event->dispatch('worker.pre-start', new DaemonEvent($worker));
                $this->assureWorkerForkRunning($worker);
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
                    $this->shutdownWorkersFromParent();
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
     * Makes sure worker instance is running
     *
     * @param Worker $worker
     */
    protected function assureWorkerForkRunning(Worker $worker)
    {
        // start now required amount
        $name = $worker->getName();
        if (!isset($this->forks[$name]) || $this->forks[$name]->isExited()) {
            $self               = & $this;
            $this->forks[$name] = $this->manager
                ->fork(function () use ($worker, $self) {
                    $self->bindWorkerSignals();
                    if ($worker->hasStartup()) {
                        $worker->runStartup();
                    }

                    $intervals = $worker->getInterval() * 10;
                    while (true) {
                        $worker->runLoop();

                        for ($i = 0; $i < $intervals; $i++) {
                            Builtin::doUsleep(100000);
                            if ($self->stopped) {
                                break 2;
                            }
                        }
                    }
                })
                ->then(function () use ($name, $self) {
                    unset($self->forks[$name]);
                });
        }
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

    /**
     * Sends shutdown to child instances, which run the worker(s)
     */
    protected function shutdownWorkersFromParent()
    {
        $this->logger->info("Shutting down all workers");

        // send initial signal..
        foreach ($this->workers as $worker) {
            $name = $worker->getName();
            if (isset($this->forks[$name]) && !$this->forks[$name]->isExited()) {
                $this->event->dispatch('worker.shutdown', new DaemonEvent($worker));
                $this->forks[$name]->kill($this->shutdownSignal);
            }
        }

        // wait for shutdown
        $tries = $this->shutdownTimeout;
        for ($try = $tries; $try > 0; --$try) {
            BuiltIn::doUsleep(1000000);
            $resisting = 0;

            foreach ($this->workers as $worker) {
                $name = $worker->getName();
                if (isset($this->forks[$name])) {
                    $fork = $this->forks[$name];
                    if (!$fork->isExited() && $this->reallyRunning($fork)) {
                        $resisting++;
                    } else {
                        unset($this->forks[$name]);
                    }
                }
            }

            if (!$resisting) {
                break;
            } else {
                $this->logger->info("$resisting resisting worker(s) still remaining - $try seconds till slaughter");
            }

        }

        // slaughter survivors
        foreach ($this->forks as $fork) {
            if (!$fork->isExited() && $this->reallyRunning($fork)) {
                $fork->kill(SIGKILL);
                try {
                    $fork->wait(true);
                } catch (ProcessControlException $e) {
                    // well, it's a SIG KILL..
                }
            }
        }
        $this->manager->zombieOkay(true);
    }


    /**
     * Check whether process is really running 'cause Fork's isExited doesn't play so good..
     *
     * @param Fork $fork
     *
     * @return bool
     */
    protected function reallyRunning(Fork $fork)
    {
        return Posix::kill($fork->getPid(), 0);
    }


}
