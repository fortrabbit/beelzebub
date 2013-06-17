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
use Fortrabbit\Beelzebub\WorkerInterface;

/**
 * Base class for Worker
 *
 * @author Ulrich Kautz <uk@fortrabbit.com>
 */

class Worker implements WorkerInterface
{

    /**
     * @var string
     */
    protected $name;

    /**
     * @var int
     */
    protected $interval;

    /**
     * @var int
     */
    protected $amount;

    /**
     * @var callback
     */
    protected $loop;

    /**
     * @var callback
     */
    protected $startup;

    /**
     * {@inheritdoc}
     */
    public function __construct($name, $interval, $loop, $startup = null, $amount = 1)
    {
        $this->name     = $name;
        $this->interval = $interval;
        $this->loop     = $loop;
        $this->startup  = $startup;
        $this->amount   = $amount ?: 1;
    }

    /**
     * Run worker loop callback
     *
     * @param Fortrabbit\Beelzebub\Deamon $daemon The paren daemon
     * @param array                  $args   Args from startup
     */
    public function runLoop(DaemonInterface &$daemon, array $args = array())
    {
        $callArgs = array(&$this, &$daemon);
        if ($args) {
            $callArgs[] = $args;
        }
        call_user_func_array($this->loop, $callArgs);
    }

    /**
     * Checks whether worker has startup method
     *
     * @return bool
     */
    public function hasStartup()
    {
        return $this->startup ? true : false;
    }

    /**
     * Run the actual startup method
     *
     * @param Fortrabbit\Beelzebub\Deamon &$daemon The paren daemon
     *
     * @return bool
     */
    public function runStartup(DaemonInterface &$daemon)
    {
        if ($this->startup) {
            return call_user_func_array($this->startup, array($this, $daemon));
        } else {
            return null;
        }
    }

    /**
     * Getter for name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Getter for interval
     *
     * @return int
     */
    public function getInterval()
    {
        return $this->interval;
    }

    /**
     * Setter for interval
     *
     * @param int $interval New interval in seconds
     */
    public function setInterval($interval)
    {
        $this->interval = $interval;
    }

    /**
     * Getter for amount
     *
     * @return int
     */
    public function getAmount()
    {
        return $this->amount;
    }

    /**
     * Setter for amount
     *
     * @param int $amount New amount
     */
    public function setAmount($amount)
    {
        return $this->amount = $amount;
    }

}
