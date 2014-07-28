<?php


namespace Frbit\Tests\Beelzebub\Worker;

use Frbit\Beelzebub\Worker;
use Frbit\Beelzebub\Worker\CallableWorker;
use Frbit\Tests\Beelzebub\TestCase;


/**
 * @covers  \Frbit\Beelzebub\Worker\CallableWorker
 * @package Frbit\Tests\Beelzebub\Worker
 **/
class CallableWorkerTest extends TestCase
{

    public function testRun()
    {
        $worker = $this->generateWorker($count);
        $worker->run([3, 4]);
        $this->assertSame(7, $count);
    }

    public function testStartupNotGiven()
    {
        $worker = $this->generateWorker($count, false);
        $this->assertFalse($worker->hasStartup());
    }

    public function testStartup()
    {
        $worker = $this->generateWorker($count);
        $this->assertTrue($worker->hasStartup());
    }

    public function testCallWithNoStartup()
    {
        $count = 0;
        $worker = $this->generateWorker($count, false);
        $worker->runStartup();
        $this->assertSame(0, $count);
    }

    public function testCallWithStartup()
    {
        $count = 0;
        $worker = $this->generateWorker($count);
        $worker->runStartup();
        $this->assertSame(5, $count);
    }

    protected function generateWorker(&$count = 0, $startup = true)
    {
        return new CallableWorker('foo', function (Worker $w, array $args) use (&$count) {
            $count += $args[0] + $args[1];
        }, 123, !$startup ? null : function () use (&$count) {
            $count += 5;
        }, 1);
    }

}