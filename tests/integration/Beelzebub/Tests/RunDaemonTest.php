<?php
/**
 * This class is part of Beelzebub
 */

namespace Beelzebub\Tests;

use Beelzebub\Daemon;
use Beelzebub\DefaultDaemon;
use Beelzebub\DefaultWorker;
use Beelzebub\Worker;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use PHPUnit_Framework_TestCase;
use Spork\ProcessManager;
use Symfony\Component\EventDispatcher\EventDispatcher;

class RunDaemonTest extends PHPUnit_Framework_TestCase
{

    public function testRunningDaemon()
    {
        $manager    = new ProcessManager();
        $logHandler = new StreamHandler("php://stderr");
        $logger     = new Logger("test", array($logHandler));
        $dispatcher = new EventDispatcher();
        $daemon     = new DefaultDaemon($manager, $logger, $dispatcher);
        $worker1    = new DefaultWorker('worker1', function (Worker $w) use ($logger) {
            pcntl_signal(SIGQUIT, SIG_IGN);
            pcntl_signal(SIGINT, SIG_IGN);
            error_log("CALLED FROM WORKER 1");
        });
        $worker2    = new DefaultWorker('worker2', function (Worker $w) use ($logger) {
            error_log("CALLED FROM WORKER 2");
        });
        $daemon->addWorker($worker1);
        $daemon->addWorker($worker2);
        $daemon->loop();
    }

}