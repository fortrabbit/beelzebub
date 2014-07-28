<?php


namespace Frbit\Beelzebub\Helper;


/**
 * Class BuiltInDouble
 *
 * @method bool   file_exists(string $path)
 * @method bool   time_sleep_until(float $timestamp)
 * @method int    pcntl_fork()
 * @method int    posix_setsid()
 * @method string file_get_contents(string $path)
 * @method void   chroot(string $directory)
 * @method void   die(string $message)
 * @method void   file_put_contents(string $path, string $content)
 * @method void   pcntl_exec(\string $path, array $args = null, array $envs = null)
 * @method void   pcntl_signal(int $signal, mixed $callback)
 * @method void   posix_kill(int $pid, int $signal)
 * @method void   posix_setgid(int $gid)
 * @method void   posix_setuid(int $uid)
 * @method void   sleep(int $time)
 * @method void   unlink(string $path)
 * @method void   usleep(int $micro_seconds)
 *
 * @package Frbit\Beelzebub\Helper
 **/
class BuiltInDouble
{

    /**
     * @var bool
     */
    protected $mockEnabled;

    /**
     * Class constructor
     */
    public function __construct($mockEnabled = false)
    {
        $this->mockEnabled = $mockEnabled;
    }

    public function mock($enable = true)
    {
        $this->mockEnabled = $enable;
    }

    /**
     * @param string $name
     * @param array  $arguments
     *
     * @return mixed
     */
    function __call($name, $arguments)
    {
        if (!function_exists($name)) {
            throw new \BadFunctionCallException("Built-in function \"$name\" does not exist");
        }

        if (!$this->mockEnabled) {
            return call_user_func_array($name, $arguments);
        }
    }


}