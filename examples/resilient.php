<?php

require_once __DIR__. '/../vendor/autoload.php';

use Fortrabbit\Beelzebub\Daemon;
use Fortrabbit\Beelzebub\DefaultDaemon;
use Fortrabbit\Beelzebub\Worker;

print "Worker will print out about every second a log message\n";
print "Once killed (Ctrl+c), daemon will wait 5 seconds and then nuke (SIGKILL) the worker\n";
print " [Press Ctrl+c to exit]\n\n";

$daemon = new DefaultDaemon("simple", "1.0.0");
$daemon->setShutdownTimeout(5);
$daemon->registerWorker(array(
    'resilient' => array(
        'interval'=> 1,
        'loop' => function (Worker $worker, Daemon $daemon) {
            $daemon->getLogger()->addInfo("Logging from client every second");
        },
        'startup' => function (Worker $worker, Daemon $daemon) {
            $daemon->getLogger()->addInfo("Calling startup in ". $worker->getName());
            pcntl_signal(SIGTERM, function() use ($daemon) {
                $daemon->getLogger()->addInfo("RECEIVED TERM IN CLIENT");
                sleep(10);
            });
            pcntl_signal(SIGINT, function() use ($daemon) {
                $daemon->getLogger()->addInfo("RECEIVED INT IN CLIENT");
                sleep(10);
            });
            pcntl_signal(SIGQUIT, function() use ($daemon) {
                $daemon->getLogger()->addInfo("RECEIVED QUIT IN CLIENT");
                sleep(10);
            });
        }
    )
));

$daemon->run();