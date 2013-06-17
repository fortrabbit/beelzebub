<?php

require_once __DIR__. '/../vendor/autoload.php';

use Fortrabbit\Beelzebub\DaemonInterface;
use Fortrabbit\Beelzebub\Daemon;
use Fortrabbit\Beelzebub\WorkerInterface;

print "Client will print out a log message about every second\n";
print "If SIGUSR1 is received (pid: ". getmypid(). "), the default hello-world worker is stopped and removed and another new worker registered.\n";
print " [Press Ctrl+c to exit]\n\n";

$daemon = new Daemon("simple", "1.0.0");
$daemon->setRestartSignal(SIGUSR1);
$daemon->setRestartHandler(function (Daemon &$d) {
    $d->getLogger()->info("After worker shutdowns, I am called");
    if ($d->unregisterWorker('hello-world')) {
        $d->registerWorker(array(
            'hello-world-2' => array(
                'loop' => function (WorkerInterface &$worker, DaemonInterface &$daemon) {
                    $daemon->getLogger()->addInfo("I am the new worker");
                },
                'interval' => 1
            )
        ));
    }
});
$daemon->registerWorker(array(
    'hello-world' => array(
        'loop' => function (WorkerInterface &$worker, DaemonInterface &$daemon) {
            $daemon->getLogger()->addInfo("I am the old worker");
        },
        'interval' => 1
    )
));

$daemon->run();