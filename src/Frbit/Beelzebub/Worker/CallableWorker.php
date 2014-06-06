<?php


namespace Frbit\Beelzebub\Worker;

use Frbit\Beelzebub\Daemon;
use Frbit\Beelzebub\Worker;

/**
 * Worker based on callable or closure
 *
 * @package Frbit\Beelzebub\Worker
 **/
class CallableWorker extends AbstractWorker
{

    /**
     * The loop callback
     *
     * @var callable
     */
    protected $loop;

    /**
     * The startup callback
     *
     * @var callable
     */
    protected $startup;


    /**
     * @param string   $name
     * @param callable $loop
     * @param int      $interval
     * @param null     $startup
     * @param int      $amount
     */
    public function __construct($name, $loop, $interval = self::DEFAULT_INTERVAL, $startup = null, $amount = self::DEFAULT_AMOUNT)
    {
        // for < 5.4, we cannot use type hints
        if (!is_callable($loop)) {
            throw new \BadMethodCallException("Loop needs to be callable");
        }
        if (!is_null($startup) && !is_callable($startup)) {
            throw new \BadMethodCallException("Startup needs to be callable");
        }
        $this->name     = $name;
        $this->loop     = $loop;
        $this->interval = $interval ? : self::DEFAULT_INTERVAL;
        $this->startup  = $startup;
        $this->amount   = $amount ? : self::DEFAULT_AMOUNT;
    }

    /**
     * {@inheritdoc}
     */
    public function run(array $args = array())
    {
        $callArgs = array($this, $args);
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

}