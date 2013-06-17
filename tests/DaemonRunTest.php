<?php

namespace Fortrabbit\Beelzebub\Test;

use Fortrabbit\Beelzebub\WorkerInterface;
use Fortrabbit\Beelzebub\DaemonInterface;
use Fortrabbit\Beelzebub\Test\DummyDaemon;


class DaemonRunTest extends \PHPUnit_Framework_TestCase
{

    protected function setUp()
    {
    }



    public function testRunOnce()
    {
        $daemon  = new DummyDaemon();
        $daemon->registerWorker(array(
            'test1' => array(
                'interval' => 1,
                'loop'     => function (WorkerInterface $child, DaemonInterface $daemon) {
                    $daemon->getLogger()->addDebug("Test 123");
                },
                'startup' => function (WorkerInterface $child, DaemonInterface $daemon) {
                    $daemon->getLogger()->addDebug("Test 234");
                }
            )
        ));
        $daemon->run(false);
        $output = array_map(function($item) {
            return $item['message'];
        }, $daemon->getLoggerHandler()->getRecords());
        $this->assertEquals(array(
            'Test 234',
            'Test 123'
        ), $output);
    }

    public function testRunLoop()
    {
        $daemon   = new DummyDaemon();
        $daemon->setSleepEntropy(0);
        $testfile = tempnam(__DIR__, ".output.");
        $daemon->registerWorker(array(
            'test1' => array(
                'interval' => 1,
                'amount'   => 2,
                'loop'     => function (WorkerInterface $child, DaemonInterface $daemon) use ($testfile) {
                    self::_appendToTestfile($testfile, "Loop");
                },
                'startup'  => function (WorkerInterface $child, DaemonInterface $daemon) use ($testfile) {
                    self::_appendToTestfile($testfile, "Startup");
                }
            )
        ));
        if ($pid = pcntl_fork()) {
            sleep(2);
            posix_kill($pid, SIGTERM);
            pcntl_waitpid($pid, $status);
            $this->assertEquals($status, 0);
            $content = '';
            if (file_exists($testfile)) {
                $content = file_get_contents($testfile);
                unlink($testfile);
            }
            $this->assertRegExp("/^Startup(Loop)*Startup(Loop)+$/", $content);
        } else {
            $daemon->run();
            exit;
        }
    }

    public function testRunRestart()
    {
        $daemon   = new DummyDaemon();
        $testfile = tempnam(__DIR__, ".output.");
        $daemon->registerWorker(array(
            'test1' => array(
                'interval' => 1,
                'amount'   => 1,
                'loop'     => function () use ($testfile) {
                    self::_appendToTestfile($testfile, "Test1");
                },
            )
        ));
        $daemon->setSleepEntropy(0);
        $daemon->setRestartSignal(SIGUSR1);
        $daemon->setRestartHandler(function (DaemonInterface &$d) use ($testfile) {
            self::_appendToTestfile($testfile, "Restarted");
            if ($d->unregisterWorker('test1')) {
                $d->registerWorker(array(
                    'test2' => array(
                        'interval' => 1,
                        'amount'   => 1,
                        'loop'     => function () use ($testfile) {
                            self::_appendToTestfile($testfile, "Test2");
                        },
                    )
                ));
            }
        });
        if ($pid = pcntl_fork()) {
            sleep(2);
            posix_kill($pid, SIGUSR1);
            sleep(4);
            posix_kill($pid, SIGTERM);
            pcntl_waitpid($pid, $status);
            $this->assertEquals($status, 0);
            $content = '';
            if (file_exists($testfile)) {
                $content = file_get_contents($testfile);
                unlink($testfile);
            }
            $this->assertRegExp("/^(Test1)+Restarted(Test2)+$/", $content);
        } else {
            $daemon->run();
            exit;
        }
    }

    public function _appendToTestfile($testfile, $msg)
    {
        $fh = fopen($testfile, "a");
        if (flock($fh, LOCK_EX)) {
            fwrite($fh, $msg);
            flock($fh, LOCK_UN);
            fclose($fh);
        }
    }

}
