# About

Beelzebub is a PHP framework for writing (forked) multi process daemons. It provides a highly configurable process manager, an extend-able logging and a simple programming interface.

If you are looking for a single process daemon framework, have a look at [clio](https://github.com/nramenta/clio).

# Example

    <?php

    require_once 'vendor/autoload.php';

    use Fortrabbit\Beelzebub\DaemonInterface;
    use Fortrabbit\Beelzebub\Daemon;
    use Fortrabbit\Beelzebub\WorkerInterface;


    $daemon = new Daemon("simple", "1.0.0");
    $daemon->registerWorker(array(
        'do-something' => array(
            'loop' => function (WorkerInterface &$w, DaemonInterface &$d) {
                $d->getLogger()->info("Doing something");
            },
            'interval' => 30
        ),
        'do-something-else' => array(
            'startup' => function(WorkerInterface &$worker, DaemonInterface &$d) {
                $d->getLogger()->info("Staring up something else");
            },
            'loop' => function (WorkerInterface &$w, DaemonInterface &$d) {
                $d->getLogger()->info("Doing something else");
            },
            'interval' => 5,

        )
    ));

    $daemon->run();

# Installation

    $ php composer.phar install "fortrabbit/beelzebub" "@dev"

# Usage

## Create

    <?php

    // create a new daemon instance with name and version
    $daemon = new Daemon("simple", "1.0.0");

## Register worker

    <?php

    // register worker by name
    $daemon->registerWorker(array(

        // unique name of the worker
        'worker-name' => array(

            // opt: startup method, called before first loop
            'startup' => function (WorkerInterface &$w, DaemonInterface &$d) {
                // ..
            },

            // req: the loop method, called every [interval]
            'loop' => function (WorkerInterface &$w, DaemonInterface &$d) {
                // ..
            },

            // req: the interval between calling the loop in seconds
            'interval' => 30,

            // opt: amount of instances (default: 1)
            'amount' => 2
        ),
        // .. other workers
    );

    // de-register a named worker
    $daemon->unregisterWorker('worker-name');

## Shutdown behavior

    <?php

    // set shutdown method in seconds. Defaults to 30
    $daemon->setShutdownTimeout($secs);

    // set alternate shutdown signal which is sent to workers. Default: SIGQUIT
    $daemon->setShutdownSignal(SIGINT);

    // set callback for when every child has been killed
    $daemon->setShutdownHandler(function (DaemonInterface &$d) { /*..*/ });

## Restart behavior

    <?php

    // enable restart (stop/start) of all workers on signal. Use false to
    //  deactivate again (default)
    $daemon->setRestartSignal(SIGUSR1);

    // set handler which is called when stop of all workers is performed, right
    //  before start is executed. Only used if restart signal has been set.
    $daemon->setRestartHandler(function (DaemonInterface &$d) { /* .. */ });

## Logging

Per default, everything is logged to STDOUT.

    <?php

    // set an logfile for output.
    $daemon->setLogfile($filePath);

    // set a \Monolog\Logger for handling logging (above logfile is ignored, if
    //  this is used)
    $daemon->setLogger($logger);

    // access to logger
    $daemon->getLogger()->info("Hello");

## Running

    <?php

    // run daemon, do NOT detach from shell
    $daemon->run();

    // run daemon and DO detach from shell. Write pid into $pidFile
    $daemon->runDetached($pidFile);

## Misc

    <?php

    // sleep entropy introduces randomness into the daemons behavior. A value of
    //  0 indicates no rendomness, a value of 100 the maximum. For example, if 
    //  the [interval] attrib of a worker is set to 30 it will be definitly
    //  30 seconds if entropy is 0 and something between 0 and 60 if entropy is
    //  set to 100. Default is 50 (in the example: 30 sec interval => 15 to
    //  45 seconds of sleep)
    $daemon->setSleepEntropy(50);