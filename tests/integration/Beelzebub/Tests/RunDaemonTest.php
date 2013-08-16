<?php
/**
 * This class is part of Beelzebub
 */

namespace Beelzebub\Tests;

use Beelzebub\Daemon\Builder;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class RunDaemonTest extends \PHPUnit_Framework_TestCase
{

    public function testRunningDaemon()
    {
        $handler = new StreamHandler('php://stderr');
        $builder = new Builder(array(
            function() {
                pcntl_signal(SIGQUIT, SIG_IGN);
                pcntl_signal(SIGINT, SIG_IGN);
                error_log("CALLED FROM WORKER 1: ". getmypid());
            },
            array(
                'loop' => function () {
                    error_log("CALLED FROM WORKER 2");
                }
            )
        ));
        $builder
            ->setLogger(new Logger('test', array($handler)))
            ->setShutdownTimeout(3);
        $daemon = $builder->build();
        $daemon->run();
    }

}