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

use Monolog\Logger;

declare(ticks = 1);

/**
 * Base class for daemon
 *
 * @author Ulrich Kautz <uk@fortrabbit.com>
 */

interface DaemonInterface
{
    const DEFAULT_SAFE_SHUTDOWN_TRIES = 30;
    const DEFAULT_SLEEP_ENTROPY       = 50;

    /**
     * Constructor
     *
     * @param string $name    Name of the daemon
     * @param string $version Version of the daemon
     */
    public function __construct($name, $version);

    /**
     * Register a worker process
     *
     * @param mixed $mixed Either an array, a Worker instance or ..
     *
     * @throws \Exception
     */
    public function registerWorker($mixed);

    /**
     * Returns reference to registered worker by name
     *
     * @param string $name Name of the worker
     *
     * @return &WorkerInterface
     */
    public function getWorker($name);

    /**
     * Removes worker from list of workers. Returns bool whether any worker was removed
     *
     * @param string $name Name of the worker
     *
     * @return bool
     */
    public function unregisterWorker($name);

    /**
     * Set alternate shutdown timeout for clients (default: 30 seconds)
     *
     * @param int $timeout Timeout in seconds
     */
    public function setShutdownTimeout($timeout);

    /**
     * Set alternate shutdown signal. Default: SIGQUIT
     *
     * @param int $signal Shutdown signal
     */
    public function setShutdownSignal($signal);

    /**
     * Set closure which is called on after all child processe shave been shut down.
     *
     * @param Closure $handler Method to be called
     */
    public function setShutdownHandler(\Closure $handler);

    /**
     * Allows to introduce for entropy in handling
     *
     * @param int $entropy Entropy factor between 0 (no entropy) and 100 (completely random)
     *
     * @throws InvalidArgumentException
     */
    public function setSleepEntropy($entropy);

    /**
     * Sets restart signal. Should be either SIGUSR1 or SIGUSR2. If set, all workers
     * are killed on signal receival and, if set, the restart handler is called.
     * Then all workers are started again.
     * If set to false, restart handling is disabled (default).
     *
     * @param int $signal
     *
     * @throws InvalidArgumentException
     */
    public function setRestartSignal($signal);

    /**
     * Restart handler, which is used if restart signal is set.
     *
     * @param \Closure $handler
     *
     * @throws InvalidArgumentException
     */
    public function setRestartHandler(\Closure $handler);

    /**
     * Set logfile
     *
     * @param string $logfile  The output logfile
     * @param int    $logLevel The loglevel. Defaults to Logger::INFO
     */
    public function setLogfile($logfile, $logLevel);

    /**
     * Set alternate logger (if setting logfile is not sufficient)
     *
     * @param Monolog\Logger $logger New logger
     */
    public function setLogger(Logger $logger);

    /**
     * Get the logger instance
     *
     * @return Monolog\Logger
     */
    public function getLogger();

    /**
     * Run the primary loop
     *
     * @param string $loop Whether loop (true, default) or run once (false)
     */
    public function run($loop = true);

    /**
     * Run the primary, detach from shell and write PID to file. Returns PID of started child process.
     *
     * @param string $pidfile Path to pid file
     *
     * @throws \Exception
     *
     * @return int
     */
    public function runDetached($pidfile);

    /**
     * Stops a detached daemon with given pid file. Returns bool whether successfully stopped or not
     *
     * @param string $pidfile Path to pid file
     * @param int    $signal  Stop signal, defaults to SIGTERM
     *
     * @throws \Exception
     *
     * @return bool
     */
    public function stopDetached($pidfile, $signal = SIGTERM);

    /**
     * Sleep about given time, allows
     *
     * @param int $seconds Sleep time
     */
    public function sleepAbout($seconds);


}
