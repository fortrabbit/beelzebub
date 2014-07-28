<?php


namespace Frbit\Tests\Beelzebub\Worker;
use Frbit\Beelzebub\Worker;
use Frbit\Tests\Beelzebub\Fixtures\TestableAbstractWorker;
use Frbit\Tests\Beelzebub\TestCase;


/**
 * @covers  \Frbit\Beelzebub\Worker\AbstractWorker
 * @package Frbit\Tests\Beelzebub\Worker
 **/
class AbstractWorkerTest extends TestCase
{

    public function setUp()
    {
        parent::setUp();
    }

    public function tearDown()
    {
        parent::tearDown();
    }

    public function testCallLoop()
    {
        $count = 0;
        $worker = $this->generateWorker($count);
        $worker->run([3, 4]);
        $this->assertSame(7, $count);
    }

    public function testCallStartup()
    {
        $count = 0;
        $worker = new TestableAbstractWorker('foo', function ($c1, $c2) use (&$count) {
            $count += $c1 + $c2;
        }, 10, 1, function () use (&$count) {
            $count += 5;
        });
        $worker->runStartup();
        $this->assertSame(5, $count);
    }

    public function testDaemon()
    {
        $worker = $this->generateWorker();
        $daemon = $this->mockCurrent('Daemon');
        $worker->setDaemon($daemon);
        $this->assertSame($daemon, $worker->getDaemon());
    }

    public function testName()
    {
        $worker = $this->generateWorker();
        $this->assertSame('foo', $worker->getName());
    }

    public function testAmount()
    {
        $worker = $this->generateWorker();
        $worker->setAmount(123);
        $this->assertSame(123, $worker->getAmount());
    }

    public function testInterval()
    {
        $worker = $this->generateWorker();
        $worker->setInterval(123);
        $this->assertSame(123, $worker->getInterval());
    }

    /**
     * @return Worker
     */
    protected function generateWorker(&$count = 0)
    {
        $worker = new TestableAbstractWorker('foo', function ($c1, $c2) use (&$count) {
            $count += $c1 + $c2;
        }, 10, 1);

        return $worker;
    }

}