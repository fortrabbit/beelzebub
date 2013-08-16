<?php
/**
 * This class is part of Beelzebub
 */

namespace Beelzebub\Worker;

/**
 * Implements builder for worker
 *
 * @package Beelzebub
 */
class Builder
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $class;

    /**
     * @var callable
     */
    protected $loop;

    /**
     * @var callable
     */
    protected $startup;

    /**
     * @var int
     */
    protected $interval;

    /**
     * @var int
     */
    protected $amount;

    /**
     * Start building worker
     *
     * @param string   $name
     * @param string   $className
     * @param callable $loop
     * @param int      $amount
     * @param callable $startup
     * @param int      $interval
     */
    public function __construct($name = null, $className = null, $loop = null, $amount = 1, $startup = null, $interval = null)
    {
        if ($name) {
            $this->name = $name;
        }
        if ($className) {
            $this->class = $className;
        }
        if ($loop) {
            $this->loop = $loop;
        }
        if ($amount) {
            $this->amount = $amount;
        } else {
            $this->amount = 1;
        }
        if ($startup) {
            $this->startup = $startup;
        }
        if ($interval) {
            $this->interval = $interval;
        } else {
            $this->interval = 1;
        }
    }


    /**
     * @param string $name
     *
     * @return Builder
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }


    /**
     * @param string $class
     *
     * @return Builder
     */
    public function setClass($class)
    {
        $this->class = $class;

        return $this;
    }

    /**
     * @param int $amount
     *
     * @return Builder
     */
    public function setAmount($amount)
    {
        $this->amount = $amount;

        return $this;
    }

    /**
     * @param int $interval
     *
     * @return Builder
     */
    public function setInterval($interval)
    {
        $this->interval = $interval;

        return $this;
    }

    /**
     * @param callable $loop
     *
     * @return Builder
     */
    public function setLoop($loop)
    {
        $this->loop = $loop;

        return $this;
    }

    /**
     * @param callable $startup
     *
     * @return Builder
     */
    public function setStartup($startup)
    {
        $this->startup = $startup;

        return $this;
    }

    /**
     * Generate the new worker
     *
     * @return mixed
     *
     * @throws \BadMethodCallException
     */
    public function generate()
    {
        if (!$this->class) {
            $this->class = '\\Beelzebub\\Worker\\Standard';
        }
        if (!class_exists($this->class)) {
            throw new \BadMethodCallException("Worker class '$this->class' not found!");
        }
        if (!$this->name) {
            throw new \BadMethodCallException("Worker needs name!");
        }
        if (!$this->loop) {
            throw new \BadMethodCallException("Worker needs loop!");
        }
        $class = $this->class;

        return new $class($this->name, $this->loop, $this->interval, $this->startup, $this->amount);
    }


}