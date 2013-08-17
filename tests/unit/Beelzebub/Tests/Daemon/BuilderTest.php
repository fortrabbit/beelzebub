<?php
/**
 * This class is part of Beelzebub
 */

namespace Beelzebub\Tests\Daemon;

use Beelzebub\Daemon\Builder;
use Beelzebub\Tests\Fixtures\TestableDaemonBuilder;
use Mockery as m;

/**
 * Class BuilderTest
 */
class BuilderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Builder
     */
    protected $builder;

    public function setUp()
    {
        $this->builder = new Builder();
        parent::setUp();
    }

    public function tearDown()
    {
        m::close();
        parent::tearDown();
    }

    public function testCanInstantiate()
    {
        $builder = new Builder();
        $this->assertTrue(true);
    }

    public function testCanAddWorkerFromCallableClosure()
    {
        $this->builder->addWorker('test', function () {
        });
        $workers = $this->builder->getWorkers();
        $this->assertSame(1, count($workers));
        $this->assertArrayHasKey('test', $workers);
        $this->assertInstanceOf('\\Beelzebub\\Worker\\Standard', $workers['test']);
    }

    public function testCanAddWorkerFromCallableArray()
    {
        $this->builder->addWorker('test', array($this, 'assertTrue'));
        $workers = $this->builder->getWorkers();
        $this->assertSame(1, count($workers));
        $this->assertArrayHasKey('test', $workers);
        $this->assertInstanceOf('\\Beelzebub\\Worker\\Standard', $workers['test']);
    }

    public function testCanAddWorkerFromArray()
    {
        $this->builder->addWorker('test', array(
            'loop'    => function () {
            },
            'startup' => function () {
            }
        ));
        $workers = $this->builder->getWorkers();
        $this->assertSame(1, count($workers));
        $this->assertArrayHasKey('test', $workers);
        $this->assertInstanceOf('\\Beelzebub\\Worker\\Standard', $workers['test']);
        $this->assertTrue($workers['test']->hasStartup());
    }

    /**
     * @expectedException        \BadMethodCallException
     * @expectedExceptionMessage Worker 'test' is missing the loop definition
     */
    public function testFailAddWorkerFromIncompleteArray()
    {
        $this->builder->addWorker('test', array());
    }

    /**
     * @expectedException        \BadMethodCallException
     * @expectedExceptionMessage Class '\Beelzebub\Daemon' of worker 'test' does not implement the \Beelzebub\Worker interface
     */
    public function testFailAddWorkerWithFromArrayWithClassNotImplementingWorkerInterface()
    {
        $this->builder->addWorker('test', array(
            'loop' => function () {},
            'class' => '\\Beelzebub\\Daemon'
        ));
    }

    public function testCanAddWorkerFromObject()
    {
        $worker = m::mock('\Beelzebub\Worker\Standard');
        $worker->shouldReceive('getName')
            ->once()
            ->withNoArgs()
            ->andReturn('test');
        $this->builder->addWorker('test', $worker);
        $workers = $this->builder->getWorkers();
        $this->assertSame(1, count($workers));
        $this->assertArrayHasKey('test', $workers);
        $this->assertInstanceOf('\\Beelzebub\\Worker\\Standard', $workers['test']);
        $this->assertSame($worker, $workers['test']);
    }

    /**
     * @expectedException        \BadMethodCallException
     * @expectedExceptionMessage Worker name 'fail' and register name 'two' do not match
     */
    public function testFailAddWorkerFromObjectWithMismatchName()
    {
        $worker = m::mock('\Beelzebub\Worker\Standard');
        $worker->shouldReceive('getName')
            ->atLeast(1)
            ->withNoArgs()
            ->andReturn('fail');
        $this->builder->addWorker('two', $worker);
    }

    /**
     * @expectedException        \BadMethodCallException
     * @expectedExceptionMessage Worker 'test' is of class 'Mockery\Mock' which does not implement \Beelzebub\Worker
     */
    public function testFailAddWorkerFromObjectNotImplementingWorkerInterface()
    {
        $worker = m::mock();
        $this->builder->addWorker('test', $worker);
    }

    /**
     * @expectedException        \BadMethodCallException
     * @expectedExceptionMessage Worker 'test' uses unsupported definition (integer)
     */
    public function testFailAddingWorkerFromScalar()
    {
        $this->builder->addWorker('test', 123);
    }

    public function testBuildFromDefaults()
    {
        $daemon = $this->builder
            ->addWorker('test', function() {})
            ->build();
        $this->assertInstanceOf('\Beelzebub\Daemon\Standard', $daemon);
    }

    public function testBuildWithAlternateClass()
    {
        $daemon = $this->builder
            ->addWorker('test', function() {})
            ->setDaemonClass('\Beelzebub\Tests\Fixtures\TestableDaemon')
            ->build();
        $this->assertInstanceOf('\Beelzebub\Tests\Fixtures\TestableDaemon', $daemon);
    }

    /**
     * @expectedException        \BadMethodCallException
     * @expectedExceptionMessage Class '\Beelzebub\Tests\Fixtures\TestableDaemonBuilder' does not implement the \Beelzebub\Daemon interface
     */
    public function testFailOnUsingDaemonClassNotImplementingDaemonInterface()
    {
        $this->builder
            ->setDaemonClass('\Beelzebub\Tests\Fixtures\TestableDaemonBuilder');
    }

    public function testCanSetShutdownSignal()
    {
        $daemon = $this->builder
            ->setShutdownSignal(SIGINT)
            ->build();
        $this->assertSame(SIGINT, $daemon->getShutdownSignal());
        $daemon = $this->builder
            ->setShutdownSignal(SIGQUIT)
            ->build();
        $this->assertSame(SIGQUIT, $daemon->getShutdownSignal());
    }

    public function testCanSetShutdownTimeout()
    {
        $daemon = $this->builder
            ->setShutdownTimeout(10)
            ->build();
        $this->assertSame(10, $daemon->getShutdownTimeout());
        $daemon = $this->builder
            ->setShutdownTimeout(20)
            ->build();
        $this->assertSame(20, $daemon->getShutdownTimeout());
    }

    public function testCanInstantiateWithWorkers()
    {
        $builder = new TestableDaemonBuilder(array(
            'test' => function() {}
        ));
        $workers = $builder->getWorkers();
        $this->assertSame(1, count($workers));
        $this->assertArrayHasKey('test', $workers);
    }

    public function testCanSetProcessManager()
    {
        $manager = m::mock('\Spork\ProcessManager');
        $manager->shouldIgnoreMissing(); // for the wait call, which is called on destruction
        $daemon = $this->builder->setProcessManager($manager)->build();
        $this->assertSame($manager, $daemon->getProcessManager());
    }

    public function testCanSetLogger()
    {
        $logger = m::mock('\Monolog\Logger');
        $daemon = $this->builder->setLogger($logger)->build();
        $this->assertSame($logger, $daemon->getLogger());
    }

    public function testCanSetEventDispatcher()
    {
        $event = m::mock('\Symfony\Component\EventDispatcher\EventDispatcher');
        $daemon = $this->builder->setEventDispatcher($event)->build();
        $this->assertSame($event, $daemon->getEventDispatcher());
    }

}