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

/**
 * Base class for daemon
 *
 * @author Ulrich Kautz <uk@fortrabbit.com>
 */

interface Worker
{

    /**
     * Constructor
     *
     * @param Daemon   $daemon   The daemon it shall run in
     * @param string   $name     Name of the worker
     * @param int      $interval Wait interval after each run
     * @param \Closure $loop     Loop callback of the worker
     * @param \Closure $startup  Optional startup callback of the worker
     * @param int      $amount   Amount of instances to run
     */
    public function __construct(Daemon $daemon, $name, \Closure $loop, $interval = 1, \Closure $startup = null, $amount = 1);

    /**
     * Run worker loop callback
     *
     * @param array $args    Args from startup
     */
    public function runLoop(array $args = array());

    /**
     * Checks whether worker has startup method
     *
     * @return bool
     */
    public function hasStartup();

    /**
     * Run the actual startup method
     *
     * @return bool
     */
    public function runStartup();

    /**
     * Getter for name
     *
     * @return string
     */
    public function getName();

    /**
     * Getter for amount
     *
     * @return int
     */
    public function getAmount();

    /**
     * Setter for amount
     *
     * @param int $amount New amount
     */
    public function setAmount($amount);

    /**
     * Returns wait-interval after each worker run
     *
     * @return int
     */
    public function getInterval();

    /**
     * Add pid to pid list .. called from Daemon
     *
     * @param int $pid
     */
    public function addPid($pid);

    /**
     * Remove pid from list .. called from Daemon
     *
     * @param int $pid
     */
    public function removePid($pid);

    /**
     * Returns all pids
     *
     * @return array
     */
    public function getPids();

    /**
     * Returns amount of running processes
     *
     * @return int
     */
    public function countRunning();

}
