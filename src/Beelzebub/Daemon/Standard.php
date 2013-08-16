<?php


/*
 * This file is part of Fortrabbit\Beelzebub.
 *
 * (c) Ulrich Kautz <uk@fortrabbit.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Beelzebub\Daemon;

use Beelzebub\Daemon;
use Beelzebub\Worker;
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

class Standard implements Daemon
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
     * @var array
     */
    protected $forks;

    /**
     * @var bool
     */
    protected $stopped;

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
     * {@inheritdoc}
     */
    public function __construct(ProcessManager $manager, Logger $logger, EventDispatcherInterface $event)
    {
        $this->manager         = $manager;
        $this->logger          = $logger;
        $this->event           = $event;
        $this->shutdownSignal  = Daemon::DEFAULT_SHUTDOWN_SIGNAL;
        $this->shutdownTimeout = Daemon::DEFAULT_SHUTDOWN_TIMEOUT;
        $this->workers         = array();
        $this->forks           = array();
    }

    /**
     * {@inheritdoc}
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
     * {@inheritdoc}
     */
    public function run($iterations = true)
    {
        $this->bindParentSignals();
        $this->logger->debug("Starting loop");

        while (true) {
            foreach ($this->workers as $worker) {
                $this->logger->debug("Run instances of {$worker->getName()}");
                $this->event->dispatch('worker.pre-start', new Event($worker));
                $this->assureWorkerForkRunning($worker);
                $this->event->dispatch('worker.post-start', new Event($worker));
            }
            if ($iterations !== true && --$iterations === 0) {
                break;
            }

            $interval = 20;
            for ($i = 0; $i < $interval; $i++) {
                BuiltIn::doUsleep(100000);
                if ($this->stopped) {
                    $this->event->dispatch('daemon.stopping', new Event($this));
                    break 2;
                }
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
                    $this->event->dispatch('daemon.pre-shutdown', new Event($this));
                    $this->shutdownWorkersFromParent();
                    $this->event->dispatch('daemon.post-shutdown', new Event($this));
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
                $this->event->dispatch('daemon.child-pre-exit', new Event($this));
                Builtin::doExit();
                break;
        }
    }

    /* Setters & Getters */

    /**
     * {@inheritdoc}
     */
    public function setShutdownSignal($signo)
    {
        $this->shutdownSignal = $signo;
    }

    /**
     * {@inheritdoc}
     */
    public function setShutdownTimeout($seconds)
    {
        $this->shutdownTimeout = $seconds;
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
        $self = & $this;
        $name = $worker->getName();
        $diff = $worker->getAmount() - $this->countRunning($name);
        if (!isset($this->forks[$name])) {
            $this->forks[$name] = array();
        }
        for ($i = 0; $i < $diff; $i++) {
            $fork                                = $this->manager
                ->fork(function () use ($worker, $self) {
                    $self->bindWorkerSignals();
                    if ($worker->hasStartup()) {
                        $worker->runStartup();
                    }

                    $intervals = $worker->getInterval() * 10;
                    while (true) {
                        $worker->run();

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
            $this->forks[$name][$fork->getPid()] = $fork;
        }
    }

    /**
     * Binds signals for parent to handlers
     */
    protected function bindParentSignals()
    {
        Pcntl::signal(SIGTERM, array($this, 'handleParentShutdownSignal'));
        Pcntl::signal(SIGQUIT, array($this, 'handleParentShutdownSignal'));
        Pcntl::signal(SIGINT, array($this, 'handleParentShutdownSignal'));
        Pcntl::signal(SIGCHLD, SIG_IGN);
    }

    /**
     * Binds signals for child (worker) to handlers
     */
    protected function bindWorkerSignals()
    {
        Pcntl::signal(SIGTERM, array($this, 'handleWorkerShutdownSignal'));
        Pcntl::signal(SIGQUIT, array($this, 'handleWorkerShutdownSignal'));
        Pcntl::signal(SIGINT, array($this, 'handleWorkerShutdownSignal'));
        Pcntl::signal(SIGCHLD, array($this, 'handleWorkerShutdownSignal'));
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
            if ($this->countRunning($name)) {
                $this->event->dispatch('worker.shutdown', new Event($worker));
                /** @var Fork $fork * */
                foreach ($this->forks[$name] as $fork) {
                    $fork->kill($this->shutdownSignal);
                }
            }
        }

        // wait for shutdown
        $tries = $this->shutdownTimeout;
        for ($try = $tries; $try > 0; --$try) {
            BuiltIn::doUsleep(1000000);
            $resisting = 0;

            foreach ($this->workers as $worker) {
                $name = $worker->getName();
                $resisting += $this->countRunning($name);
            }

            if (!$resisting) {
                break;
            } else {
                $this->logger->info("$resisting resisting worker(s) still remaining - $try seconds till slaughter");
            }

        }

        // slaughter survivors
        /** @var Fork[] $forks */
        foreach ($this->forks as $forks) {
            foreach ($forks as $fork) {
                if ($this->reallyRunning($fork)) {
                    $fork->kill(SIGKILL);
                    try {
                        $fork->wait(true);
                    } catch (ProcessControlException $e) {
                        // well, it's a SIG KILL..
                    }
                }
            }
        }
        $this->manager->zombieOkay(true);
    }


    /**
     * Returns running instances by worker name
     *
     * @param string $name
     *
     * @return int
     */
    protected function countRunning($name)
    {
        if (!isset($this->forks[$name])) {
            return 0;
        }

        /** @var Fork[] $oldForks */
        $newForks = array();
        $oldForks = $this->forks[$name];
        foreach ($oldForks as $fork) {
            if ($this->reallyRunning($fork)) {
                $newForks[$fork->getPid()] = $fork;
            }
        }
        if (empty($newForks)) {
            unset($this->forks[$name]);

            return 0;
        } else {
            $this->forks[$name] = $newForks;

            return count($this->forks[$name]);
        }
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
        return !$fork->isExited() && Posix::kill($fork->getPid(), 0);
    }


}
