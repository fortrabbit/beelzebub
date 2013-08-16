<?php
/**
 * This class is part of Beelzebub
 */

namespace Beelzebub\Tests;

use Beelzebub\Daemon;
use Beelzebub\DefaultWorker;
use Beelzebub\Worker;
use Mockery as m;

class DefaultWorkerTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @var \Mockery\MockInterface
     */
    protected $daemon;

    public function setUp()
    {
        $this->daemon = m::mock('\Beelzebub\Daemon');
        parent::setUp();
    }

    public function tearDown()
    {
        $this->addToAssertionCount($this->daemon->mockery_getExpectationCount());
        m::close();
        parent::tearDown();
    }

    public function testDummy()
    {
        $this->assertTrue(true);
    }

    /*public function testCreateWorkerInstance()
    {
        $loop   = function () {
        };
        $worker = new DefaultWorker($this->daemon, 'test', $loop);
        $this->assertSame('test', $worker->getName());
        $this->assertSame($this->daemon, $worker->getDaemon());
    }

    public function testCallWorkerLoop()
    {
        $value  = 0;
        $loop   = function (Worker $worker, array $args = array()) use (&$value) {
            $value++;
            $value += count($args);
        };
        $worker = new DefaultWorker($this->daemon, 'test', $loop);
        $worker->run();
        $this->assertSame(1, $value);

        $worker->run(array(1, 2, 3));
        $this->assertSame(5, $value);
    }

    public function testNoStartupSet()
    {
        $loop   = function () {
        };
        $worker = new DefaultWorker($this->daemon, 'test', $loop);
        $this->assertFalse($worker->hasStartup(), 'No startup method defined');
        $result = $worker->runStartup();
        $this->assertNull($result, 'Null returned and nothing run');
    }

    public function testCallWorkerStartup()
    {
        $value   = 0;
        $loop    = function () {
        };
        $startup = function (Worker $worker) use (&$value) {
            $value++;

            return 5;
        };
        $worker  = new DefaultWorker($this->daemon, 'test', $loop, 1, $startup, 10);
        $this->assertTrue($worker->hasStartup(), 'Startup method defined');
        $result = $worker->runStartup();
        $this->assertSame(1, $value);
        $this->assertSame(5, $result);

        $result += $worker->runStartup();
        $this->assertSame(2, $value);
        $this->assertSame(10, $result);
    }

    public function testSetGetAmount()
    {
        $value   = 0;
        $loop    = function () {
        };
        $worker  = new DefaultWorker($this->daemon, 'test', $loop, 1, null, 10);
        $this->assertSame(10, $worker->getAmount());

        $worker->setAmount(5);
        $this->assertSame(5, $worker->getAmount());
    }

    public function testAddPid()
    {
        $value   = 0;
        $loop    = function () {
        };
        $worker  = new DefaultWorker($this->daemon, 'test', $loop, 1, null, 10);

        $pids = $worker->getPids();
        $this->assertInternalType('array', $pids);
        $this->assertSame(0, count($pids));

        $worker->addPid(123);
        $pids = $worker->getPids();
        $this->assertSame(1, count($pids));
        $this->assertSame([123], $pids);

        $worker->addPid(234);
        $pids = $worker->getPids();
        $this->assertSame(2, count($pids));
        $this->assertSame([123, 234], $pids);
    }

    public function testRemovePid()
    {
        $value   = 0;
        $loop    = function () {
        };
        $worker  = new DefaultWorker($this->daemon, 'test', $loop, 1, null, 10);

        $pids = $worker->getPids();
        $this->assertInternalType('array', $pids);
        $this->assertSame(0, count($pids));

        $worker->addPid(123);
        $worker->addPid(234);
        $worker->removePid(123);
        $worker->removePid(345);
        $pids = $worker->getPids();
        $this->assertSame(1, count($pids));
        $this->assertSame([234], $pids);
    }

    public function testCountRunning()
    {
        $value   = 0;
        $loop    = function () {
        };
        $worker  = new DefaultWorker($this->daemon, 'test', $loop, 1, null, 10);

        $pids = $worker->getPids();
        $this->assertInternalType('array', $pids);
        $this->assertSame(0, count($pids));

        $worker->addPid(123);
        $worker->addPid(234);
        $this->assertSame(2, $worker->countRunning());
    }*/

}