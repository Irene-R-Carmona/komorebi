<?php

declare(strict_types=1);

/**
 * OPcache preload script.
 *
 * Warms up the OPcache at PHP startup by compiling the Composer autoloader
 * and the most frequently-hit framework core files.
 *
 * Referenced by opcache.ini → opcache.preload=/app/scripts/opcache-preload.php
 */

$autoload = '/app/vendor/autoload.php';

if (!\file_exists($autoload)) {
    return;
}

require_once $autoload;

$coreFiles = [
    '/app/app/Core/Container.php',
    '/app/app/Core/Router.php',
    '/app/app/Core/Request.php',
    '/app/app/Core/Result.php',
    '/app/app/Core/View.php',
    '/app/app/Core/Logger.php',
    '/app/app/Core/Cache.php',
    '/app/app/Core/Session.php',
    '/app/app/Core/Raw.php',
    '/app/app/Core/Env.php',
    '/app/app/Core/Flash.php',
    '/app/app/Core/Database.php',
];

foreach ($coreFiles as $file) {
    if (\file_exists($file)) {
        \opcache_compile_file($file);
    }
}
