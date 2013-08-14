<?php

require_once __DIR__. '/../vendor/autoload.php';

use Fortrabbit\Beelzebub\Daemon;
use Fortrabbit\Beelzebub\DefaultDaemon;
use Fortrabbit\Beelzebub\Worker;

print "Client will print out a log message about every second\n";
print " [Press Ctrl+c to exit]\n\n";

$daemon = new DefaultDaemon("simple", "1.0.0");
$daemon->registerWorker(array(
    'hello-world' => array(
        'loop' => function (Worker &$worker, Daemon &$daemon) {
            $daemon->getLogger()->addInfo("Logging from client every second");
        },
        'interval' => 1
    )
));

$daemon->run();