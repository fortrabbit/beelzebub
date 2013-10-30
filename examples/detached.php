<?php

require_once __DIR__ . '/../vendor/autoload.php';


$opts = parseArgs($argv);

if (isset($opts['help']) || !isset($opts['pid'])) {
    echo "Usage: $argv[0] [help] [log:<path-to-log-file>] [stop] pid:<path-to-pid-file>\n";
    exit(0);
}

$logHandler = isset($opts['log'])
    ? new \Monolog\Handler\StreamHandler($opts['log'])
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
$pidfile = new \Beelzebub\Wrapper\File($opts['pid']);

// stop
if (isset($opts['stop'])) {
    print "Stopping running daemon: ";
    print ($daemon->halt($pidfile) ? "OK" : "FAIL"). "\n";
} else {
    print "Starting detached daemon: ";
    try {
        $pid = $daemon->runDetached($pidfile);
        print " [pid: $pid, file: {$opts['pid']}]\n";
    } catch (\Exception $e) {
        print " FAIL: ". $e->getMessage(). "\n";
    }
}

function parseArgs(array $argv)
{
    $opts = [];
    foreach (array_splice($argv, 1) as $arg) {
        if (preg_match('/^(.+?):(.+)$/', $arg, $match)) {
            $opts[$match[1]] = $match[2];
        } else {
            $opts[$arg] = true;
        }
    }
    return $opts;
}