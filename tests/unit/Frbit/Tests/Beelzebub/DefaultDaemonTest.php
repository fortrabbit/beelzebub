<?php
/**
 * This class is part of Beelzebub
 */

use AspectMock\Proxy\ClassProxy;
use Fortrabbit\Beelzebub\DefaultDaemon;
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
        $worker = m::mock('\Fortrabbit\Beelzebub\Worker');
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
        $worker1 = m::mock('\Fortrabbit\Beelzebub\Worker');
        $worker1->shouldReceive('getName')
            ->once()
            ->andReturn('name');
        $worker2 = m::mock('\Fortrabbit\Beelzebub\Worker');
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
        $this->worker->shouldReceive('runLoop')
            ->once()
            ->with($this->daemon);
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

    /*public function testShutdownDaemonWithRunningResistingWorker()
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

        $this->posix->verifyInvokedMultipleTimes(6, 'kill', 123);
        $this->pcntl->verifyNeverInvoked('signal');
        $this->builtin->verifyInvokedOnce('doExit');
        $this->builtin->verifyInvokedMultipleTimes(30, 'doSleep');
    }*/

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

        $this->posix->verifyInvokedMultipleTimes(6, 'kill', 123);
        $this->pcntl->verifyNeverInvoked('signal');
        $this->builtin->verifyInvokedOnce('doExit');
        $this->builtin->verifyInvokedMultipleTimes(30, 'doSleep');
    }

    /*public function testRunDaemonLoopWithSufficientWorkers()
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

        //$this->pcntl->verifyInvokedOnce('signal');
        $this->posix->verifyInvokedOnce('kill', array(123, 0));
        //$this->builtin->verifyInvokedOnce('doExit');

    }*/

    /**
     * @return ClassProxy
     */
    private function getPcntlDouble()
    {
        return test::double('Fortrabbit\Beelzebub\Wrapper\Pcntl', array(
            'signal' => true
        ));
    }

    /**
     * @return ClassProxy
     */
    private function getPosixDouble($result = true)
    {
        return test::double('Fortrabbit\Beelzebub\Wrapper\Posix', array(
            'kill' => $result
        ));
    }

    /**
     * @return ClassProxy
     */
    private function getBuiltinDouble()
    {
        return test::double('Fortrabbit\Beelzebub\Wrapper\Builtin', array(
            'doExit'  => null,
            'doSleep' => null,
        ));
    }

    private function initDaemonWithWorker()
    {
        $this->daemon = new DefaultDaemon($this->manager, $this->logger);
        $this->worker = m::mock('\Fortrabbit\Beelzebub\Worker');
        $this->worker->shouldReceive('getName')
            ->atLeast(1)
            ->withNoArgs()
            ->andReturn("worker-name");
    }

}