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

use Fortrabbit\Beelzebub\Daemon;

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
     * @param string   $name     Name of the worker
     * @param int      $interval Interval this worker is to be called
     * @param callback $loop Implementation of the worker
     * @param callback $startup  To be called once at startup
     * @param int      $amount   Amount of instances to run
     */
    public function __construct($name, $interval, $loop, $startup = null, $amount = 1);

    /**
     * Run worker loop callback
     *
     * @param Daemon &$daemon The paren daemon
     * @param array                       $args    Args from startup
     */
    public function runLoop(Daemon &$daemon, array $args = array());

    /**
     * Checks whether worker has startup method
     *
     * @return bool
     */
    public function hasStartup();

    /**
     * Run the actual startup method
     *
     * @param Daemon &$daemon The paren daemon
     *
     * @return bool
     */
    public function runStartup(Daemon &$daemon);

    /**
     * Getter for name
     *
     * @return string
     */
    public function getName();

    /**
     * Getter for interval
     *
     * @return int
     */
    public function getInterval();

    /**
     * Setter for interval
     *
     * @param int $interval New interval in seconds
     */
    public function setInterval($interval);

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
