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

use Monolog\Logger;
use Spork\ProcessManager;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

declare(ticks = 1);

/**
 * Base class for daemon
 *
 * @author Ulrich Kautz <uk@fortrabbit.com>
 */

interface Daemon
{

    const DEFAULT_SHUTDOWN_TIMEOUT = 30;
    const DEFAULT_SHUTDOWN_SIGNAL  = SIGQUIT;

    /**
     * Create new daemon instance
     *
     * @param ProcessManager           $manager
     * @param Logger                   $logger
     * @param EventDispatcherInterface $event
     */
    public function __construct(ProcessManager $manager, Logger $logger, EventDispatcherInterface $event);

    /**
     * Add worker to daemon
     *
     * @param Worker $worker
     */
    public function addWorker(Worker $worker);

    /**
     * Executes daemon by starting all childs processes
     *
     * @param int|bool $iterations If true, run infinite
     */
    public function run($iterations = true);

    /**
     * Set shutdown signal.. something like SIGINT, SIGTERM, ..
     *
     * @parma int $signo
     */
    public function setShutdownSignal($signo);

    /**
     * Get shutdown signal
     *
     * @return int
     */
    public function getShutdownSignal();

    /**
     * Set shutdown timeout in seconds (until workers are killed with SIGKILL)
     *
     * @param int $seconds
     */
    public function setShutdownTimeout($seconds);

    /**
     * Get shutdown timeout seconds
     *
     * @return int
     */
    public function getShutdownTimeout();

    /**
     * Get the process manager
     *
     * @return ProcessManager
     */
    public function getProcessManager();

    /**
     * Get the logger
     *
     * @return Logger
     */
    public function getLogger();

    /**
     * Get the event dispatcher
     *
     * @return EventDispatcherInterface
     */
    public function getEventDispatcher();


}
