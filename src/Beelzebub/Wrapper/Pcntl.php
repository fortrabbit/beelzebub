<?php
/**
 * This class is part of Beelzebub
 */

namespace Beelzebub\Wrapper;


/**
 * Wrapper class for used pcntl_* calls so can be mocked
 *
 * @package Fortrabbit\Beelzebub
 */
class Pcntl
{

    /**
     * Call pcntl_signal
     *
     * @param int   $signo
     * @param mixed $handler
     *
     * @return bool
     */
    public static function signal($signo, $handler)
    {
        return pcntl_signal($signo, $handler);
    }
}