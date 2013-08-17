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
     * @var Worker[]
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
     * @param array $workers
     */
    public function __construct(array $workers = array())
    {
        $this->workers = array();
        if ($workers) {
            foreach ($workers as $name => $definition) {
                $this->addWorker($name, $definition);
            }
        }
    }

    /**
     * Add worker definition
     *
     * @param string                $name
     * @param array|callable|Worker $definition
     *
     * @return Builder
     */
    public function addWorker($name, $definition)
    {
        // method
        if (is_callable($definition)) {
            $worker = $this->createWorkerFromCallable($name, $definition);
        } // array('loop' => function () {}, ...)
        elseif (is_array($definition)) {
            $worker = $this->createWorkerFromArray($name, $definition);
        } // Worker object
        elseif (is_object($definition)) {
            $worker = $this->createWorkerFromObject($name, $definition);
        } // fail
        else {
            throw new \BadMethodCallException("Worker '$name' uses unsupported definition (" . gettype($definition) . ")");
        }
        $this->workers[$name] = $worker;

        return $this;
    }

    /**
     * Set different daemon class
     *
     * @param $class
     *
     * @throws \BadMethodCallException
     *
     * @return Builder
     */
    public function setDaemonClass($class)
    {

        if (!in_array('Beelzebub\Daemon', class_implements($class))) {
            throw new \BadMethodCallException("Class '$class' does not implement the \\Beelzebub\\Daemon interface");
        }

        $this->class = $class;

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
        $this->assureDefaults();

        $class = $this->class;

        /** @var Daemon $daemon */
        $daemon = new $class($this->manager, $this->logger, $this->events);

        if ($this->shutdownSignal) {
            $daemon->setShutdownSignal($this->shutdownSignal);
        }

        if ($this->shutdownTimeout) {
            $daemon->setShutdownTimeout($this->shutdownTimeout);
        }

        foreach ($this->workers as $worker) {
            $daemon->addWorker($worker);
        }

        return $daemon;
    }

    /**
     * All created workers
     *
     * @return Worker[]
     */
    public function getWorkers()
    {
        return $this->workers;
    }

    /**
     * Create a new worker from callable
     *
     * @param string   $name
     * @param callable $definition
     *
     * @return StandardWorker
     */
    protected function createWorkerFromCallable($name, $definition)
    {
        return new StandardWorker($name, $definition);
    }

    /**
     * Create a new worker from array definition
     *
     * @param string $name
     * @param array  $definition
     *
     * @return Worker
     * @throws \BadMethodCallException
     */
    protected function createWorkerFromArray($name, array $definition)
    {
        if (!isset($definition['loop'])) {
            throw new \BadMethodCallException("Worker '$name' is missing the loop definition");
        }
        // $name, $loop, $interval = 1, $startup = null, $amount = 1
        $loop        = $definition['loop'];
        $interval    = isset($definition['interval']) ? $definition['interval'] : null;
        $startup     = isset($definition['startup']) ? $definition['startup'] : null;
        $amount      = isset($definition['amount']) ? $definition['amount'] : null;
        $workerClass = isset($definition['class']) ? $definition['class'] : '\\Beelzebub\\Worker\\Standard';

        if (!in_array('Beelzebub\Worker', class_implements($workerClass))) {
            throw new \BadMethodCallException("Class '$workerClass' of worker '$name' does not implement the \\Beelzebub\\Worker interface");
        }

        return new $workerClass($name, $loop, $interval, $startup, $amount);
    }

    /**
     * Create a new worker from object
     *
     * @param $name
     * @param $definition
     *
     * @return Worker
     * @throws \BadMethodCallException
     */
    protected function createWorkerFromObject($name, $definition)
    {
        if (!($definition instanceof Worker)) {
            throw new \BadMethodCallException(
                "Worker '$name' is of class '" . get_class($definition) . "' which does not implement \\Beelzebub\\Worker"
            );
        }

        if ($definition->getName() !== $name) {
            throw new \BadMethodCallException("Worker name '{$definition->getName()}' and register name '$name' do not match");
        }

        return $definition;
    }

    /**
     * Sets up defaults for process manager, logger and event dispatcher, if not given
     */
    protected function assureDefaults()
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
    }


}