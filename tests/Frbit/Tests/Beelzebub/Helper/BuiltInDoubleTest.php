<?php


namespace Frbit\Tests\Beelzebub\Helper;
use Frbit\Beelzebub\Helper\BuiltInDouble;
use Frbit\Tests\Beelzebub\TestCase;


/**
 * @covers  \Frbit\Beelzebub\Helper\BuiltInDouble
 * @package Frbit\Tests\Beelzebub\Helper
 **/
class BuiltInDoubleTest extends TestCase
{

    public function testCreate()
    {
        new BuiltInDouble();
        $this->assertTrue(true, 'Created');
    }

    public function testCallingBuiltIn()
    {
        $builtIn = new BuiltInDouble();
        $sum = $builtIn->array_sum([1, 2, 3]);
        $this->assertSame(6, $sum, 'Called built-in method');
    }

    public function testCallingCreatedAsMocked()
    {
        $builtIn = new BuiltInDouble(true);
        $sum = $builtIn->array_sum([1, 2, 3]);
        $this->assertNull($sum, 'Called built-in method');
    }

    public function testCallingSetAsMocked()
    {
        $builtIn = new BuiltInDouble();
        $builtIn->mock(true);
        $sum = $builtIn->array_sum([1, 2, 3]);
        $this->assertNull($sum, 'Called built-in method');
    }

}