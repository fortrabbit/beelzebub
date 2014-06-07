<?php


namespace Frbit\Beelzebub\Helper;


/**
 * Class BuiltInDouble
 *
 * @method void chroot(string $directory)
 * @method bool file_exists(string $path)
 * @method string file_get_contents(string $path)
 * @method void file_put_contents(string $path, string $content)
 * @method int pcntl_fork()
 * @method void pcntl_signal(int $signal, mixed $callback)
 * @method void posix_kill(int $pid, int $signal)
 * @method int posix_setsid()
 * @method void posix_setgid(int $gid)
 * @method void posix_setuid(int $uid)
 * @method void sleep(int $time)
 * @method void unlink(string $path)
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