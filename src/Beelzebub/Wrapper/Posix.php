<?php
/**
 * This class is part of Beelzebub
 */

namespace Beelzebub\Wrapper;


/**
 * Wrapper class for used posix_* calls so can be mocked
 *
 * @package Fortrabbit\Beelzebub
 */
class Posix
{

    /**
     * Call posix_kill
     *
     * @param int $pid
     * @param int $signo
     *
     * @return bool
     */
    public static function kill($pid, $signo)
    {
        return posix_kill($pid, $signo);
    }

    /**
     * Call posix_setsid
     *
     * @return int
     */
    public static function setsid()
    {
        return posix_setsid();
    }
}