<?php
/**
 * This class is part of Beelzebub
 */

namespace Fortrabbit\Beelzebub\Wrapper;

/**
 * Wrapper class for some built-in methods which shall be mocked
 *
 * @package Fortrabbit\Beelzebub\Wrapper
 */
class Builtin
{

    /**
     * Wraps exit() method
     */
    public static function doExit()
    {
        exit();
    }

    /**
     * Wraps sleep() method
     *
     * @param int $seconds
     */
    public static function doSleep($seconds)
    {
        sleep($seconds);
    }

}