<?php


namespace Frbit\Beelzebub\Sleeper;

use Frbit\Beelzebub\Helper\BuiltInDouble;
use Frbit\Beelzebub\Sleeper;


/**
 * Sleeper which does sleep a random amount +/- X% around given time
 *
 * @package Frbit\Beelzebub\Timer
 **/
class FuzzySleeper implements Sleeper
{
    /**
     * @var \Frbit\Beelzebub\Helper\BuiltInDouble
     */
    protected $builtIn;

    /**
     * @var float
     */
    protected $randomFactor;

    /**
     * Class constructor
     *
     * @param float         $randomFactor
     * @param BuiltInDouble $builtInDouble
     */
    public function __construct($randomFactor = 0.5, BuiltInDouble $builtInDouble = null)
    {
        $this->builtIn      = $builtInDouble ?: new BuiltInDouble();
        $this->randomFactor = $randomFactor;
    }

    /**
     * {@inheritdoc}
     */
    public function sleep($time)
    {
        $sleepTime = $this->randomFactor * $time;
        $sleepTime = $sleepTime + (mt_rand($sleepTime * 100, ($time + $sleepTime) * 100) / 100);
        $this->builtIn->usleep($sleepTime * 1000000);
    }
}