<?php

require_once __DIR__. '/../vendor/autoload.php';

echo "Client will print out a log message about every second\n";
echo " Press Ctrl+c to exit\n\n";

$daemon = new Frbit\Beelzebub\Daemon('simple-daemon');
$daemon->addWorker(new \Frbit\Beelzebub\Worker\CallableWorker(
    'simple-worker1',
    function () {
        error_log("Working");
    },
    5
));
$daemon->addWorker(new \Frbit\Beelzebub\Worker\CallableWorker(
    'simple-worker2',
    function () {
        sleep(2);
        die("Foo");
    },
    5
));
$daemon->run();