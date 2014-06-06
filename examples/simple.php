<?php

require_once __DIR__. '/../vendor/autoload.php';

echo "Client will print out a log message about every second\n";
echo " Press Ctrl+c to exit\n\n";

$daemon = new Frbit\Beelzebub\Daemon('foo');
$daemon->addWorker(new \Frbit\Beelzebub\Worker\CallableWorker(
    'worker1',
    function () {
        error_log("Working");
    },
    5
));
$daemon->addWorker(new \Frbit\Beelzebub\Worker\CallableWorker(
    'worker2',
    function () {
        sleep(2);
        die("Foo");
    },
    5
));
$daemon->setShutdownSignal(SIGINT);
$daemon->run();