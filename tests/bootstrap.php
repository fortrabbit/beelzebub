<?php

require_once __DIR__. '/../vendor/autoload.php';

spl_autoload_register(function($class) {
    if (preg_match('/^Fortrabbit\\\Beelzebub\\\Test\\\(.+)$/', $class, $match)) {
        $file = preg_replace('/\\\/', '/', $match[1]);
        include_once __DIR__. '/libs/'. $file. '.php';
    }
});