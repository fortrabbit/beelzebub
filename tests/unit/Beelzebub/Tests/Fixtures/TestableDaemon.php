<?php
/**
 * This class is part of Beelzebub
 */

namespace Beelzebub\Tests\Fixtures;

use Beelzebub\Daemon\Standard;
use Beelzebub\Worker;
use Spork\Fork;

class TestableDaemon extends Standard
{

    public function stop()
    {
        $this->stopped = true;
    }

    public function isStopped()
    {
        return $this->stopped;
    }

    public function getWorkers()
    {
        return $this->workers;
    }

    public function getForks()
    {
        return $this->forks;
    }

    /**
     * @param string $name
     * @param Fork[] $forks
     *
     * @return array
     */
    public function setForks($name, array $forks)
    {
        $this->forks[$name] = $forks;
    }

    public function setCurrentWorker(Worker $worker)
    {
        $this->currentWorker = $worker;
    }

}