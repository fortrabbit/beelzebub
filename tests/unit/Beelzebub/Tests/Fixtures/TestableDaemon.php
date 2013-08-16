<?php
/**
 * This class is part of Beelzebub
 */

namespace Beelzebub\Tests\Fixtures;

use Beelzebub\Daemon\Standard;

class TestableDaemon extends Standard
{

    public function stop()
    {
        $this->stopped = true;
    }

    public function getShutdownTimeout()
    {
        return $this->shutdownTimeout;
    }

    public function getShutdownSignal()
    {
        return $this->shutdownSignal;
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

}