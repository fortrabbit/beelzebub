<?php
/**
 * This class is part of Beelzebub
 */

namespace Beelzebub\Tests;

use Beelzebub\Daemon\Builder;
use Beelzebub\Worker;
use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Spork\Exception\ProcessControlException;
use Spork\Fork;
use Spork\ProcessManager;

class RunDaemonTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ProcessManager
     */
    protected $outerManager;

    /**
     * @var string
     */
    protected $tempFile;

    public function setUp()
    {
        $this->outerManager = new ProcessManager();
    }

    public function tearDown()
    {
        if ($this->tempFile && file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
        try {
            unset($this->outerManager);
        } catch (ProcessControlException $e) {

        }
    }

    public function testRunningDaemonWithSimpleWorker()
    {
        $writer = $this->getFileWriter();
        $fork = $this->outerManager->fork(function () use($writer) {
            $handler = new NullHandler();
            $builder = new Builder(array(
                'worker1' => function () use ($writer) {
                    $writer("worker");
                }
            ));
            $daemon = $builder
                ->setLogger(new Logger('test', array($handler)))
                ->build();
            $daemon->setProcessName('testing');
            $daemon->run();
        });

        sleep(1);
        $fork->kill(SIGQUIT);
        while (posix_kill($fork->getPid(), 0)) {
            pcntl_waitpid($fork->getPid(), $status, WNOHANG | WUNTRACED);
            usleep(100000);
        }

        $content = file_get_contents($this->tempFile);
        $this->assertSame("worker\n", $content);
    }

    public function testRunningDaemonWithResistingWorker()
    {
        $writer = $this->getFileWriter();
        $fork = $this->outerManager->fork(function () use ($writer) {
            $handler = new NullHandler();
            $builder = new Builder(array(
                'worker1' => function () use ($writer) {
                    $writer("worker1.call");
                },
                'worker2' => array(
                    'startup' => function () use ($writer) {
                        pcntl_signal(SIGQUIT, SIG_IGN);
                        pcntl_signal(SIGINT, SIG_IGN);
                        $writer("worker2.startup");
                    },
                    'loop'    => function () use ($writer) {
                        $writer("worker2.call");
                    },
                    'interval' => 1
                )
            ));
            $builder
                ->setLogger(new Logger('test', array($handler)))
                ->setShutdownTimeout(3);
            $daemon = $builder->build();
            $daemon->setProcessName('testing');
            $daemon->run();
        });

        sleep(1);
        $start = time();
        $fork->kill(SIGQUIT);
        while (posix_kill($fork->getPid(), 0)) {
            pcntl_waitpid($fork->getPid(), $status, WNOHANG | WUNTRACED);
            usleep(100000);
        }
        $end  = time();
        $diff = $end - $start;
        $this->assertTrue($diff >= 2 && $diff <= 4, 'Has been killed in shutdown interval');
        $content = file_get_contents($this->tempFile);
        $this->assertSame(1, preg_match_all('/worker1\.call/', $content));
        $this->assertSame(1, preg_match_all('/worker2\.startup/', $content));

        $calls = preg_match_all('/worker2\.call/', $content);
        $this->assertTrue($calls >= 3 && $calls <= 5, 'Expected amount of worker2 calls');
    }


    private function getFileWriter() {
        $file = $this->tempFile = tempnam(sys_get_temp_dir(), 'beelzebub.integration');
        return function ($content) use ($file) {
            file_put_contents($file, "$content\n", FILE_APPEND);
        };
    }

}