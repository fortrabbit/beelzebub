<?php
/**
 * This class is part of Beelzebub
 */

namespace Fortrabbit\Beelzebub;


class WorkerList
{
    /**
     * @var array
     */
    protected $workers;
    /**
     * @var array
     */
    protected $pids;

    public function __construct()
    {
        $this->workers = array();
        $this->pids    = array();
    }

    /**
     * Add pid to pid list .. called from Daemon
     *
     * @param int $pid
     */
    public function addWorker(Worker &$worker)
    {
        $this->workers[$worker->getName()] = & $worker;
    }

    /**
     * Add pid to pid list .. called from Daemon
     *
     * @param int $pid
     */
    public function addPid(Worker $worker, $pid)
    {


    }

    /**
     * Remove pid from list .. called from Daemon
     *
     * @param int $pid
     */
    public function removePid($pid);

    /**
     * Returns all pids
     *
     * @return array
     */
    public function getPids();

    /**
     * Returns amount of running processes
     *
     * @return int
     */
    public function countRunning();

}