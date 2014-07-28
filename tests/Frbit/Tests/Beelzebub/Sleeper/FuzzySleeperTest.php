<?php


namespace Frbit\Tests\Beelzebub\Sleeper;

use Frbit\Beelzebub\Helper\BuiltInDouble;
use Frbit\Beelzebub\Sleeper\FuzzySleeper;
use Frbit\Beelzebub\Sleeper\RealSleeper;
use Frbit\Tests\Beelzebub\TestCase;
use Mockery\MockInterface;

/**
 * @covers  \Frbit\Beelzebub\Sleeper\FuzzySleeper
 * @package Frbit\Tests\Beelzebub\Sleeper
 **/
class FuzzySleeperTest extends TestCase
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
        $sleeper = new FuzzySleeper(0.5, $this->builtIn);
        $this->builtIn->shouldReceive('usleep')
            ->once()
            ->andReturnUsing(function($time) {
                $this->assertGreaterThanOrEqual(5000000, $time);
                $this->assertLessThanOrEqual(15000000, $time);
            });
        $sleeper->sleep(10);
    }

}