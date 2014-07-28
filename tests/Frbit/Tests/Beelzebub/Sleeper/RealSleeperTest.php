<?php


namespace Frbit\Tests\Beelzebub\Sleeper;

use Frbit\Beelzebub\Helper\BuiltInDouble;
use Frbit\Beelzebub\Sleeper\RealSleeper;
use Frbit\Tests\Beelzebub\TestCase;
use Mockery\MockInterface;

/**
 * @covers  \Frbit\Beelzebub\Sleeper\RealSleeper
 * @package Frbit\Tests\Beelzebub\Sleeper
 **/
class RealSleeperTest extends TestCase
{
    /**
     * @var MockInterface|BuiltInDouble
     */
    protected $builtIn;

    protected function setUp()
    {
        parent::setUp(); // TODO: Change the autogenerated stub
        $this->builtIn = $this->mock('\Frbit\Beelzebub\Helper\BuiltInDouble');
    }


    public function testSomething()
    {
        $sleeper = new RealSleeper($this->builtIn);
        $this->builtIn->shouldReceive('sleep')
            ->once()
            ->with(123);
        $sleeper->sleep(123);
    }

}