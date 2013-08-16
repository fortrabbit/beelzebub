<?php

$loader = require_once __DIR__ . '/../vendor/autoload.php';
/*$loader->add('\\Beelzebub\\Tests\\', __DIR__ . '/tests/unit');
$loader->add('\\Beelzebub\\Tests\\Fixtures\\', __DIR__ . '/tests/unit');
$loader->register();*/

// composer loader does not like the unit & integration structure..
spl_autoload_register(function($class) {
    if (strpos($class, 'Beelzebub\\Tests\\') === 0) {
        foreach (array('unit', 'integration') as $folder) {
            $path = __DIR__. '/'. $folder. '/'. preg_replace('/\\\\/', '/', $class). '.php';
            if (file_exists($path)) {
                include_once($path);
            }
        }
    }
});

$kernel = \AspectMock\Kernel::getInstance();
$kernel->init([
    'debug'        => true,
    'includePaths' => [__DIR__ . '/../src']
]);