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
use Spork\Fifo;
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
     * The worker running in a child process
     *
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
                $this->assureWorkerForkRunning($worker);
            }
            if ($iterations !== true && --$iterations === 0) {
                break;
            }

            $interval = 20;
            for ($i = 0; $i < $interval; $i++) {
                BuiltIn::doUsleep(100000);
                if ($this->stopped) {
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
    public function handleDaemonShutdownSignal($signo)
    {
        $this->logger->debug("Received SIG $signo in parent");
        switch ($signo) {
            case SIGTERM:
            case SIGQUIT:
            case SIGINT:
                if (!$this->stopped) {
                    $this->logger->info("Shutting down parent, sending shutdown to all workers");
                    $this->stopped = true;
                    $this->event->dispatch(Event::EVENT_DAEMON_STOPPING, new Event($this));
                    $this->shutdownWorkersFromParent();
                    $this->event->dispatch(Event::EVENT_DAEMON_STOPPED, new Event($this));
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
        $this->logger->debug("Received SIG $signo in worker '{$this->currentWorker->getName()}' WITH PID " . getmypid());
        switch ($signo) {
            case SIGTERM:
            case SIGQUIT:
            case SIGINT:
                $this->stopped = true;
                $this->event->dispatch(Event::EVENT_WORKER_STOPPING, new Event($this->currentWorker));
                //Builtin::doExit();
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
    public function getShutdownSignal()
    {
        return $this->shutdownSignal;
    }

    /**
     * {@inheritdoc}
     */
    public function setShutdownTimeout($seconds)
    {
        $this->shutdownTimeout = $seconds;
    }

    /**
     * {@inheritdoc}
     */
    public function getShutdownTimeout()
    {
        return $this->shutdownTimeout;
    }

    /**
     * {@inheritdoc}
     */
    public function getProcessManager()
    {
        return $this->manager;
    }

    /**
     * {@inheritdoc}
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * {@inheritdoc}
     */
    public function getEventDispatcher()
    {
        return $this->event;
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
            $this->event->dispatch(Event::EVENT_WORKER_STARTING, new Event($worker));
            $fork                                = $this->manager
                ->fork(function () use ($worker, $self) {
                    $this->currentWorker = $worker;
                    $self->bindWorkerSignals();
                    $startupArgs = array();
                    if ($worker->hasStartup()) {
                        $startupArgs = $worker->runStartup();
                        if (!is_array($startupArgs)) {
                            $startupArgs = array($startupArgs);
                        }
                    }

                    $intervals = $worker->getInterval() * 10;
                    while (true) {
                        $worker->run($startupArgs);

                        for ($i = 0; $i < $intervals; $i++) {
                            Builtin::doUsleep(100000);
                            if ($self->stopped) {
                                $this->event->dispatch(Event::EVENT_WORKER_STOPPED, new Event($this->currentWorker));
                                break 2;
                            }
                        }
                    }
                })
                ->then(function (Fork $fork) use ($name, $self) {
                    unset($self->forks[$fork->getPid()][$name]);
                });
            $this->forks[$name][$fork->getPid()] = $fork;
            $this->event->dispatch(Event::EVENT_WORKER_STARTED, new Event($worker), array($fork));
        }
    }

    /**
     * Binds signals for parent to handlers
     */
    protected function bindParentSignals()
    {
        Pcntl::signal(SIGTERM, array($this, 'handleDaemonShutdownSignal'));
        Pcntl::signal(SIGQUIT, array($this, 'handleDaemonShutdownSignal'));
        Pcntl::signal(SIGINT, array($this, 'handleDaemonShutdownSignal'));
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
        foreach ($this->forks as $name => $forks) {
            foreach ($forks as $fork) {
                if ($this->reallyRunning($fork)) {
                    $this->event->dispatch(Event::EVENT_WORKER_KILL, new Event($this->workers[$name]), array($fork));
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
