<?php

require_once __DIR__. '/../vendor/autoload.php';

echo "Client will print out a log message about every second\n";
echo " Press Ctrl+c to exit -> it will take 3-4 seconds\n\n";

$builder = new Beelzebub\Daemon\Builder();
$daemon = $builder
    ->setLogger(new \Monolog\Logger('ResilientExample', array(new \Monolog\Handler\StreamHandler('php://stderr'))))
    ->addWorker('hello-world', array(
        'interval' => 1,
        'startup'  => function () {
            pcntl_signal(SIGINT, function (){
                error_log("Not reacting on SIGINT");
            });
            pcntl_signal(SIGQUIT, function () {
                error_log("Not reacting on SIGQUIT");
            });
        },
        'loop'     => function (Beelzebub\Worker $w) {
            $w->getDaemon()->getLogger()->info("Hello world");
        }
    ))
    ->setShutdownTimeout(3)
    ->build();
$daemon->run();