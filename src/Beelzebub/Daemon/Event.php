<?php
/**
 * This class is part of Beelzebub
 */

namespace Beelzebub\Daemon;


use Symfony\Component\EventDispatcher\GenericEvent;

class Event extends GenericEvent
{

    /**
     * Called when dameon (parent) has received shutdown signal right before children are shutdown (killed)
     */
    const EVENT_DAEMON_STOPPING = 'daemon.stopping';

    /**
     * Called when dameon (parent) has finished shutting down children, right before program exit
     */
    const EVENT_DAEMON_STOPPED  = 'daemon.stopped';




    /**
     * Called right before new worker instance is created
     */
    const EVENT_WORKER_STARTING = 'worker.starting';

    /**
     * Called right after worker instance (fork) has been created
     */
    const EVENT_WORKER_STARTED = 'worker.started';

    /**
     * Called when worker has received shutdown signal
     */
    const EVENT_WORKER_STOPPING = 'worker.stopping';

    /**
     * Called when worker has received shutdown signal, right before worker fork is exiting
     */
    const EVENT_WORKER_STOPPED = 'worker.stopped';

    /**
     * Called when worker did not stop after shutdown timeout period after receiving shutdown signal right before SIGKILL is sent
     */
    const EVENT_WORKER_KILL = 'worker.kill';


}