<?php


namespace Frbit\Beelzebub;



/**
 * Interface Sleeper
 * @package Frbit\Beelzebub
 **/
interface Sleeper
{
    /**
     * Sleep for given length
     *
     * @param int $time
     */
    public function sleep($time);
}