<?php
/**
 * This class is part of Beelzebub
 */

namespace Beelzebub\Tests;

use AspectMock\Proxy\ClassProxy;
use Beelzebub\Tests\Fixtures\TestableDaemon;
use Mockery as m;
use AspectMock\Test as test;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Class DefaultDaemonTest
 *
 */
class DaemonTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @var \Mockery\MockInterface
     */
    protected $logger;

    /**
     * @var \Mockery\MockInterface
     */
    protected $manager;

    /**
     * @var TestableDaemon
     */
    protected $daemon;

    /**
     * @var \Mockery\MockInterface
     */
    protected $worker;

    /**
     * @var \Mockery\MockInterface
     */
    protected $events;

    /**
     * @var ClassProxy
     */
    protected $pcntl;

    /**
     * @var ClassProxy
     */
    protected $posix;

    /**
     * @var ClassProxy
     */
    protected $builtin;

    public function setUp()
    {
        $this->logger = m::mock('\Monolog\Logger');
        $this->logger->shouldIgnoreMissing();
        $this->manager = m::mock('\Spork\ProcessManager');
        $this->manager->shouldReceive('wait')->atLeast(1);
        $this->events = m::mock('\Symfony\Component\EventDispatcher\EventDispatcher');
        //$this->events->shouldReceive('dispatch')->zeroOrMoreTimes();
        $this->pcntl   = test::double('Beelzebub\Wrapper\Pcntl', array(
            'signal' => true
        ));
        $this->posix   = $this->getPosixDouble();
        $this->builtin = test::double('Beelzebub\Wrapper\Builtin', array(
            'doExit'   => null,
            'doUsleep' => null,
        ));
        parent::setUp();
    }

    public function tearDown()
    {
        $this->addToAssertionCount($this->logger->mockery_getExpectationCount());
        $this->addToAssertionCount($this->manager->mockery_getExpectationCount());
        if ($this->worker) {
            $this->addToAssertionCount($this->worker->mockery_getExpectationCount());
        }
        m::close();
        test::clean();
        parent::tearDown();
    }


    public function testCreateDaemonInstance()
    {
        $daemon = new TestableDaemon($this->manager, $this->logger, $this->events);
        $this->assertSame(SIGQUIT, $daemon->getShutdownSignal());
        $this->assertSame(30, $daemon->getShutdownTimeout());
        $this->assertStaticCalls();
    }

    public function testAddSingleWorkerToDaemon()
    {
        $this->initDaemonWithWorker(false);
        $this->worker->shouldReceive('setDaemon')
            ->once()
            ->with($this->daemon);
        $this->daemon->addWorker($this->worker);
        $this->assertEquals(array('worker-name' => $this->worker), $this->daemon->getWorkers());
        $this->assertStaticCalls();
    }

    public function testAddMultipleWorkersToDaemon()
    {
        $this->initDaemonWithWorker(false);
        $this->worker->shouldReceive('setDaemon')
            ->once()
            ->with($this->daemon);
        $this->daemon->addWorker($this->worker);
        $other = $this->generateWorker('other');
        $this->daemon->addWorker($other);
        $this->assertEquals(array('worker-name' => $this->worker, 'other' => $other), $this->daemon->getWorkers());
        $this->assertStaticCalls();
    }

    /**
     * @expectedException        \InvalidArgumentException
     * @expectedExceptionMessage Worker with name worker-name already registered
     */
    public function testFailToAddWorkerWithTheSameNameMultipleTimes()
    {
        $this->initDaemonWithWorker();
        $this->daemon->addWorker($this->worker);
        $this->daemon->addWorker($this->worker);
    }

    public function testRunOnceAndForkNewProcess()
    {
        $this->initDaemonWithWorker();
        $this->daemon->addWorker($this->worker);

        $events     = array();
        $eventCount = 0;
        $this->events->shouldReceive('dispatch')
            ->twice()
            ->andReturnUsing(function ($name) use (&$events, &$eventCount) {
                $events[$name] = $eventCount++;
            });
        $this->worker->shouldReceive('getAmount')
            ->zeroOrMoreTimes()
            ->andReturn(1);
        $this->worker->shouldReceive('getInterval')
            ->zeroOrMoreTimes()
            ->andReturn(1);
        $fork = m::mock('\Spork\Fork');
        $this->worker->shouldReceive('hasStartup')
            ->once()
            ->withNoArgs()
            ->andReturn(false);
        $this->worker->shouldReceive('runStartup')
            ->never();
        $this->worker->shouldReceive('run')
            ->once()
            ->withNoArgs();
        $this->manager->shouldReceive('fork')
            ->once()
            ->andReturnUsing(function ($callback) use ($fork) {
                $callback();

                return $fork;
            });
        $fork->shouldReceive('then')
            ->once()
            ->andReturn($fork);
        $fork->shouldReceive('getPid')
            ->once()
            ->andReturn(123);
        $this->daemon->stop();
        $this->daemon->run(1);
        $this->assertEquals(array('worker-name' => array(123 => $fork)), $this->daemon->getForks());
        $this->assertSame(array(
            'worker.pre-start'  => 0,
            'worker.post-start' => 1
        ), $events);
        $this->assertStaticCalls(array(
            'pcntl'   => array('signal' => 8),
            'builtin' => array('doUsleep' => 0)
        ));
    }

    public function testRunTwiceAndForkNewProcess()
    {
        $this->posix = $this->getPosixDouble(true);

        $events     = array();
        $eventCount = 0;
        $this->events->shouldReceive('dispatch')
            ->times(3)
            ->andReturnUsing(function ($name) use (&$events, &$eventCount) {
                $events[$name] = $eventCount++;
            });
        $this->initDaemonWithWorker();
        $this->daemon->addWorker($this->worker);
        $this->worker->shouldReceive('getAmount')
            ->zeroOrMoreTimes()
            ->andReturn(1);
        $this->worker->shouldReceive('getInterval')
            ->zeroOrMoreTimes()
            ->andReturn(1);
        $this->worker->shouldReceive('hasStartup')
            ->once()
            ->withNoArgs()
            ->andReturn(true);
        $this->worker->shouldReceive('runStartup')
            ->once()
            ->withNoArgs();
        $this->worker->shouldReceive('run')
            ->once()
            ->withNoArgs();
        $fork = m::mock('\Spork\Fork');
        $this->manager->shouldReceive('fork')
            ->once()
            ->andReturnUsing(function ($callback) use ($fork) {
                $callback();

                return $fork;
            });
        $fork->shouldReceive('then')
            ->once()
            ->andReturn($fork);
        $fork->shouldReceive('getPid')
            ->atLeast(1)
            ->andReturn(123);
        //$this->daemon->stop();
        $this->daemon->stop();
        $this->daemon->run(2);
        $this->assertEquals(array('worker-name' => array(123 => $fork)), $this->daemon->getForks());
        $this->assertSame(array(
            'worker.pre-start'  => 0,
            'worker.post-start' => 1,
            'daemon.stopping'   => 2
        ), $events);
        $this->assertStaticCalls(array(
            'pcntl'   => array('signal' => 8),
            'posix'   => array('kill' => -1),
            'builtin' => array('doUsleep' => 0)
        ));
    }

    public function testHandleParentShutdownWithNoExistingForks()
    {
        $this->initDaemonWithWorker();
        $this->daemon->addWorker($this->worker);
        $this->posix = $this->getPosixDouble(true);

        $events     = array();
        $eventCount = 0;
        $this->events->shouldReceive('dispatch')
            ->times(2)
            ->andReturnUsing(function ($name) use (&$events, &$eventCount) {
                $events[$name] = $eventCount++;
            });

        $this->manager->shouldReceive('zombieOkay')
            ->once()
            ->with(true);

        $this->daemon->handleParentShutdownSignal(SIGINT);
        $this->assertSame(array(
            'daemon.pre-shutdown'  => 0,
            'daemon.post-shutdown' => 1
        ), $events);
        $this->assertStaticCalls(array(
            'builtin' => array('doExit' => 1, 'doUsleep' => 0),
        ));
    }

    public function testHandleParentShutdownWithExistingForks()
    {
        $this->initDaemonWithWorker();
        $this->daemon->addWorker($this->worker);
        $fork1 = m::mock('Spork\Fork');
        $fork2 = m::mock('Spork\Fork');
        $this->daemon->setForks('worker-name', array(123 => $fork1, 234 => $fork2));

        $fork1->shouldReceive('getPid')
            ->zeroOrMoreTimes()
            ->andReturn(123);
        $fork1->shouldReceive('isExited')
            ->zeroOrMoreTimes()
            ->withNoArgs()
            ->andReturn(true);
        $fork2->shouldReceive('kill')
            ->never();
        $fork2->shouldReceive('getPid')
            ->zeroOrMoreTimes()
            ->andReturn(234);
        $fork2->shouldReceive('isExited')
            ->zeroOrMoreTimes()
            ->withNoArgs()
            ->andReturn(false);
        $fork2->shouldReceive('wait')
            ->zeroOrMoreTimes();
        $fork2->shouldReceive('kill')
            ->once()
            ->with(SIGQUIT);
        $fork2->shouldReceive('kill')
            ->once()
            ->with(SIGKILL);

        $events     = array();
        $eventCount = 0;
        $this->events->shouldReceive('dispatch')
            ->times(3)
            ->andReturnUsing(function ($name) use (&$events, &$eventCount) {
                $events[$name] = $eventCount++;
            });

        $this->manager->shouldReceive('zombieOkay')
            ->once()
            ->with(true);

        $this->daemon->handleParentShutdownSignal(SIGINT);
        $this->assertSame(array(
            'daemon.pre-shutdown'  => 0,
            'worker.shutdown'      => 1,
            'daemon.post-shutdown' => 2
        ), $events);
        $this->assertStaticCalls(array(
            'builtin' => array('doExit' => 1, 'doUsleep' => 0),
            'posix'   => array('kill' => 0),
        ));
    }


    private function initDaemonWithWorker($extended = true)
    {
        $this->daemon = new TestableDaemon($this->manager, $this->logger, $this->events);
        $this->worker = $this->generateWorker('worker-name', $extended);
    }

    private function generateWorker($name, $extended = true)
    {
        $worker = m::mock('\Beelzebub\Worker');
        $worker->shouldReceive('getName')
            ->zeroOrMoreTimes()
            ->andReturn($name);
        if ($extended) {
            $worker->shouldReceive('setDaemon')
                ->once()
                ->with($this->daemon);
        }

        return $worker;
    }

    private function assertStaticCalls(array $args = array())
    {
        $defaults = array(
            'posix'   => array('kill' => -1),
            'pcntl'   => array('signal' => -1),
            'builtin' => array('doExit' => -1, 'doUsleep' => -1),
        );
        foreach ($defaults as $name => $methods) { // silly merge..
            if (!isset($args[$name])) {
                $args[$name] = array();
            }
            foreach ($methods as $method => $count) {
                if (!isset($args[$name][$method])) {
                    $args[$name][$method] = $count;
                }
            }
        }
        foreach ($args as $name => $methods) {
            foreach ($methods as $method => $count) {
                $call     = array($this->$name);
                $callArgs = array($method);
                if ($count === 0) {
                    $call [] = 'verifyInvoked';
                } elseif ($count === -1) {
                    $call [] = 'verifyNeverInvoked';
                } elseif ($count === 1) {
                    $call [] = 'verifyInvokedOnce';
                } else {
                    $call []     = 'verifyInvokedMultipleTimes';
                    $callArgs [] = $count;
                }
                call_user_func_array($call, $callArgs);
            }
        }
    }

    /**
     * @param bool $result
     *
     * @return ClassProxy
     */
    private function getPosixDouble($result = true)
    {
        return test::double('Beelzebub\Wrapper\Posix', array(
            'kill' => $result
        ));
    }

}