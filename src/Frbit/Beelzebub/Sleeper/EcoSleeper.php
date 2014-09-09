<?php


namespace Frbit\Beelzebub\Sleeper;

use Frbit\Beelzebub\Helper\BuiltInDouble;
use Frbit\Beelzebub\Sleeper;

/**
 * Since the PHP sleep() method does some nasty amount of rt_sigprocmask calls: This sleeper
 * utilizes the "sleep" shell command to do the actual sleeping.
 *
 * @see     https://secure.phabricator.com/T4627
 *
 *
 * @package Frbit\Beelzebub\Sleeper
 **/
class EcoSleeper implements Sleeper
{
    /**
     * @var BuiltInDouble
     */
    protected $builtInDouble;
    /**
     * @var float
     */
    protected $fuzziness;
    /**
     * @var float
     */
    protected $pauseEvery;

    /**
     * Class constructor
     *
     * @param float         $fuzziness
     * @param float         $pauseEvery
     * @param BuiltInDouble $builtInDouble
     */
    public function __construct($fuzziness = 1.0, $pauseEvery = 1.0, BuiltInDouble $builtInDouble = null)
    {
        $this->fuzziness     = $fuzziness;
        $this->pauseEvery    = $pauseEvery;
        $this->builtInDouble = $builtInDouble ?: new BuiltInDouble;
    }

    /**
     * Sleep for given length
     *
     * @param float $time
     */
    public function sleep($time)
    {
        if ($this->fuzziness < 1.0) {
            $timeMin = $this->fuzziness * $time;
            $timeMax = (1 + (1 - $this->fuzziness)) * $time;
            $time    = mt_rand($timeMin * 1000, $timeMax * 1000) / 1000;
        }
        $this->sleepOnce($time);
    }

    /**
     * @param float $interval
     */
    protected function sleepOnce($interval)
    {
        if ($interval > $this->pauseEvery) {
            $intervals = [];
            while ($interval > 0) {
                $intervals [] = $this->pauseEvery < $interval ? $this->pauseEvery : $interval;
                $interval -= $this->pauseEvery;
            }
        } else {
            $intervals = [$interval];
        }
        $end = microtime(true);
        foreach ($intervals as $iv) {
            $end += $iv;
            if ($end <= microtime(true) + 0.01) {
                continue;
            }
            try {
                $this->builtInDouble->time_sleep_until($end);
            } catch (\ErrorException $e) {
                // ignore error here, since this probably means between determining end time
                //  and then sleeping to end time the end time is already reached
                continue;
            }
        }
    }
}
