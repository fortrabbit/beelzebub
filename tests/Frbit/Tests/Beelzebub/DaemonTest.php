<?php


namespace Frbit\Tests\Beelzebub;

use Frbit\Beelzebub\Daemon;


/**
 * @covers  \Frbit\Beelzebub\Daemon
 * @package Frbit\Tests\Beelzebub
 **/
class DaemonTest extends TestCase
{

    /**
     * @var \Mockery\MockInterface
     */
    protected $builtIns;

    /**
     * @var \Mockery\MockInterface
     */
    protected $logger;

    /**
     * @var \Mockery\MockInterface
     */
    protected $processes;

    /**
     * @var \Mockery\MockInterface
     */
    protected $sleeper;

    /**
     * @var \Mockery\MockInterface
     */
    protected $spork;

    public function setUp()
    {
        parent::setUp();
        $this->spork     = $this->mock('\Spork\ProcessManager', [], ['zombieOkay' => true, 'wait' => true]);
        $this->logger    = $this->mock('\Psr\Log\LoggerInterface')->shouldIgnoreMissing();
        $this->processes = $this->mock('\Frbit\System\UnixProcess\Manager');
        $this->sleeper   = $this->mockCurrent('Sleeper');
        $this->builtIns  = $this->mockCurrent('Helper\BuiltInDouble');
    }

    public function testConstructor()
    {
        new Daemon('foo', $this->spork, $this->logger, $this->processes, $this->sleeper, $this->builtIns);
        $this->assertTrue(true);
    }

    public function testAccessToLogger()
    {
        $daemon = $this->generateDaemon();
        $this->assertSame($this->logger, $daemon->getLogger());
    }

    public function testAccessToSpork()
    {
        $daemon = $this->generateDaemon();
        $this->assertSame($this->spork, $daemon->getSpork());
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

    /**
     * @return Daemon
     */
    protected function generateDaemon()
    {
        $daemon = new Daemon('foo', $this->spork, $this->logger, $this->processes, $this->sleeper, $this->builtIns);

        return $daemon;
    }


}