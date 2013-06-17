<?php


/*
 * This file is part of Fortrabbit\Beelzebub.
 *
 * (c) Ulrich Kautz <uk@fortrabbit.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Fortrabbit\Beelzebub;

use Fortrabbit\Beelzebub\DaemonInterface;
use Fortrabbit\Beelzebub\Worker;
use Fortrabbit\Beelzebub\WorkerInterface;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

declare(ticks = 1);

/**
 * Base class for daemon
 *
 * @author Ulrich Kautz <uk@fortrabbit.com>
 */

class Daemon implements DaemonInterface
{

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $version;

    /**
     * @var array
     */
    protected $workers;

    /**
     * @var array
     */
    protected $pids;

    /**
     * @var bool
     */
    protected $stopped;

    /**
     * @var bool
     */
    protected $restarting;

    /**
     * @var int
     */
    protected $shutdownTries;

    /**
     * @var int
     */
    protected $shutdownSignal;

    /**
     * @var Closure
     */
    protected $shutdownHandler;

    /**
     * @var int
     */
    protected $restartSignal;

    /**
     * @var Closure
     */
    protected $restartHandler;

    /**
     * @var callback
     */
    protected $sleepCall;

    /**
     * @var Monolog\Logger
     */
    protected $logger;

    /**
     * {@inheritdoc}
     */
    public function __construct($name, $version = '1.0.0')
    {
        $this->name           = $name;
        $this->version        = $version;
        $this->workers        = array();
        $this->pids           = array();
        $this->stopped        = false;
        $this->restarting     = false;
        $this->shutdownTries  = DaemonInterface::DEFAULT_SAFE_SHUTDOWN_TRIES;
        $this->shutdownSignal = SIGQUIT;
        $this->logger         = new Logger($name);
        $this->logger->pushHandler(new StreamHandler("php://stderr"), Logger::INFO);
        $this->setSleepEntropy(DaemonInterface::DEFAULT_SLEEP_ENTROPY);
    }


    /**
     * Closes STDOUT, STDERR and STDIN and replaces them with /dev/null
     *
     * @throws \Exception
     */
    protected function closeInOutStreams()
    {
        global $STDIN, $STDOUT, $STDERR;

        // close all standard i/o
        fclose(STDIN);
        fclose(STDOUT);
        fclose(STDERR);

        // replace with null i/o
        $STDIN  = fopen('/dev/null', 'r');
        $STDOUT = fopen('/dev/null', 'ab');
        $STDERR = fopen('/dev/null', 'ab');
    }


    /**
     * {@inheritdoc}
     */
    public function registerWorker($mixed)
    {
        $args = func_get_args();

        // array format: add multiple
        if (is_array($mixed)) {
            foreach ($mixed as $name => $definition) {
                $create = array($name);
                foreach (array('interval', 'loop') as $required) {
                    if (!isset($definition[$required])) {
                        throw new \InvalidArgumentException("Missing arg '$required' for '$name'");
                    }
                    $create[] = $definition[$required];
                }
                foreach (array('startup', 'amount') as $optional) {
                    if (isset($definition[$optional])) {
                        $create[] = $definition[$optional];
                    } else {
                        $create[] = null;
                    }
                }
                $worker = new Worker($create[0], $create[1], $create[2], $create[3], $create[4]);
                $this->registerWorker($worker);
            }
        }

        // add single instance of Worker
        elseif (is_object($mixed) && in_array('Fortrabbit\\Beelzebub\\WorkerInterface', class_implements(get_class($mixed)))) {
            if (isset($this->workers[$mixed->getName()])) {
                throw new \Exception("Worker with name '". $mixed->getName(). "' already attached");
            }
            $this->workers[$mixed->getName()] = &$mixed;
        }

        // using simple params [<name>, <interval>, <loop>, [<startup>, [<amount>]]]
        elseif (is_string($mixed) && count($args) > 2) {
            @list($name, $interval, $loop, $startup, $amount) = $args;
            $worker = new Worker($name, $interval, $loop, $startup, $amount);
            return $this->registerWorker($worker);
        }

        // unrecognized
        else {
            throw new \InvalidArgumentException("Failed to add object ". (is_object($mixed) ? get_class($mixed) : "SCALAR"));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getWorker($name)
    {
        if (isset($this->workers[$name])) {
            return $this->workers[$name];
        }
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function unregisterWorker($name)
    {
        if (isset($this->workers[$name])) {
            unset($this->workers[$name]);
            return true;
        }
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function setShutdownTimeout($timeout)
    {
        $this->shutdownTries = $timeout;
    }

    /**
     * {@inheritdoc}
     */
    public function setShutdownSignal($signal)
    {
        $this->shutdownSignal = $signal;
    }

    /**
     * {@inheritdoc}
     */
    public function setShutdownHandler(\Closure $handler)
    {
        $this->shutdownHandler = $handler;
    }

    /**
     * {@inheritdoc}
     */
    public function setSleepEntropy($entropy)
    {
        if (!is_numeric($entropy) || $entropy > 100 || $entropy < 0) {
            throw new \InvalidArgumentException("Entropy needs to be between 0 and 100");
        }

        // determine sleep
        $sleepCall = null;
        if ($entropy > 0) {
            $randomCall = function_exists('mt_rand') ? 'mt_rand' : 'rand';
            $sleepCall  = function () use ($randomCall, $entropy) {
                $fixed = 1000000.0 * (100 - $entropy)/100;
                $sleep = (int)$fixed + $randomCall(0, (int)(1000000.0 - $fixed) * 2);
                usleep($sleep);
            };
        } else {
            $sleepCall = function () {
                sleep(1);
            };
        }

        $this->sleepCall = $sleepCall;
    }

    /**
     * {@inheritdoc}
     */
    public function setRestartSignal($signal)
    {
        if ($signal === false) {
            if ($this->restartSignal) {
                pcntl_signal($this->restartSignal, SIG_DFL);
            }
            $this->restartSignal = false;
            return ;
        } else {
            $self = &$this;
            pcntl_signal($signal, function () use (&$self) {
                $self->getLogger()->info("Performing restart");
                $self->restarting = true;
            });
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setRestartHandler(\Closure $handler)
    {
        $this->restartHandler = $handler;
    }

    /**
     * {@inheritdoc}
     */
    public function setLogfile($logFile, $logLevel = Logger::INFO)
    {
        $this->logger->popHandler();
        $this->logger->pushHandler(new StreamHandler($logFile), $logLevel);
    }

    /**
     * {@inheritdoc}
     */
    public function setLogger(Logger $logger)
    {
        $this->logger = $logger;
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
    public function run($loop = true)
    {
        $this->bindParentSignals();

        while (true) {

            // determine running pids
            $this->pids = $this->checkWorkerPids();

            // start/restart all required instances
            foreach ($this->workers as $worker) {
                $running = isset($this->pids[$worker->getName()])
                    ? count($this->pids[$worker->getName()])
                    : 0;

                while ($running++ < $worker->getAmount()) {

                    if ($loop) {
                        $pid = pcntl_fork();
                        switch ($pid) {
                            case -1:
                                throw new \Exception("Failed to fork worker {$worker->getName()} from parent");
                                break;
                            case 0:
                                $this->executeWorker($worker, $loop);
                                break;
                            default:
                                $this->registerWorkerPid($worker, $pid);
                                break;
                        }
                    } else {
                        $this->executeWorker($worker, $loop);
                    }

                    if ($this->stopped || $this->restarting) {
                        break;
                    }
                }

                if ($this->stopped || $this->restarting) {
                    break;
                }
            }

            if ($this->stopped) {
                return $this->shutdownWorkers();
            } else if ($this->restarting) {
                $this->shutdownWorkers();
                $this->restarting = false;
            } elseif (!$loop) {
                return;
            }
            $this->sleepAbout(2);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function runDetached($pidfile)
    {
        // check pid file
        if (is_readable($pidfile)) {
            $pid = file_get_contents($pidfile);
            if (posix_kill($pid, 0)) {
                throw new \Exception("Instance of {$this->name} already runing with PID $pid");
            }
        }

        $pid = pcntl_fork();
        switch($pid) {

            // fail
            case -1:
                throw new \Exception("Failed to fork from parent");
                break;

            // child
            case 0:
                // become session leader
                if (posix_setsid() === -1) {
                    throw new \Exception("Failed to become session leader");
                }

                // run
                $this->closeInOutStreams();
                $this->run();
                exit;
                break;

            // parent
            default:
                file_put_contents($pidfile, $pid);
                return $pid;
                break;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function stopDetached($pidfile, $signal = SIGTERM)
    {
        // check pid file
        if (is_readable($pidfile)) {
            $pid = file_get_contents($pidfile);
            if (posix_kill($pid, 0)) {
                posix_kill($pid, $signal);
                return true;
            }
        }
        return false;
    }

    /**
     * Handle signals from daemon (parent). Needs to be public so can be used with pcntl_signal
     *
     * @param int $sigNum Signal number
     */
    public function handleSignal($sigNum)
    {
        $this->logger->addDebug("Received SIG $sigNum in parent");
        switch ($sigNum) {
            case SIGTERM:
            case SIGQUIT:
            case SIGINT:
                if (!$this->stopped) {
                    $this->stopped = true;
                    $this->shutdownWorkers();
                    exit;
                }
                break;
        }
    }

    /**
     * Handle signals from worker (child). Needs to be public so can be used with pcntl_signal
     *
     * @param int $sigNum Signal number
     */
    public function handleWorkerSignal($sigNum)
    {
        $this->logger->addDebug("Received SIG $sigNum in worker");
        switch ($sigNum) {
            case SIGTERM:
            case SIGQUIT:
            case SIGINT:
                exit;
            break;
        }
    }


    /**
     * Shutdown all workers
     */
    protected function shutdownWorkers()
    {
        $this->logger->addInfo("Shutting down all workers");
        foreach ($this->pids as $workerName => $pids) {
            foreach ($pids as $pid) {
                if (posix_kill($pid, 0)) {
                    $this->logger->addInfo(" Killing worker $workerName with pid $pid");
                    posix_kill($pid, $this->shutdownSignal);
                }
            }
        }
        usleep(100000);

        $tries = $this->shutdownTries;
        $persisting = array();
        while ($tries-- > 0) {
            $persisting = array();
            foreach ($this->pids as $workerName => $pids) {
                foreach ($pids as $pid) {
                    if (posix_kill($pid, 0)) {
                        $this->logger->addDebug("Worker $workerName with pid $pid persisting");
                        $persisting[] = [$workerName, $pid];
                    }
                }
            }

            if ($persisting) {
                $this->logger->addDebug("Found ". count($persisting). " still running workers. $tries secs till nukedown.");
            } else {
                break;
            }
            sleep(1);
        }

        foreach ($persisting as $ref) {
            list($workerName, $pid) = $ref;
            $this->logger->addInfo("Nuking worker $workerName with $pid.");
            posix_kill($pid, SIGKILL);
        }
        if ($this->restarting) {
            if ($handler = $this->restartHandler) {
                $handler($this);
            }
            $this->logger->addInfo("Restart complete.");
        } else {
            if ($handler = $this->shutdownHandler) {
                $this->logger->addInfo("Calling shutdown handler.");
                $handler($this);
            }
            $this->logger->addInfo("Shutdown complete.");
        }
    }

    /**
     * {@inheritdoc}
     */
    public function sleepAbout($seconds)
    {
        $sleepCall = $this->sleepCall;

        // do sleep
        foreach (range(1, $seconds) as $num) {
            if ($this->stopped) {
                exit;
            }
            $sleepCall();
        }
    }

    /**
     * Registers worker PID
     *
     * @param Fortrabbit\Beelzebub\WorkerInterface $worker The worker
     * @param int                                  $pid    The process ID
     */
    protected function registerWorkerPid(WorkerInterface $worker, $pid)
    {
        $this->logger->addInfo("Register instance for {$worker->getName()} with pid $pid");
        $this->pids[$worker->getName()][] = $pid;
    }

    /**
     * Executes a worerk in loop
     *
     * @param Fortrabbit\Beelzebub\WorkerInterface &$worker The worker
     * @param bool                                 $loop    Whether loop execution
     */
    protected function executeWorker(WorkerInterface &$worker, $loop = true)
    {
        $this->bindWorkerSignals();

        // startup
        $args = array(&$this);
        if ($worker->hasStartup()) {
            $res = $worker->runStartup($this);
            if (!is_null($res)) {
                $args[] = $res;
            }
        }

        // loop
        while (true) {
            call_user_func_array(array($worker, 'runLoop'), $args);
            if (!$loop) {
                break;
            }
            $this->sleepAbout($worker->getInterval());
        }

        if ($loop) {
            exit;
        }
    }

    /**
     * Checks worker pids and returns assoc array of [name => [pids]] for running pids
     *
     * @return array
     */
    protected function checkWorkerPids()
    {
        $foundWorkers = array();
        foreach ($this->pids as $workerName => $pids) {
            $okPids = array();
            foreach ($pids as $pid) {
                if (posix_kill($pid, 0)) {
                    $okPids[] = $pid;
                }
            }
            $foundWorkers[$workerName] = $okPids;
        }
        return $foundWorkers;
    }

    /**
     * Binds signals for parent to handlers
     */
    protected function bindParentSignals()
    {
        pcntl_signal(SIGTERM, array($this, 'handleSignal'));
        pcntl_signal(SIGQUIT, array($this, 'handleSignal'));
        pcntl_signal(SIGINT,  array($this, 'handleSignal'));
        pcntl_signal(SIGCHLD, SIG_IGN);
    }

    /**
     * Binds signals for child (worker) to handlers
     */
    protected function bindWorkerSignals()
    {
        pcntl_signal(SIGTERM, array($this, 'handleWorkerSignal'));
        pcntl_signal(SIGQUIT, array($this, 'handleWorkerSignal'));
        pcntl_signal(SIGINT,  array($this, 'handleWorkerSignal'));
        pcntl_signal(SIGCHLD, array($this, 'handleWorkerSignal'));
    }




}
