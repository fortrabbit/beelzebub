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
use Beelzebub\Worker;

/**
 * Base class for Worker
 *
 * @author Ulrich Kautz <uk@fortrabbit.com>
 */

class DefaultWorker implements Worker
{

    /**
     * @var Daemon
     */
    protected $daemon;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var int
     */
    protected $interval;

    /**
     * @var \Closure
     */
    protected $loop;

    /**
     * @var \Closure
     */
    protected $startup;

    /**
     * @var array
     */
    protected $pids;

    /**
     * {@inheritdoc}
     */
    public function __construct($name, \Closure $loop, $interval = 1, \Closure $startup = null)
    {
        $this->name     = $name;
        $this->loop     = $loop;
        $this->interval = $interval;
        $this->startup  = $startup;
    }

    /**
     * {@inheritdoc}
     */
    public function runLoop(array $args = array())
    {
        $callArgs = array($this);
        if ($args) {
            $callArgs[] = $args;
        }
        call_user_func_array($this->loop, $callArgs);
    }

    /**
     * {@inheritdoc}
     */
    public function hasStartup()
    {
        return $this->startup ? true : false;
    }

    /**
     * {@inheritdoc}
     */
    public function runStartup()
    {
        if ($this->startup) {
            return call_user_func_array($this->startup, array($this));
        } else {
            return null;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setDaemon(Daemon $daemon)
    {
        $this->daemon = $daemon;
    }

    /**
     * {@inheritdoc}
     */
    public function getDaemon()
    {
        return $this->daemon;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * {@inheritdoc}
     */
    public function getInterval()
    {
        return $this->interval;
    }

    /**
     * {@inheritdoc}
     */
    public function setAmount($amount)
    {
        return $this->amount = $amount;
    }

}
