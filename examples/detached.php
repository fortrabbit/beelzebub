<?php

require_once __DIR__. '/../vendor/autoload.php';

use Fortrabbit\Beelzebub\Daemon;
use Fortrabbit\Beelzebub\DefaultDaemon;
use Fortrabbit\Beelzebub\Worker;

print "Example shows a detaching daemon with a single worker printing out something to the logs about every second\n";

if (count($argv) !== 3) {
    die("Usage: $argv[0] <start|stop> <pid-file>");
}

$daemon = new DefaultDaemon("simple", "1.0.0");
$daemon->registerWorker(array(
    'hello-world' => array(
        'loop' => function (Worker &$worker, Daemon &$daemon) {
            $daemon->getLogger()->addInfo("Logging from client every second");
        },
        'interval' => 1
    )
));

$mode    = $argv[1];
$pidfile = $argv[2];

if (file_exists($pidfile)) {
    $pid = file_get_contents($pidfile);
    $running = posix_kill($pid, 0);
    print "Read PID $pid from $pidfile. Is running: $running\n";
    #exit;
    if ($running) {
        if ($mode == 'start') {
            die("Cannot start. Found running process with pid $pid.");
        } else {
            if ($daemon->stopDetached($pidfile)) {
                print "Killed running process with pid $pid.";
                unlink($pidfile);
                exit;
            } else {
                die("Failed to kill running process with $pid.");
            }
        }
    } else {
        if ($mode == 'stop') {
            unlink($pidfile);
            die("Process not running, cleaning up pid file");
        } else {
            print "Cleaning up obsolete pid file\n";
            unlink($pidfile);
        }
    }
} else if ($mode == 'stop') {
    die("Nothing to stop, $pidfile not existing");
}



print "Daemon will now detach from shell. You can find it's PID in $pidfile and see it's output in ". __DIR__. "/out.log\n\n";

$daemon->setLogfile(__DIR__. "/out.log");
if ($pid = $daemon->runDetached($pidfile)) {
    print "Process started with $pid\n";
}