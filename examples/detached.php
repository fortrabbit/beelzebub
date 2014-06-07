<?php

require_once __DIR__. '/../vendor/autoload.php';

if ($argc !== 2 || !in_array($argv[1], ['start', 'stop'])) {
    die("Usage: $argv[0] <start|stop>");
}

$pidFile = '/tmp/daemon.pid';
$logFile = '/tmp/daemon.log';

echo "Usage: $argv[0] <start|stop>\n";
echo " In start mode daemon will detach from shell\n";
echo " Log file: $logFile\n";
echo " Pid file: $pidFile\n\n";


$logger = new \Monolog\Logger('simple-daemon', [
    new \Monolog\Handler\StreamHandler($logFile)
]);
$daemon = new Frbit\Beelzebub\Daemon('simple-daemon', null, $logger);
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


if ($argc > 1 && $argv[1] === 'stop') {
    $daemon->halt($pidFile, true);
} else {
    $pid = $daemon->runDetached($pidFile);
    echo "Started daemon with pid $pid\n";
}