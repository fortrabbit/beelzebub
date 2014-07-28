<?php


namespace Frbit\Tests\Beelzebub\Fixtures;

use Frbit\Beelzebub\Worker\AbstractWorker;


/**
 * Class TestableAbstractWorker
 * @package Frbit\Tests\Beelzebub\Fixtures
 **/
class TestableAbstractWorker extends AbstractWorker
{
    /**
     * @var callable
     */
    private $loop;
    /**
     * @var callable
     */
    private $startup;

    /**
     * @param string   $name
     * @param callable $loop
     * @param int      $interval
     * @param int      $amount
     * @param callable $startup
     */
    function __construct($name, callable $loop, $interval = 10, $amount = 1, callable $startup = null)
    {
        $this->name     = $name;
        $this->loop     = $loop;
        $this->startup  = $startup;
        $this->interval = $interval;
        $this->amount   = $amount;
    }


    public function hasStartup()
    {
        return $this->startup ? true : false;
    }

    public function run(array $args = array())
    {
        call_user_func_array($this->loop, $args);
    }

    public function runStartup()
    {
        if ($this->startup) {
            call_user_func($this->startup);
        }
    }
}