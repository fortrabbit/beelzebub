<?php
/**
 * This class is part of Beelzebub
 */

if (!function_exists('setproctitle')) {
    function setproctitle($string) {
        $GLOBALS['THENAME'] = $string;
    }
}