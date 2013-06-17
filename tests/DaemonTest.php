<?php

namespace Fortrabbit\Beelzebub\Test;

use Fortrabbit\Beelzebub\Worker;
use Fortrabbit\Beelzebub\DaemonInterface;
use Fortrabbit\Beelzebub\Test\DummyDaemon;


class DaemonTest extends \PHPUnit_Framework_TestCase
{

    protected function setUp()
    {
    }



    public function testRegisterWorker()
    {
        $daemon  = new DummyDaemon();
        $daemon->registerWorker(array(
            'test1' => array(
                'loop'     => function() {},
                'interval' => 1
            )
        ));
        $worker = $daemon->getWorker('test1');
        $this->assertNotNull($worker);
        $this->assertEquals(get_class($worker), 'Fortrabbit\\Beelzebub\\Worker');
        $this->assertContains('Fortrabbit\\Beelzebub\\WorkerInterface', class_implements(get_class($worker)));
    }


    public function testRemoveWorker()
    {
        $daemon  = new DummyDaemon();
        $daemon->registerWorker(array(
            'test1' => array(
                'loop'     => function() {},
                'interval' => 1
            )
        ));
        $daemon->unregisterWorker('test1');
        $worker = $daemon->getWorker('test1');
        $this->assertNull($worker);
    }


}
