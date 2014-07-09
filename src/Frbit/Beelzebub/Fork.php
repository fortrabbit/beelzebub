<?php


namespace Frbit\Beelzebub;

use Frbit\System\UnixProcess\Manager;


/**
 * Class Fork
 * @package Frbit\Beelzebub
 **/
class Fork
{
    /**
     * @var bool
     */
    protected $parent;

    /**
     * @var int
     */
    protected $pid;

    /**
     * Class constructor
     *
     * @param callable $callback
     * @param Manager  $manager
     */
    public function __construct(callable $callback, Manager $manager = null)
    {
        $pid = pcntl_fork();
        if (is_null($pid)) {
            throw new \RuntimeException("Failed to fork");
        } elseif ($pid) {
            $this->parent  = true;
            $this->pid     = $pid;
            $this->manager = $manager ?: new Manager();
        } else {
            $this->parent = false;
            $this->pid    = getmypid();
            $callback();
            exit();
        }
    }

    public function isExited()
    {
        if (!$this->parent) {
            return false;
        }
        pcntl_waitpid($this->pid, $status, WNOHANG | WUNTRACED); // reap
        return $this->manager->getByPid($this->pid) ? false : true;
    }

    /**
     * @return boolean
     */
    public function isParent()
    {
        return $this->parent;
    }

    /**
     * @return int
     */
    public function getPid()
    {
        return $this->pid;
    }

    public function kill($signo)
    {
        if (!$this->parent) {
            exit();
        } else {
            return posix_kill($this->pid, $signo);
        }
    }

}