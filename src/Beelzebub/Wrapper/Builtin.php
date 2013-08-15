<?php
/**
 * This class is part of Beelzebub
 */

namespace Beelzebub\Wrapper;

/**
 * Wrapper class for some built-in methods which shall be mocked
 *
 * @package Beelzebub\Wrapper
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

    /**
     * Wraps usleep() method
     *
     * @param int $nanoseconds
     */
    public static function doUsleep($nanoseconds)
    {
        usleep($nanoseconds);
    }

}