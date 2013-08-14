<?php
/**
 * This class is part of Beelzebub
 */

namespace Fortrabbit\Beelzebub\Wrapper;


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
}