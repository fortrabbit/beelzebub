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
     * Wraps usleep() method
     *
     * @param int $microseconds
     */
    public static function doUsleep($microseconds)
    {
        usleep($microseconds);
    }

}