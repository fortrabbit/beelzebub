<?php
/**
 * This class is part of Beelzebub
 */

namespace Beelzebub\Tests;

use Beelzebub\Daemon;
use Beelzebub\Worker\Standard;
use Beelzebub\Worker;
use Mockery as m;

/**
 * Class WorkerTest
 */
class WorkerTest extends \PHPUnit_Framework_TestCase
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

    public function testCreateInstanceFromCallableClosure()
    {
        new Standard('test', function () {
        });
        $this->assertTrue(true);
    }

    public function testCreateInstanceFromCallableArray()
    {
        new Standard('test', array($this, 'assertTrue'));
        $this->assertTrue(true);
    }

    /**
     * @expectedException        \BadMethodCallException
     * @expectedExceptionMessage Loop needs to be callable
     */
    public function testFailCreateInstanceWithNonCallableArray()
    {
        new Standard('test', array());
        $this->assertTrue(true);
    }

    public function testCreateInstanceWithStartupCallableClosure()
    {
        new Standard('test', function () {
        }, 1, function () {
        });
        $this->assertTrue(true);
    }

    public function testCreateInstanceWithStartupCallableArray()
    {
        new Standard('test', function () {
        }, 1, array($this, 'assertTrue'));
        $this->assertTrue(true);
    }

    /**
     * @expectedException        \BadMethodCallException
     * @expectedExceptionMessage Startup needs to be callable
     */
    public function testFailCreateInstanceWithNonCallableStartup()
    {
        new Standard('test', function () {
        }, 1, array());
        $this->assertTrue(true);
    }

    public function testCanRunLoopMethod()
    {
        $check  = '';
        $worker = new Standard('test', function (Worker $w) use (&$check) {
            $check = $w->getName();
        });
        $worker->run();
        $this->assertSame('test', $check);
    }

    public function testCanRunLoopMethodWithArgs()
    {
        $check  = '';
        $worker = new Standard('test', function (Worker $w, array $args) use (&$check) {
            $check = $w->getName() . ': ' . implode(', ', $args);
        });
        $worker->run(array(1, 2, 3));
        $this->assertSame('test: 1, 2, 3', $check);
    }

    public function testStartupNotUsedIfNotSet()
    {
        $check  = '';
        $worker = new Standard('test', function () {
        });

        $result = $worker->runStartup();

        $this->assertFalse($worker->hasStartup(), 'Startup not set');
        $this->assertNull($result, 'Startup returns null');
    }

    public function testStartupCanBeUsedIfSet()
    {
        $check  = '';
        $worker = new Standard('test', function () {
        }, 1, function (Worker $w) use (&$check) {
            return $check = $w->getName();
        });

        $result = $worker->runStartup();

        $this->assertTrue($worker->hasStartup(), 'Has a startup method');
        $this->assertSame('test', $check);
        $this->assertSame('test', $result);
    }

    public function testSetGetDaemon()
    {
        $daemon = m::mock('\Beelzebub\Daemon');
        $worker = new Standard('test', function () {
        });

        $worker->setDaemon($daemon);

        $this->assertSame($daemon, $worker->getDaemon());
    }

    public function testSetGetInterval()
    {
        $worker = new Standard('test', function () {
        });

        $before = $worker->getInterval();
        $worker->setInterval($before + 10);

        $this->assertSame(10, $before);
        $this->assertSame(20, $worker->getInterval());
    }

    public function testSetGetAmount()
    {
        $worker = new Standard('test', function () {
        });

        $before = $worker->getAmount();
        $worker->setAmount($before + 10);

        $this->assertSame(1, $before);
        $this->assertSame(11, $worker->getAmount());
    }

}