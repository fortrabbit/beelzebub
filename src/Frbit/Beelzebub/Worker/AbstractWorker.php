<?php


namespace Frbit\Beelzebub\Worker;

use Frbit\Beelzebub\Daemon;
use Frbit\Beelzebub\Worker;

/**
 * Class AbstractWorker
 * @package Frbit\Beelzebub\Worker
 **/
abstract class AbstractWorker implements Worker
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
     * The loop interval
     *
     * @var int
     */
    protected $interval;

    /**
     * Amount of parallel loops
     *
     * @var int
     */
    protected $amount;

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
    public function setInterval($interval)
    {
        $this->interval = $interval;
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
        $this->amount = $amount;
    }

    /**
     * {@inheritdoc}
     */
    public function getAmount()
    {
        return $this->amount;
    }


}