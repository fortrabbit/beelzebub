<?php


namespace Frbit\Tests\Beelzebub\Worker;

use Frbit\Beelzebub\Worker;
use Frbit\Beelzebub\Worker\ExecutableWorker;
use Frbit\Tests\Beelzebub\TestCase;


/**
 * @covers  \Frbit\Beelzebub\Worker\ExecutableWorker
 * @package Frbit\Tests\Beelzebub\Worker
 **/
class ExecutableWorkerTest extends TestCase
{
    /**
     * @var \Mockery\MockInterface
     */
    protected $builtIn;

    protected function setUp()
    {
        parent::setUp();
        $this->builtIn = $this->mock('\Frbit\Beelzebub\Helper\BuiltInDouble');
    }

    public function testRun()
    {
        $worker = $this->generateWorker();

        $this->builtIn->shouldReceive('pcntl_exec')
            ->once()
            ->with(
                '/bin/echo',
                [
                    'Hello $FOO',
                    'baz',
                ],
                [
                    'FOO' => 'Bar'
                ]
            );

        $worker->run(["baz"]);
    }

    protected function generateWorker()
    {
        return new ExecutableWorker('worker', '/bin/echo', [
            'Hello $FOO'
        ], [
            'FOO' => 'Bar'
        ], 1, null, 1, $this->builtIn);
    }

}