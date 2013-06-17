<?php

require_once __DIR__. '/../vendor/autoload.php';

use Fortrabbit\Beelzebub\DaemonInterface;
use Fortrabbit\Beelzebub\Daemon;
use Fortrabbit\Beelzebub\WorkerInterface;

print "Client will print out a log message about every second\n";
print " [Press Ctrl+c to exit]\n\n";

$daemon = new Daemon("simple", "1.0.0");
$daemon->registerWorker(array(
    'hello-world' => array(
        'loop' => function (WorkerInterface &$worker, DaemonInterface &$daemon) {
            $daemon->getLogger()->addInfo("Logging from client every second");
        },
        'interval' => 1
    )
));

$daemon->run();