<?php


namespace Frbit\Beelzebub\Sleeper;

use Frbit\Beelzebub\Helper\BuiltInDouble;
use Frbit\Beelzebub\Sleeper;


/**
 * Sleeper which does sleep the exact amount it is given
 *
 * @package Frbit\Beelzebub\Timer
 **/
class RealSleeper implements Sleeper
{
    /**
     * @var \Frbit\Beelzebub\Helper\BuiltInDouble
     */
    protected $builtIn;

    /**
     * Class constructor
     *
     * @param BuiltInDouble $builtInDouble
     */
    public function __construct(BuiltInDouble $builtInDouble = null)
    {
        $this->builtIn = $builtInDouble ?: new BuiltInDouble();
    }

    /**
     * {@inheritdoc}
     */
    public function sleep($time)
    {
        $this->builtIn->sleep($time);
    }
}