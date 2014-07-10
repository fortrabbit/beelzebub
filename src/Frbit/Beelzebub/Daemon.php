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

use Frbit\Beelzebub\Helper\BuiltInDouble;
use Frbit\Beelzebub\Sleeper\RealSleeper;
use Frbit\System\UnixProcess\Manager;
use Frbit\System\UnixProcess\ProcessList;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Spork\Exception\ProcessControlException;

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
     * @var BuiltInDouble
     */
    protected $builtIn;

    /**
     * @var Fork[]
     */
    protected $forks;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var ProcessList
     */
    protected $processList;

    /**
     * @var int
     */
    protected $processListCounter = 0;

    /**
     * @var int
     */
    protected $processListTimeout;

    /**
     * @var Manager
     */
    protected $processes;

    /**
     * Handler called when restart signal USR1 is received
     *
     * @var callable
     */
    protected $restartHandler;

    /**
     * Signal send to childs on shutdown
     *
     * @var int
     */
    protected $shutdownSignal;

    /**
     * Grace timeout after which childs are kill by force (SIGKILL)
     *
     * @var int
     */
    protected $shutdownTimeout;

    /**
     * @var Sleeper
     */
    protected $sleeper;

    /**
     * @var Worker[]
     */
    protected $workers;

    /**
     * Create new daemon instance
     *
     * @param string          $name
     * @param LoggerInterface $logger
     * @param Manager         $processes
     * @param Sleeper         $sleeper
     * @param BuiltInDouble   $double
     */
    public function __construct(
        $name,
        LoggerInterface $logger = null,
        Manager $processes = null,
        Sleeper $sleeper = null,
        BuiltInDouble $double = null
    ) {
        $this->name      = $name;
        $this->logger    = $logger ?: new Logger($name, [new StreamHandler('php://stderr')]);
        $this->processes = $processes ?: new Manager();
        $this->builtIn   = $double ?: new BuiltInDouble();
        $this->sleeper   = $sleeper ?: new RealSleeper($this->builtIn);

        $this->workers         = [];
        $this->forks           = [];
        $this->shutdownSignal  = static::DEFAULT_SHUTDOWN_SIGNAL;
        $this->shutdownTimeout = static::DEFAULT_SHUTDOWN_GRACETIME;
    }

    /**
     * Add worker to daemon. Worker must be unique by name. If worker with name exists, it will be replaced.
     *
     * @param Worker $worker
     */
    public function addWorker(Worker $worker)
    {
        $this->workers[$worker->getName()] = $worker;
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
     * @return RealSleeper
     */
    public function getSleeper()
    {
        return $this->sleeper;
    }

    /**
     * @param string $name
     *
     * @return Worker|null
     */
    public function getWorker($name)
    {
        return isset($this->workers[$name]) ? $this->workers[$name] : null;
    }

    /**
     * Returns associative array of workers {name => Worker}
     *
     * @return Worker[]
     */
    public function getWorkers()
    {
        return $this->workers;
    }

    /**
     * Reads out pid file and sends shutdown signal to pid
     *
     * @param string $pidFile
     * @param bool   $forceKill If set to true and regular shutdown does not work after timeout -> send SIGKILL
     *
     * @return bool|null
     */
    public function halt($pidFile, $forceKill = false)
    {
        // no pidfile?
        if (!$this->builtIn->file_exists($pidFile)) {
            die("Pidfile \"$pidFile\" does not exist");
        }

        // pid invalid?
        $pid = $this->builtIn->file_get_contents($pidFile);
        if (!$pid) {
            die("Pidfile \"$pidFile\" does not contain a pid");
        }

        // initial not found
        $process = $this->getProcessList(true)->getByPid($pid);
        if (!$process) {
            die("No process found with pid \"$pid\"");
        }

        // send shutdown and wait ..
        $this->builtIn->posix_kill($process->getPid(), $this->getShutdownSignal());
        $end = time() + $this->getShutdownTimeout();
        while (time() < $end) {
            $process = $this->getProcessList(true)->getByPid($pid);
            if (!$process) {
                break;
            }
            $this->sleeper->sleep(1);
        }

        // process still there -> force kill?
        if ($process) {
            if ($forceKill) {
                $childs = $process->getAllChilds();
                foreach ($childs as $child) {
                    $this->builtIn->posix_kill($child->getPid(), SIGKILL);
                }
                $this->builtIn->posix_kill($process->getPid(), SIGKILL);
            } else {
                return false;
            }
        }

        // cleanup pid file
        $this->builtIn->unlink($pidFile);

        return true;
    }

    /**
     * Executes daemon by starting all child processes
     *
     * @param int|bool $timeout If int value > 0, then worker will exit after this time
     */
    public function run($timeout = false)
    {
        $this->setProcessName($this->name);

        // bind shutdown signal
        $stopped = false;
        $this->builtIn->pcntl_signal(SIGINT, function () use (&$stopped) {
            $this->logger->debug("Received INT shutdown in " . getmypid());
            $stopped = true;
        });
        $this->builtIn->pcntl_signal(SIGTERM, function () use (&$stopped) {
            $this->logger->debug("Received TERM shutdown in " . getmypid());
            $stopped = true;
        });
        $this->builtIn->pcntl_signal(SIGQUIT, function () use (&$stopped) {
            $this->logger->debug("Received QUIT shutdown in " . getmypid());
            $stopped = true;
        });
        $this->builtIn->pcntl_signal(SIGCLD, SIG_IGN);

        // bind restart signal
        $restart = false;
        $this->builtIn->pcntl_signal(SIGUSR1, function () use (&$restart) {
            $this->logger->debug("Received USR1 restart signal in " . getmypid());
            $restart = true;
        });

        $timeout = $timeout ? time() + $timeout : false;
        while (true) {

            try {
                $countBefore = $this->processListCounter;

                // handle restart of child processes
                if ($restart) {
                    if ($forks = $this->getAllForks()) {
                        $this->killForks($forks);
                    }

                    // execute restart handler, if any
                    if ($handler = $this->restartHandler) {
                        call_user_func($handler, $this);
                    }

                    $restart = false;
                }

                // assure worker running
                foreach ($this->workers as $name => $worker) {
                    $this->assureWorkerRuns($worker);
                }

                // shut down
                if ($stopped) {
                    break;
                }

                // if run only x iterations -> make sure we stop
                if ($timeout && $timeout > time()) {
                    break;
                }

                $countAfter = $this->processListCounter;
                $this->logger->debug("Ran ". ($countAfter - $countBefore). " times this cycle, total: {$countAfter}");
                $this->sleeper->sleep(10);

            } catch (\Exception $e) {
                $this->logger->critical("Failure in daemon loop: $e");
            }
        }

        if ($forks = $this->getAllForks()) {
            $this->killForks($forks);
        }
    }

    /**
     * Run daemon, detach from shell, close input/output and write pid to pid file
     *
     * @param string      $pidfile
     * @param bool|string $chroot
     * @param bool|int    $uid
     * @param bool|int    $gid
     *
     * @return int|bool
     */
    public function runDetached($pidfile, $chroot = false, $uid = false, $gid = false)
    {
        if ($this->builtIn->file_exists($pidfile) && ($pid = $this->builtIn->file_get_contents($pidfile))) {
            if ($this->getProcessList()->getByPid($pid)) {
                return false;
            }
        }

        $pid = $this->builtIn->pcntl_fork();
        if ($pid) {
            return $pid;
        } elseif (is_null($pid)) {
            die("Failed to fork");
        } else {
            if ($gid !== false) {
                $this->builtIn->posix_setgid($gid);
            }
            if ($uid !== false) {
                $this->builtIn->posix_setuid($uid);
            }
            if ($chroot !== false) {
                $this->builtIn->chroot($chroot);
            }
            $pid = getmypid();
            $this->builtIn->file_put_contents($pidfile, $pid);
            $this->logger->info("Daemonized with pid $pid ($pidfile)");

            // close standard file descriptors
            fclose(STDIN);
            fclose(STDOUT);
            fclose(STDERR);

            $sid = $this->builtIn->posix_setsid();
            $this->logger->info("SID $sid");
            $this->run();
            exit();
        }
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
     * @param callable $restartHandler
     */
    public function setRestartHandler($restartHandler)
    {
        $this->restartHandler = $restartHandler;
    }

    /**
     * Assure that given amount of forks for worker are running. Not more. Not less.
     *
     * @param Worker $worker
     * @param int    $amount
     *
     * @return Fork[]
     */
    protected function assureWorkerForks(Worker $worker, $amount)
    {
        $forks = $this->getRunningForks($worker);
        $count = count($forks);

        if ($count > $amount) {
            $kills = [];
            foreach (range(1, $count - $amount) as $num) {
                $kills[] = $forks[$num - 1];
            }
            $this->killForks($kills);
        }

        while ($count < $amount) {
            $forks [] = $fork = new Fork(function () use ($worker) {

                // set shutdown handler, might be overwritten by worker's startup ro so
                $this->builtIn->pcntl_signal(SIGTERM, function () { exit(); });
                $this->builtIn->pcntl_signal(SIGQUIT, function () { exit(); });
                $this->builtIn->pcntl_signal(SIGINT, function () { exit(); });
                $this->builtIn->pcntl_signal(SIGUSR1, SIG_IGN);
                $this->builtIn->pcntl_signal(SIGCLD, SIG_DFL);

                // set process name
                $this->setProcessName($worker->getName());

                // run startup
                if ($worker->hasStartup()) {
                    $worker->runStartup();
                }

                // run loop
                while (true) {
                    $worker->run();
                    $this->sleeper->sleep($worker->getInterval());
                }
                exit();
            });
            $this->logger->info("Started new process for {$worker->getName()} with pid {$fork->getPid()}");
            $count++;
        }

        return $forks;
    }

    /**
     * Makes sure worker runs with exactly the amount of proceses required
     *
     * @param Worker $worker
     */
    protected function assureWorkerRuns(Worker $worker)
    {
        if (!isset($this->forks[$worker->getName()])) {
            $this->forks[$worker->getName()] = [];
        }

        $amount = $worker->getAmount();
        $forks  = $this->assureWorkerForks($worker, $amount);

        $this->forks[$worker->getName()] = $forks;
    }

    /**
     * Reduces list of forks by running
     *
     * @param Fork[] $forks
     *
     * @return Fork[]
     */
    protected function checkForks(array $forks)
    {
        $running = [];
        foreach ($forks as $num => $fork) {
            if (!$fork->isExited()) { // && $this->getProcessList($num === 0)->getByPid($fork->getPid())) {
                $running[] = $fork;
            }
        }

        return $running;
    }

    /**
     * Get all running forks of all workers
     *
     * @return Fork[]
     */
    protected function getAllForks()
    {
        $forks = [];
        foreach ($this->forks as $workerName => $workerForks) {
            foreach ($workerForks as $fork) {
                $forks [] = $fork;
            }
        }

        return $forks;
    }

    /**
     * Cached access to process list.. speeds up things
     *
     * @param bool $forceRefresh
     *
     * @return ProcessList
     */
    protected function getProcessList($forceRefresh = false)
    {
        $this->processListCounter ++;
        $now = microtime();
        if ($forceRefresh || !$this->processListTimeout || $this->processListTimeout < $now) {
            $this->processListTimeout = $now + 0.5;
            $this->processList        = $this->processes->all();
        }

        return $this->processList;
    }

    /**
     * Get all running forks of a specific worker
     *
     * @param Worker $worker
     *
     * @return Fork[]
     */
    protected function getRunningForks(Worker $worker)
    {
        if (!isset($this->forks[$worker->getName()])) {
            return [];
        }

        /** @var Fork $fork */
        $forks = $this->checkForks($this->forks[$worker->getName()]);
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

    /**
     * Kill list of forks and their (if any) child processes
     *
     * @param Fork[] $forks
     */
    protected function killForks(array $forks)
    {
        $sendSignals = [];
        while ($forks) {
            foreach ($forks as $fork) {

                // re-check here to reduce the exception error on killing
                if ($fork->isExited()) { // || !$this->getProcessList()->getByPid($fork->getPid())) {
                    continue;
                }

                // already signaled -> either wait or kill brutally
                if (isset($sendSignals[$fork->getPid()])) {

                    // time to be brutal
                    $diff = $sendSignals[$fork->getPid()] - time();
                    if ($diff < 0) {
                        $this->logger->info("Must force kill worker pid {$fork->getPid()}");

                        // kill childs, if any
                        $processes = $this->getProcessList()->getByPpid($fork->getPid(), true);
                        foreach ($processes as $process) {
                            $this->builtIn->posix_kill($process->getPid(), SIGKILL);
                        }

                        // kill fork itself
                        try {
                            $fork->kill(SIGKILL);
                        } catch (ProcessControlException $e) {
                            $this->logger->error("Error force killing fork: {$e->getMessage()}");
                        }
                    } else {
                        $this->logger->debug("Waiting for pid {$fork->getPid()} to shut down (max: {$diff}sec)");
                    }
                } // not yet signaled -> do now
                else {
                    $this->logger->debug("Stopping obsolete pid {$fork->getPid()}, wait max {$this->getShutdownTimeout()}sec to kill");
                    $sendSignals[$fork->getPid()] = time() + $this->getShutdownTimeout();

                    // kill childs, if any
                    $processes = $this->getProcessList()->getByPpid($fork->getPid(), true);
                    foreach ($processes as $process) {
                        $this->builtIn->posix_kill($process->getPid(), $this->getShutdownSignal());
                    }

                    // kill fork iteself
                    try {
                        $fork->kill($this->getShutdownSignal());
                    } catch (ProcessControlException $e) {
                        $this->logger->error("Error killing fork: {$e->getMessage()}");
                    }
                }
            }

            $this->sleeper->sleep(1);
            if ($forks = $this->checkForks($forks)) {
                $this->logger->debug("Still waiting for " . count($forks) . " children");
            }
        }
    }


}
