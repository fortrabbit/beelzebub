<?php
/**
 * This class is part of Beelzebub
 */

namespace Beelzebub\Tests;

use Beelzebub\Daemon;
use Beelzebub\DefaultWorker;
use Beelzebub\Worker;
use PHPUnit_Framework_TestCase;

class RunDaemonTest  extends PHPUnit_Framework_TestCase
{

    public function testRunningDaemon()
    {
        $worker1 = new DefaultWorker('worker1', function (Worker $w) {
            //$d->
        });
    }

}