<?php

namespace Frbit\Tests\Beelzebub;

/**
 * Class TestCase
 * @package Frbit\Tests\Beelzebub
 **/
class TestCase extends \PHPUnit_Framework_TestCase
{

    /**
     * @param string $class
     * @param array  $getter
     * @param array  $methods
     *
     * @return \Mockery\MockInterface
     */
    protected function mock($class, array $getter = [], array $methods = [])
    {
        $mock = \Mockery::mock($class);
        foreach ($getter as $attribute => $returnValue) {
            $method = 'get' . ucfirst($attribute);
            $mock->shouldReceive($method)->andReturn($returnValue);
        }
        foreach ($methods as $method => $returnValue) {
            $mock->shouldReceive($methods)->andReturn($returnValue);
        }

        return $mock;
    }

    /**
     * @param string $class
     * @param array  $getter
     * @param array  $methods
     *
     * @return \Mockery\MockInterface
     */
    protected function mockCurrent($class, array $getter = [], array $methods = [])
    {
        return $this->mock("\\Frbit\\Beelzebub\\$class", $getter, $methods);
    }
}