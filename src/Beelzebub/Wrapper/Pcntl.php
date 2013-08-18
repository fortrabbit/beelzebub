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

    /**
     * Call pcntl_waitpid
     *
     * @param int $pid
     * @param int &$shared
     * @param int $flags
     *
     * @return int
     */
    public static function waitpid($pid, &$shared, $flags)
    {
        return pcntl_waitpid($pid, $shared, $flags);
    }
}