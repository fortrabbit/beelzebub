<?php
/**
 * This class is part of Beelzebub
 */

namespace Beelzebub;


use Spork\Fork;

class WorkerForks
{
    /**
     * @var Worker
     */
    protected $worker;

    /**
     * @var Fork[]
     */
    protected $forks;

    /**
     * Create new WorkerForks container
     *
     * @param Worker $worker
     */
    public function __construct(Worker $worker)
    {
        $this->worker = $worker;
        $this->forks  = array();
    }

    /**
     * @return Fork[]
     */
    public function getForks()
    {
        return array_values($this->forks);
    }

    /**
     * Add new fork of worker
     *
     * @param Fork $fork
     */
    public function addFork(Fork $fork)
    {
        $this->forks[$fork->getPid()] = $fork;
    }

    /**
     * Remove a fork from list
     *
     * @param Fork $fork
     */
    public function removeFork(Fork $fork)
    {
        $pid = $fork->getPid();
        if (isset($this->forks[$pid])) {
            unset($this->forks[$pid]);
        }
    }

    /**
     * @return Worker
     */
    public function getWorker()
    {
        return $this->worker;
    }

    /**
     * Clearup stopped forks from list
     */
    public function clearStopped()
    {
        $forks = [];
        foreach ($this->getForks() as $fork) {
            if (!$fork->isExited()) {
                $forks[$fork->getPid()] = $fork;
            }
        }
        $this->forks = $forks;
    }

    /**
     * Proxy method for worker's name
     *
     * @return string
     */
    public function getName()
    {
        return $this->worker->getName();
    }

    /**
     * Send shutdown to all forks
     *
     * @param int $signo
     */
    public function shutdownAll($signo = SIGQUIT)
    {
        foreach ($this->getForks() as $fork) {
            error_log("SENDING SIG TO $signo");
            $fork->kill($signo);
            error_log("AFTER KILL SIG TO $signo");
            /*pcntl_waitpid($fork->getPid(), $status, WNOHANG | WUNTRACED);
            error_log("AFTER WAITPID $signo");
            $fork->processWaitStatus($status);
            error_log("AFTER ALL $signo");*/
        }
    }

    /**
     * Amount of missing forks
     *
     * @return int
     */
    public function countRunning()
    {
        return count($this->forks);
    }

    /**
     * Amount of missing forks
     *
     * @return int
     */
    public function countMissing()
    {
        return $this->worker->getAmount() - $this->countRunning();
    }

}