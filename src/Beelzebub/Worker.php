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
use Spork\Fork;

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
     * @param int      $interval Wait interval after each run
     * @param callable $loop     Loop callback of the worker
     * @param callable $startup  Optional startup callback of the worker
     * @param int      $amount   Amount of forks
     */
    public function __construct($name, $loop, $interval = 1, $startup = null, $amount);

    /**
     * Set daemon after added
     *
     * @param Daemon $daemon
     */
    public function setDaemon(Daemon $daemon);

    /**
     * Run worker loop callback
     *
     * @param array $args    Args from startup
     */
    public function run(array $args = array());

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
     * Set wait interval after each loop-run
     *
     * @param int $interval
     */
    public function setInterval($interval);

    /**
     * Returns wait-interval after each worker run
     *
     * @return int
     */
    public function getInterval();

    /**
     * Set should-amount of processes
     *
     * @param int $amount
     */
    public function setAmount($amount);

    /**
     * Returns should-amount of instances/process
     *
     * @return int
     */
    public function getAmount();


}
