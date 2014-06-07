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
    5,
    function () {
        error_log("startup");
        pcntl_signal(SIGINT, function (){
            error_log("Not reacting on SIGINT");
        });
        pcntl_signal(SIGQUIT, function () {
            error_log("Not reacting on SIGQUIT");
        });
        pcntl_signal(SIGTERM, function (){
            error_log("Not reacting on SIGTERM");
        });
    }
));
$daemon->setShutdownTimeout(5);
$daemon->setShutdownSignal(SIGINT);
$daemon->run();