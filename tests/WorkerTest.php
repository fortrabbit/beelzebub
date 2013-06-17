<?php

namespace Fortrabbit\Beelzebub\Test;

use Fortrabbit\Beelzebub\Worker;
use Fortrabbit\Beelzebub\Test\DummyDaemon;


class WorkerTest extends \PHPUnit_Framework_TestCase
{

    protected static $daemon;

    protected function setUp()
    {
        self::$daemon = new DummyDaemon();
    }



    public function testRunLoop()
    {
        $counter = 1;
        $child = new Worker('test', 1, function() use (&$counter) {
            $counter ++;
        });
        $child->runLoop(self::$daemon);
        $this->assertEquals(2, $counter);
    }

    public function testRunStartup()
    {
        $counter = 1;
        $child = new Worker('test', 1, function() use (&$counter) {
            $counter --;
        }, function () use (&$counter) {
            $counter ++;
        });
        $child->runStartup(self::$daemon);
        $this->assertEquals(2, $counter);
    }


}
