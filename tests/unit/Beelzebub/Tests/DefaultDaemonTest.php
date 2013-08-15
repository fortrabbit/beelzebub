<?php
/**
 * This class is part of Beelzebub
 */

namespace Beelzebub\Tests;

use AspectMock\Proxy\ClassProxy;
use Beelzebub\DefaultDaemon;
use Mockery as m;
use AspectMock\Test as test;

/**
 * Class DefaultDaemonTest
 *
 */
class DefaultDaemonTest extends \PHPUnit_Framework_TestCase
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
     * @var DefaultDaemon
     */
    protected $daemon;

    /**
     * @var \Mockery\MockInterface
     */
    protected $worker;

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
        $this->pcntl   = $this->getPcntlDouble();
        $this->posix   = $this->getPosixDouble();
        $this->builtin = $this->getBuiltinDouble();
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

    public function testCreateWorkerInstance()
    {
        $daemon = new DefaultDaemon($this->manager, $this->logger);
        $this->assertTrue(true);

        $this->posix->verifyNeverInvoked('kill');
        $this->pcntl->verifyNeverInvoked('signal');
        $this->builtin->verifyNeverInvoked('doExit');
        $this->builtin->verifyNeverInvoked('doSleep');
    }

    public function testRegisterNewWorker()
    {
        $daemon = new DefaultDaemon($this->manager, $this->logger);
        $worker = m::mock('\Beelzebub\Worker');
        $worker->shouldReceive('getName')
            ->once()
            ->andReturn('name');
        $daemon->addWorker($worker);

        $this->posix->verifyNeverInvoked('kill');
        $this->pcntl->verifyNeverInvoked('signal');
        $this->builtin->verifyNeverInvoked('doExit');
        $this->builtin->verifyNeverInvoked('doSleep');
    }

    /**
     * @expectedException        \InvalidArgumentException
     * @expectedExceptionMessage Worker with name name already registered
     */
    public function testCannotAddTwoWorkersWithSameName()
    {
        $daemon  = new DefaultDaemon($this->manager, $this->logger);
        $worker1 = m::mock('\Beelzebub\Worker');
        $worker1->shouldReceive('getName')
            ->once()
            ->andReturn('name');
        $worker2 = m::mock('\Beelzebub\Worker');
        $worker2->shouldReceive('getName')
            ->once()
            ->andReturn('name');
        $daemon->addWorker($worker1);
        $daemon->addWorker($worker2);

        $this->posix->verifyNeverInvoked('kill');
        $this->pcntl->verifyNeverInvoked('signal');
        $this->builtin->verifyNeverInvoked('doExit');
        $this->builtin->verifyNeverInvoked('doSleep');
    }

    public function testRunDaemonLoopFirstTime()
    {

        $this->initDaemonWithWorker();
        $this->worker->shouldReceive('getPids')
            ->once()
            ->andReturn([]);
        $this->worker->shouldReceive('getAmount')
            ->once()
            ->andReturn(1);
        $this->worker->shouldReceive('countRunning')
            ->once()
            ->andReturn(0);
        $fork = m::mock('\Spork\Fork');
        $this->manager->shouldReceive('fork')
            ->once()
            ->andReturn($fork);
        $fork->shouldReceive('then')
            ->once();
        $this->daemon->addWorker($this->worker);
        $this->daemon->loop(1);

        $this->posix->verifyNeverInvoked('kill');
        $this->pcntl->verifyNeverInvoked('signal');
        $this->builtin->verifyNeverInvoked('doExit');
        $this->builtin->verifyNeverInvoked('doSleep');
    }

    public function testRunDaemonLoopWithDiedWorkers()
    {
        $this->posix = $this->getPosixDouble(false);
        $this->initDaemonWithWorker();
        $this->worker->shouldReceive('getPids')
            ->once()
            ->andReturn([123]);
        $this->worker->shouldReceive('getAmount')
            ->once()
            ->andReturn(1);
        $this->worker->shouldReceive('countRunning')
            ->once()
            ->andReturn(0);
        $this->worker->shouldReceive('removePid')
            ->once()
            ->with(123);
        $fork = m::mock('\Spork\Fork');
        $this->manager->shouldReceive('fork')
            ->once()
            ->andReturn($fork);
        $fork->shouldReceive('then')
            ->once();
        $this->daemon->addWorker($this->worker);
        $this->daemon->loop(1);

        $this->posix->verifyInvokedOnce('kill', array(123, 0));
        $this->pcntl->verifyNeverInvoked('signal');
        $this->builtin->verifyNeverInvoked('doExit');
        $this->builtin->verifyNeverInvoked('doSleep');

    }

    public function testRunDaemonLoopWithSufficientWorkers()
    {

        $this->initDaemonWithWorker();
        $this->worker->shouldReceive('getPids')
            ->once()
            ->andReturn([123]);
        $this->worker->shouldReceive('getAmount')
            ->once()
            ->andReturn(1);
        $this->worker->shouldReceive('countRunning')
            ->once()
            ->andReturn(1);
        $this->manager->shouldReceive('fork')
            ->never();
        $this->daemon->addWorker($this->worker);
        $this->daemon->loop(1);

        $this->posix->verifyInvokedOnce('kill', array(123, 0));
        $this->pcntl->verifyNeverInvoked('signal');
        $this->builtin->verifyNeverInvoked('doExit');
        $this->builtin->verifyNeverInvoked('doSleep');

    }

    public function testRunDaemonLoopRunCallback()
    {

        $this->initDaemonWithWorker();
        $this->worker->shouldReceive('getPids')
            ->once()
            ->andReturn([]);
        $this->worker->shouldReceive('getAmount')
            ->once()
            ->andReturn(1);
        $this->worker->shouldReceive('countRunning')
            ->once()
            ->andReturn(0);
        $fork = m::mock('\Spork\Fork');
        $this->manager->shouldReceive('fork')
            ->once()
            ->andReturnUsing(function ($callback) use ($fork) {
                $callback();

                return $fork;
            });
        $this->worker->shouldReceive('hasStartup')
            ->once()
            ->withNoArgs()
            ->andReturn(true);
        $this->worker->shouldReceive('runStartup')
            ->once()
            ->withNoArgs();
        $this->worker->shouldReceive('runLoop')
            ->once()
            ->withNoArgs();
        $self = & $this;
        $fork->shouldReceive('then')
            ->once()
            ->andReturnUsing(function ($callback) use ($fork, $self) {
                $callback($fork, $self);

                return $fork;
            });
        $fork->shouldReceive('getPid')
            ->once()
            ->withNoArgs()
            ->andReturn(123);
        $this->worker->shouldReceive('addPid')
            ->once()
            ->with(123);
        $this->daemon->addWorker($this->worker);
        $this->daemon->loop(1);
        $this->pcntl->verifyNeverInvoked('signal');
        $this->posix->verifyNeverInvoked('kill');
        $this->builtin->verifyNeverInvoked('doExit');
        $this->builtin->verifyNeverInvoked('doSleep');
    }

    public function testRunDaemonMultiLoopRunCallback()
    {

        $this->initDaemonWithWorker();
        $this->worker->shouldReceive('getPids')
            ->twice()
            ->andReturn([], [123]);
        $this->worker->shouldReceive('getAmount')
            ->twice()
            ->andReturn(1);
        $this->worker->shouldReceive('countRunning')
            ->twice()
            ->andReturn(0, 1);
        $fork = m::mock('\Spork\Fork');
        $this->manager->shouldReceive('fork')
            ->once()
            ->andReturn($fork);
        $self = & $this;
        $fork->shouldReceive('then')
            ->once()
            ->andReturnUsing(function ($callback) use ($fork, $self) {
                $callback($fork, $self);

                return $fork;
            });
        $fork->shouldReceive('getPid')
            ->once()
            ->withNoArgs()
            ->andReturn(123);
        $this->worker->shouldReceive('addPid')
            ->once()
            ->with(123);
        $this->daemon->addWorker($this->worker);
        $this->daemon->loop(2);
        $this->pcntl->verifyNeverInvoked('signal');
        $this->posix->verifyInvokedOnce('kill', 123);
        $this->builtin->verifyNeverInvoked('doExit');
        $this->builtin->verifyInvokedOnce('doSleep');
    }

    public function testShutdownDaemonWithoutWorkers()
    {
        $this->initDaemonWithWorker();
        $this->daemon->handleParentShutdownSignal(SIGTERM);
        $this->pcntl->verifyNeverInvoked('signal');
        $this->posix->verifyNeverInvoked('kill');
        $this->builtin->verifyInvokedOnce('doExit');
        $this->builtin->verifyInvokedOnce('doSleep');
    }

    public function testShutdownDaemonWithStoppedWorker()
    {
        $this->initDaemonWithWorker();
        $this->worker->shouldReceive('getPids')
            ->times(5)
            ->andReturn([]);
        $this->worker->shouldReceive('countRunning')
            ->twice()
            ->andReturn(0);
        $this->daemon->addWorker($this->worker);
        $this->daemon->handleParentShutdownSignal(SIGTERM);

        $this->posix->verifyNeverInvoked('kill');
        $this->pcntl->verifyNeverInvoked('signal');
        $this->builtin->verifyInvokedOnce('doExit');
        $this->builtin->verifyInvokedOnce('doSleep');
    }

    public function testShutdownDaemonWithRunningResistingWorker()
    {
        $this->initDaemonWithWorker();
        $this->worker->shouldReceive('getPids')
            ->times(34)
            ->andReturn([123]);
        $this->worker->shouldReceive('countRunning')
            ->times(31)
            ->andReturn(1);
        $this->daemon->addWorker($this->worker);
        $this->daemon->handleParentShutdownSignal(SIGTERM);

        $this->posix->verifyInvokedMultipleTimes('kill', 34, 123);
        $this->pcntl->verifyNeverInvoked('signal');
        $this->builtin->verifyInvokedOnce('doExit');
        $this->builtin->verifyInvokedMultipleTimes('doSleep', 30);
    }

    public function testShutdownDaemonWithRunningAbidingWorker()
    {
        $this->initDaemonWithWorker();
        $this->worker->shouldReceive('getPids')
            ->times(6)
            ->andReturn([123], []);
        $this->worker->shouldReceive('countRunning')
            ->times(3)
            ->andReturn(1, 0);
        $this->daemon->addWorker($this->worker);
        $this->daemon->handleParentShutdownSignal(SIGTERM);

        $this->posix->verifyInvokedMultipleTimes('kill', 1, 123);
        $this->pcntl->verifyNeverInvoked('signal');
        $this->builtin->verifyInvokedOnce('doExit');
        $this->builtin->verifyInvokedMultipleTimes(30, 'doSleep');
    }

    public function testShutdownDaemonWithRunningFromWorker()
    {
        $this->initDaemonWithWorker();
        $this->daemon->handleWorkerShutdownSignal(SIGTERM);

        $this->posix->verifyNeverInvoked('kill');
        $this->pcntl->verifyNeverInvoked('signal');
        $this->builtin->verifyInvokedOnce('doExit');
        $this->builtin->verifyNeverInvoked('doSleep');
    }

    public function testGetSetRestartSignal()
    {
        $this->initDaemonWithWorker();
        $this->daemon->setRestartSignal(SIGINT);
        $this->assertSame(SIGINT, $this->daemon->getRestartSignal());
        $this->daemon->setRestartSignal(SIGTERM);
        $this->assertSame(SIGTERM, $this->daemon->getRestartSignal());

        $this->posix->verifyNeverInvoked('kill');
        $this->pcntl->verifyNeverInvoked('signal');
        $this->builtin->verifyNeverInvoked('doExit');
        $this->builtin->verifyNeverInvoked('doSleep');
    }



    /**
     * @return ClassProxy
     */
    private function getPcntlDouble()
    {
        return test::double('Beelzebub\Wrapper\Pcntl', array(
            'signal' => true
        ));
    }

    /**
     * @return ClassProxy
     */
    private function getPosixDouble($result = true)
    {
        return test::double('Beelzebub\Wrapper\Posix', array(
            'kill' => $result
        ));
    }

    /**
     * @return ClassProxy
     */
    private function getBuiltinDouble()
    {
        return test::double('Beelzebub\Wrapper\Builtin', array(
            'doExit'  => null,
            'doSleep' => null,
        ));
    }

    private function initDaemonWithWorker()
    {
        $this->daemon = new DefaultDaemon($this->manager, $this->logger);
        $this->worker = m::mock('\Beelzebub\Worker');
        $this->worker->shouldReceive('getName')
            ->atLeast(1)
            ->withNoArgs()
            ->andReturn("worker-name");
    }

}