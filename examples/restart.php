<?php

require_once __DIR__ . '/../vendor/autoload.php';

echo "Client will print out a log message about every second\n";
echo " Press Ctrl+c to exit\n\n";

class Foo
{
    protected $counter = 0;

    public function startup()
    {
        $this->counter++;
    }

    public function loop()
    {
        error_log("Working {$this->counter}");
    }
}


$foo    = new Foo;
$daemon = new Frbit\Beelzebub\Daemon('simple-daemon');
$daemon->addWorker(new \Frbit\Beelzebub\Worker\CallableWorker(
    'simple-worker1',
    [$foo, 'loop'],
    5,
    [$foo, 'startup']
));
$daemon->run();