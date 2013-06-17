<?php

namespace Fortrabbit\Beelzebub\Test;

use Fortrabbit\Beelzebub\Daemon;
use Monolog\Logger;
use Monolog\Handler\TestHandler;

class DummyDaemon extends Daemon
{
    private $loggerHandler;

    public function __construct($name = 'dummy', $version = '0.0.1')
    {
        parent::__construct($name, $version);
        $logger = new Logger("dummy");
        $this->loggerHandler = new TestHandler();
        $logger->pushHandler($this->loggerHandler);
        $this->setLogger($logger);
    }

    public function getLoggerHandler()
    {
        return $this->loggerHandler;
    }
}