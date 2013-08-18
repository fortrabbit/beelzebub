<?php

require_once __DIR__. '/../vendor/autoload.php';

print "Client will print out a log message about every second\n";
print " Press Ctrl+c to exit\n\n";

$builder = new Beelzebub\Daemon\Builder();
$daemon = $builder
    ->setLogger(new \Monolog\Logger(new \Monolog\Handler\StreamHandler('php://stderr')))
    ->addWorker('hello-world', array(
        'interval' => 1,
        'loop'     => function (Beelzebub\Worker $w) {
            $w->getDaemon()->getLogger()->info("Hello world");
        }
    ))
    ->build();
$daemon->run();