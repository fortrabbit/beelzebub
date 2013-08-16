<?php
/**
 * This class is part of Beelzebub
 */

namespace Beelzebub\Daemon;


use Beelzebub\Daemon;
use Beelzebub\Worker;
use Beelzebub\Worker\Standard as StandardWorker;
use Monolog\Handler\NullHandler;
use Monolog\Logger;
use Spork\EventDispatcher\EventDispatcherInterface;
use Spork\ProcessManager;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Easy interface for building daemons and workers
 *
 *
 * @package Beelzebub\Daemon
 */
class Builder
{

    /**
     * @var array
     */
    protected $workers;

    /**
     * @var string
     */
    protected $class;

    /**
     * @var ProcessManager
     */
    protected $manager;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var EventDispatcherInterface
     */
    protected $events;

    /**
     * @var int
     */
    protected $shutdownSignal;

    /**
     * @var int
     */
    protected $shutdownTimeout;

    /**
     * Create Daemon
     *
     * @param array  $workers
     * @param string $class
     *
     * @throws \BadMethodCallException
     */
    public function __construct(array $workers = array(), $class = null)
    {
        if (!is_null($class) && !class_exists($class)) {
            throw new \BadMethodCallException("Daemon class '$class' not existing");
        }
        $this->class   = $class;
        $this->workers = $workers;
    }

    /**
     * Add worker definition
     *
     * @param                       $name
     * @param array|callable|Worker $definition
     *
     * @return Builder
     */
    public function addWorker($name, $definition)
    {
        $this->workers[$name] = $definition;

        return $this;
    }

    /**
     * Set process manager
     *
     * @param ProcessManager $manager
     *
     * @return Builder
     */
    public function setProcessManager(ProcessManager $manager)
    {
        $this->manager = $manager;

        return $this;
    }

    /**
     * Set logger
     *
     * @param Logger $logger
     *
     * @return Builder
     */
    public function setLogger(Logger $logger)
    {
        $this->logger = $logger;

        return $this;
    }

    /**
     * Set event dispatcher
     *
     * @param EventDispatcher $events
     *
     * @return Builder
     */
    public function setEventDispatcher(EventDispatcher $events)
    {
        $this->events = $events;

        return $this;
    }

    /**
     * Set shutdown signal (eg SIGTERM)
     *
     * @param $signo
     *
     * @return Builder
     */
    public function setShutdownSignal($signo)
    {
        $this->shutdownSignal = $signo;

        return $this;
    }

    /**
     * Set shutdown timeout
     *
     * @param $seconds
     *
     * @return Builder
     */
    public function setShutdownTimeout($seconds)
    {
        $this->shutdownTimeout = $seconds;

        return $this;
    }

    /**
     * Build worker from definitions
     *
     * @return Daemon
     * @throws \BadMethodCallException
     */
    public function build()
    {
        if (!$this->manager) {
            $this->manager = new ProcessManager();
        }
        if (!$this->logger) {
            $handler      = new NullHandler();
            $this->logger = new Logger('null', array($handler));
        }
        if (!$this->events) {
            $this->events = new EventDispatcher();
        }
        if (!$this->class) {
            $this->class = '\\Beelzebub\\Daemon\\Standard';
        }

        $class = $this->class;

        /** @var Daemon $daemon */
        $daemon = new $class($this->manager, $this->logger, $this->events);

        if ($this->shutdownSignal) {
            $daemon->setShutdownSignal($this->shutdownSignal);
        }

        if ($this->shutdownTimeout) {
            $daemon->setShutdownTimeout($this->shutdownTimeout);
        }

        // add all the workers from definition
        foreach ($this->workers as $name => $definition) {

            // callable -> generate standard worker
            if (is_callable($definition)) {
                $worker = new StandardWorker($name, $definition);
            } // array('loop' => function () {}, ...)
            elseif (is_array($definition)) {
                if (!isset($definition['loop'])) {
                    throw new \BadMethodCallException("Worker '$name' is missing the loop definition");
                }
                // $name, $loop, $interval = 1, $startup = null, $amount = 1
                $loop        = $definition['loop'];
                $interval    = isset($definition['interval']) ? $definition['interval'] : null;
                $startup     = isset($definition['startup']) ? $definition['startup'] : null;
                $amount      = isset($definition['amount']) ? $definition['amount'] : null;
                $workerClass = isset($definition['class']) ? $definition['class'] : '\\Beelzebub\\Worker\\Standard';
                $worker      = new $workerClass($name, $loop, $interval, $startup, $amount);
            } // Worker object
            elseif (is_object($definition)) {
                if (!($definition instanceof Worker)) {
                    throw new \BadMethodCallException(
                        "Worker '$name' is of class '" . get_class($definition) . "' which does not implement \\Beelzebub\\Worker"
                    );
                }
                $worker = $definition;
            } // fail
            else {
                throw new \BadMethodCallException("Worker '$name' uses unsupported definition (" . gettype($definition) . ")");
            }
            $daemon->addWorker($worker);
        }

        return $daemon;

    }


}