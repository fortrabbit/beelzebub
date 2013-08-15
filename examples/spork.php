<?php
/**
 * This class is part of Beelzebub
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Spork\Fork;
use Spork\ProcessManager;


declare(ticks = 1)


foo();


while (true) {
    echo "Main wait\n";
    sleep(1);
}

function foo() {

    $spork = new ProcessManager();

    echo "Before fork\n";
    $fork = $spork->fork(function () {
        pcntl_signal(SIGTERM, SIG_IGN);
        $pid = getmypid();
        error_log("New in fork $pid");
        while (true) {
            error_log("Looping in $pid");
            sleep(1);
        }
    });


    echo "Wait for it ";
    sleep(1);
    echo "Kill!\n";
    $fork->kill(SIGTERM);
    sleep(1);
    echo "Kill AGAIN!\n";
    $fork->kill(SIGKILL);
    //pcntl_waitpid($fork->getPid(), $status, WNOHANG | WUNTRACED);
    //$fork->wait(true);
    //$fork->processWaitStatus($status);
}