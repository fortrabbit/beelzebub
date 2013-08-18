<?php

require_once __DIR__ . '/../vendor/autoload.php';


$opts = getopt("p:l::hs", array(
    "pidfile:",
    "logfile::",
    "help",
    "stop"
));

if (!isset($opts['l']) || isset($opts['h'])) {
    print <<<USAGE
Usage: $argv[0] -p=<pidfile> [-l=<logfile>] [-s] [-h]

    -p=<pidfile> | --pidfile=<pidfile> [REQUIRED]
        File containing PID

    -l=<logfile> | --logfile=<logfile> [optional]
        Output logging, otherwise none

    -s | --stop
        Do not start but stop, using PID from file

    -h | --help
        Show this help

USAGE;
    exit(0);
}

$logHandler = isset($opts['l'])
    ? new \Monolog\Handler\StreamHandler($opts['l'])
    : new \Monolog\Handler\NullHandler();
$builder    = new Beelzebub\Daemon\Builder();
$daemon     = $builder
    ->setLogger(new \Monolog\Logger($logHandler))
    ->addWorker('hello-world', array(
        'interval' => 1,
        'loop'     => function (Beelzebub\Worker $w) {
            $w->getDaemon()->getLogger()->info("Hello world");
        }
    ))
    ->build();
$pidfile = new \Beelzebub\Wrapper\File($opts['p']);

// stop
if (isset($opts['s'])) {
    print "Stopping running daemon: ";
    print ($daemon->halt($pidfile) ? "OK" : "FAIL"). "\n";
} else {
    print "Starting detached daemon: ";
    try {
        $pid = $daemon->runDetached($pidfile);
        print " [pid: $pid, file: {$opts['p']}]\n";
    } catch (\Exception $e) {
        print " FAIL: ". $e->getMessage(). "\n";
    }
}