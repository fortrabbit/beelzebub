<?php


/*
 * This file is part of Fortrabbit\Beelzebub.
 *
 * (c) Ulrich Kautz <uk@fortrabbit.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Frbit\Beelzebub;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Spork\Fork;
use Spork\ProcessManager;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

declare(ticks = 1);

/**
 * Base class for daemon
 *
 * @author Ulrich Kautz <uk@fortrabbit.com>
 */
class Daemon
{

    /**
     * The default timeout for shutdown (after which force-kill will occur)
     */
    const DEFAULT_SHUTDOWN_GRACETIME = 30;

    /**
     * The default shutdown signal
     */
    const DEFAULT_SHUTDOWN_SIGNAL = SIGQUIT;

    /**
     * @var EventDispatcherInterface
     */
    protected $dispatcher;

    /**
     * @var Fork[]
     */
    protected $forks;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var ProcessManager
     */
    protected $manager;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var int
     */
    protected $shutdownSignal;

    /**
     * @var int
     */
    protected $shutdownTimeout;

    /**
     * @var Worker[]
     */
    protected $workers;

    /**
     * Create new daemon instance
     *
     * @param string                   $name
     * @param ProcessManager           $manager
     * @param LoggerInterface          $logger
     * @param EventDispatcherInterface $dispatcher
     */
    public function __construct($name, ProcessManager $manager = null, LoggerInterface $logger = null, EventDispatcherInterface $dispatcher = null)
    {
        $this->name       = $name;
        $this->manager    = $manager ? : new ProcessManager();
        $this->logger     = $logger ? : new Logger($name, [new StreamHandler('php://stderr')]);
        $this->dispatcher = $dispatcher ? : new EventDispatcher();

        $this->workers        = [];
        $this->forks          = [];
        $this->shutdownSignal = static::DEFAULT_SHUTDOWN_SIGNAL;
    }

    /**
     * Add worker to daemon
     *
     * @param Worker $worker
     */
    public function addWorker(Worker $worker)
    {
        $this->workers[$worker->getName()] = $worker;
    }

    /**
     * Get the event dispatcher
     *
     * @return EventDispatcherInterface
     */
    public function getEventDispatcher()
    {
        return $this->dispatcher;
    }

    /**
     * Get the logger
     *
     * @return LoggerInterface
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * Get the process manager
     *
     * @return ProcessManager
     */
    public function getProcessManager()
    {
        return $this->manager;
    }

    /**
     * Get shutdown signal
     *
     * @return int
     */
    public function getShutdownSignal()
    {
        return $this->shutdownSignal;
    }

    /**
     * Set shutdown signal.. something like SIGINT, SIGTERM, ..
     *
     * @parma int $signo
     */
    public function setShutdownSignal($signo)
    {
        $this->shutdownSignal = $signo;
    }

    /**
     * Get shutdown timeout seconds
     *
     * @return int
     */
    public function getShutdownTimeout()
    {
        return $this->shutdownTimeout;
    }

    /**
     * Set shutdown timeout in seconds (until workers are killed with SIGKILL)
     *
     * @param int $seconds
     */
    public function setShutdownTimeout($seconds)
    {
        $this->shutdownTimeout = $seconds;
    }

    /**
     * Reads out pid file and sends shutdown signal to pid
     *
     * @param string $pidfile
     * @param bool   $forceKill If set to true and regular shutdown does not work after timeout -> send SIGKILL
     *
     * @return bool|null
     */
    public function halt($pidfile, $forceKill = false)
    {
        // TODO
    }

    /**
     * Executes daemon by starting all child processes
     *
     * @param int|bool $iterations If true, run infinite
     */
    public function run($iterations = true)
    {
        $this->setProcessName($this->name);
        $stopped = false;
        pcntl_signal($this->getShutdownSignal(), function () use (&$stopped) {
            $this->logger->info("Received shutdown in " . getmypid());
            $stopped = true;
        });

        $count = 0;
        while (true) {
            foreach ($this->workers as $name => $worker) {
                $this->assureWorkerRuns($worker);
            }
            $this->manager->wait(false);
            if ($stopped) {
                break;
            }
            if ($iterations !== true) {
                $count++;
                if ($count > $iterations) {
                    break;
                }
            }
            sleep(1);
        }

        foreach ($this->workers as $name => $worker) {
            $this->assureMaxForks($worker, 0);
        }

        while (true) {
            $count = 0;
            foreach ($this->workers as $name => $worker) {
                $count += isset($this->forks[$name]) ? count($this->forks[$name]) : 0;
            }
            if (!$count) {
                break;
            }
            $this->logger->debug("Waiting for $count childs to die in " . getmypid());
            sleep(1);
        }
    }

    /**
     * Run daemon, detach from shell, close input/output and write pid to pid file
     *
     * @param string $pidfile
     *
     * @throws \RuntimeException
     */
    public function runDetached($pidfile)
    {
        // TODO
    }

    /**
     * Set process name (if pecl-proctitle available)
     *
     * @param $name
     */
    public function setProcessName($name)
    {
        if (function_exists('cli_set_process_title')) {
            cli_set_process_title($name);
        } elseif (function_exists('setproctitle')) {
            setproctitle($name);
        }
    }

    /**
     * @param Worker $worker
     * @param int    $amount
     *
     * @return Fork[]
     */
    protected function assureMaxForks(Worker $worker, $amount)
    {
        $forks = $this->checkRunning($worker);
        $count = count($forks);

        $sendSignals = [];
        while ($count > $amount) {
            $kills = [];
            foreach (range(1, $count - $amount) as $num) {
                $kills[] = $forks[$num - 1];
            }

            /** @var Fork $fork */
            error_log("WILL ITERATE " . count($kills));
            foreach ($kills as $fork) {
                error_log("FOR FORK {$fork->getPid()}");
                if (isset($sendSignals[$fork->getPid()])) {
                    error_log("ALREADY SEND {$fork->getPid()}");
                    if ($sendSignals[$fork->getPid()] > time()) {
                        $this->logger->info("Must kill worker {$worker->getName()} with pid {$fork->getPid()}");
                        $fork->kill(SIGKILL);
                    } else {
                        $this->logger->debug("Waiting for worker {$worker->getName()} with pid {$fork->getPid()} to shut down");
                        // wait ..
                    }
                } else {
                    error_log("NOW SEND {$fork->getPid()}");
                    $this->logger->info("Stopping obsolete worker {$worker->getName()} with pid {$fork->getPid()}");
                    $sendSignals[$fork->getPid()] = time() + $this->getShutdownTimeout();
                    $fork->kill(SIGTERM);
                    $this->manager->wait(true);
                }
            }
            sleep(1);
            $forks = $this->checkRunning($worker);
            $count = count($forks);
        }

        while ($count < $amount) {
            $forks [] = $fork = $this->manager->fork(function () use ($worker) {
                pcntl_signal($this->getShutdownSignal(), function () { exit(); });
                $this->setProcessName($worker->getName());
                while (true) {
                    $worker->run();
                    sleep($worker->getInterval());
                }
            });
            $this->logger->info("Started new process for {$worker->getName()} with pid {$fork->getPid()}");
            $count++;
        }

        return $forks;
    }

    protected function assureWorkerRuns(Worker $worker)
    {
        if (!isset($this->forks[$worker->getName()])) {
            $this->forks[$worker->getName()] = [];
        }

        $amount = $worker->getAmount();
        $forks  = $this->assureMaxForks($worker, $amount);

        $this->forks[$worker->getName()] = $forks;
    }

    /**
     * @param Worker $worker
     *
     * @return Fork[]
     */
    protected function checkRunning(Worker $worker)
    {
        if (!isset($this->forks[$worker->getName()])) {
            return [];
        }

        /** @var Fork $fork */
        $forks = [];
        foreach ($this->forks[$worker->getName()] as $fork) {
            if (!$fork->isExited()) {
                $this->logger->debug("Process {$fork->getPid()} for {$worker->getName()} in state {$fork->getState()}");
                $forks [] = $fork;
            }
        }
        usort($forks, function (Fork $forkA, Fork $forkB) {
            return $forkA->getPid() > $forkB->getPid()
                ? 1
                : ($forkA->getPid() === $forkB->getPid()
                    ? 0
                    : -1
                );
        });

        return $this->forks[$worker->getName()] = $forks;
    }


}
