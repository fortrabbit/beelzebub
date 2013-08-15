<?php

$loader = require_once __DIR__ . '/../vendor/autoload.php';
$loader->add('\\Beelzebub\\Tests\\', __DIR__ . '/tests');
$loader->register();

$kernel = \AspectMock\Kernel::getInstance();
$kernel->init([
    'debug'        => true,
    'includePaths' => [__DIR__ . '/../src']
]);