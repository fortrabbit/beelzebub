<?php


namespace Frbit\Beelzebub\Worker;

use Frbit\Beelzebub\Helper\BuiltInDouble;
use Frbit\Beelzebub\Worker;

/**
 * Class ExecutableWorker
 * @package Frbit\Beelzebub\Worker
 **/
class ExecutableWorker extends AbstractWorker
{
    /**
     * @var BuiltInDouble
     */
    protected $builtInDouble;

    /**
     * @var array
     */
    protected $loopArgs;

    /**
     * The loop callback
     *
     * @var string
     */
    protected $loopCommand;

    /**
     * @var array
     */
    protected $loopEnv;

    /**
     * @param string        $name
     * @param string        $loopCommand
     * @param array         $loopArgs
     * @param array         $loopEnv
     * @param int           $interval
     * @param null          $startup
     * @param int           $amount
     * @param BuiltInDouble $builtInDouble
     */
    public function __construct($name, $loopCommand, array $loopArgs, array $loopEnv, $interval = self::DEFAULT_INTERVAL, $startup = null, $amount = self::DEFAULT_AMOUNT, BuiltInDouble $builtInDouble = null)
    {
        // for < 5.4, we cannot use type hints
        if (!is_executable($loopCommand)) {
            throw new \BadMethodCallException("Loop command \"$loopCommand\" needs to be executable");
        }
        $this->name          = $name;
        $this->loopCommand   = $loopCommand;
        $this->loopArgs      = $loopArgs;
        $this->loopEnv       = $loopEnv;
        $this->interval      = $interval ?: self::DEFAULT_INTERVAL;
        $this->startup       = $startup;
        $this->amount        = $amount ?: self::DEFAULT_AMOUNT;
        $this->builtInDouble = $builtInDouble ?: new BuiltInDouble;
    }

    /**
     * {@inheritdoc}
     */
    public function hasStartup()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function run(array $args = array())
    {
        $this->builtInDouble->pcntl_exec($this->loopCommand, array_merge($this->loopArgs, $args), $this->loopEnv);
    }

    /**
     * {@inheritdoc}
     */
    public function runStartup()
    {
        // do nothing
    }
}