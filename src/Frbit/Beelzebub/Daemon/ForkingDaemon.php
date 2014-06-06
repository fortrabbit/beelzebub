<?php


/*
 * This file is part of Fortrabbit\Beelzebub.
 *
 * (c) Ulrich Kautz <uk@fortrabbit.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Frbit\Beelzebub\Daemon;

use Frbit\Beelzebub\Daemon;
use Frbit\Beelzebub\Worker;
use Psr\Log\LoggerInterface;
use Spork\ProcessManager;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Base class for daemon
 *
 * @author Ulrich Kautz <uk@fortrabbit.com>
 */
class ForkingDaemon implements Daemon
{


    /**
     * Create new daemon instance
     *
     * @param ProcessManager           $manager
     * @param LoggerInterface          $logger
     * @param EventDispatcherInterface $event
     */
    public function __construct(ProcessManager $manager, LoggerInterface $logger, EventDispatcherInterface $event)
    {
        c
    }

    /**
     * Set process name (if pecl-proctitle available)
     *
     * @param $name
     */
    public function setProcessName($name)
    {
        // TODO: Implement setProcessName() method.
    }

    /**
     * Add worker to daemon
     *
     * @param Worker $worker
     */
    public function addWorker(Worker $worker)
    {
        // TODO: Implement addWorker() method.
    }

    /**
     * Executes daemon by starting all childs processes
     *
     * @param int|bool $iterations If true, run infinite
     */
    public function run($iterations = true)
    {
        // TODO: Implement run() method.
    }

    /**
     * Run daemon, detach from shell, close input/output and write pid to pid file
     *
     * @param File $pidfile
     *
     * @throws \RuntimeException
     */
    public function runDetached(File $pidfile)
    {
        // TODO: Implement runDetached() method.
    }

    /**
     * Reads out pid file and sends shutdown signal to pid
     *
     * @param File $pidfile
     * @param bool $forceKill If set to true and regular shutdown does not work after timeout -> send SIGKILL
     *
     * @return bool|null
     */
    public function halt(File $pidfile, $forceKill = false)
    {
        // TODO: Implement halt() method.
    }

    /**
     * Set shutdown signal.. something like SIGINT, SIGTERM, ..
     *
     * @parma int $signo
     */
    public function setShutdownSignal($signo)
    {
        // TODO: Implement setShutdownSignal() method.
    }

    /**
     * Get shutdown signal
     *
     * @return int
     */
    public function getShutdownSignal()
    {
        // TODO: Implement getShutdownSignal() method.
    }

    /**
     * Set shutdown timeout in seconds (until workers are killed with SIGKILL)
     *
     * @param int $seconds
     */
    public function setShutdownTimeout($seconds)
    {
        // TODO: Implement setShutdownTimeout() method.
    }

    /**
     * Get shutdown timeout seconds
     *
     * @return int
     */
    public function getShutdownTimeout()
    {
        // TODO: Implement getShutdownTimeout() method.
    }

    /**
     * Get the process manager
     *
     * @return ProcessManager
     */
    public function getProcessManager()
    {
        // TODO: Implement getProcessManager() method.
    }

    /**
     * Get the logger
     *
     * @return LoggerInterface
     */
    public function getLogger()
    {
        // TODO: Implement getLogger() method.
    }

    /**
     * Get the event dispatcher
     *
     * @return EventDispatcherInterface
     */
    public function getEventDispatcher()
    {
        // TODO: Implement getEventDispatcher() method.
    }
}
