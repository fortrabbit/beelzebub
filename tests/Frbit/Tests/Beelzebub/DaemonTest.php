<?php


namespace Frbit\Tests\Beelzebub;

use Frbit\Beelzebub\Daemon;
use Frbit\Beelzebub\Helper\BuiltInDouble;
use Frbit\Beelzebub\Worker;
use Frbit\System\UnixProcess\Manager;
use Mockery\MockInterface;


/**
 * @covers  \Frbit\Beelzebub\Daemon
 * @package Frbit\Tests\Beelzebub
 **/
class DaemonTest extends TestCase
{

    /**
     * @var MockInterface|BuiltInDouble
     */
    protected $builtIns;

    /**
     * @var MockInterface
     */
    protected $logger;

    /**
     * @var MockInterface|Manager
     */
    protected $processes;

    /**
     * @var MockInterface
     */
    protected $sleeper;

    /**
     * @var MockInterface
     */
    protected $spork;

    public function setUp()
    {
        parent::setUp();
        $this->logger    = $this->mock('\Psr\Log\LoggerInterface')->shouldIgnoreMissing();
        $this->processes = $this->mock('\Frbit\System\UnixProcess\Manager');
        $this->sleeper   = $this->mockCurrent('Sleeper');
        $this->builtIns  = $this->mockCurrent('Helper\BuiltInDouble');
    }

    public function testConstructor()
    {
        new Daemon('foo', $this->logger, $this->processes, $this->sleeper, $this->builtIns);
        $this->assertTrue(true);
    }

    public function testAccessToLogger()
    {
        $daemon = $this->generateDaemon();
        $this->assertSame($this->logger, $daemon->getLogger());
    }

    public function testGetSetShutdownSignal()
    {
        $daemon = $this->generateDaemon();
        $this->assertSame(SIGQUIT, $daemon->getShutdownSignal());
        $daemon->setShutdownSignal(SIGINT);
        $this->assertSame(SIGINT, $daemon->getShutdownSignal());
    }

    public function testGetSetShutdownTimeout()
    {
        $daemon = $this->generateDaemon();
        $this->assertSame(30, $daemon->getShutdownTimeout());
        $daemon->setShutdownTimeout(10);
        $this->assertSame(10, $daemon->getShutdownTimeout());
    }

    public function testSleeper()
    {
        $this->assertSame($this->sleeper, $this->generateDaemon()->getSleeper());
    }

    public function testGetWorkers()
    {
        $worker = $this->generateWorker('foo');
        $daemon = $this->generateDaemon();
        $daemon->addWorker($worker);
        $this->assertSame(['foo' => $worker], $daemon->getWorkers());
    }

    public function testHaltDiesWithMissingPidFile()
    {
        $daemon = $this->generateDaemonWithWorker();
        $this->builtIns->shouldReceive('file_exists')
            ->with('pid-file')
            ->andReturn(false);
        $this->builtIns->shouldReceive('die')
            ->andReturn('died');
        $result = $daemon->halt('pid-file', false);
        $this->assertSame('died', $result);
    }

    public function testHaltDiesWithEmptyPidFile()
    {
        $daemon = $this->generateDaemonWithWorker();
        $this->builtIns->shouldReceive('file_exists')
            ->with('pid-file')
            ->andReturn(true);
        $this->builtIns->shouldReceive('file_get_contents')
            ->with('pid-file')
            ->andReturn('');
        $this->builtIns->shouldReceive('die')
            ->andReturn('died');
        $result = $daemon->halt('pid-file', false);
        $this->assertSame('died', $result);
    }

    public function testHaltDiesWithMissingProcess()
    {
        $daemon = $this->generateDaemonWithWorker();
        $this->builtIns->shouldReceive('file_exists')
            ->with('pid-file')
            ->andReturn(true);
        $this->builtIns->shouldReceive('file_get_contents')
            ->with('pid-file')
            ->andReturn('1234');

        $processList = $this->mock('Frbit\System\UnixProcess\ProcessList');
        $this->processes->shouldReceive('all')
            ->andReturn($processList);
        $processList->shouldReceive('getByPid')
            ->with('1234')
            ->andReturnNull();
        $this->builtIns->shouldReceive('die')
            ->andReturn('died');
        $result = $daemon->halt('pid-file', false);
        $this->assertSame('died', $result);
    }


    /**
     * @return Daemon
     */
    protected function generateDaemon()
    {
        $daemon = new Daemon('foo', $this->logger, $this->processes, $this->sleeper, $this->builtIns);

        return $daemon;
    }

    /**
     * @return Daemon
     */
    protected function generateDaemonWithWorker()
    {
        $worker = $this->generateWorker('foo');
        $daemon = $this->generateDaemon();
        $daemon->addWorker($worker);

        return $daemon;
    }

    /**
     * @param string $name
     *
     * @return MockInterface|Worker
     */
    protected function generateWorker($name)
    {
        $worker = $this->mockCurrent('Worker');
        $worker->shouldReceive('getName')
            ->andReturn($name);

        return $worker;
    }


}