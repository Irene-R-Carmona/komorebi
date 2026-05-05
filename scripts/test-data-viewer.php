<?php

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');

$_SESSION = ['user_id' => 1, 'role' => 'admin'];
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../bootstrap/container.php';

try {
    $repo = \App\Core\Container::make(\App\Repositories\Contracts\StatisticsRepositoryInterface::class);
    $stats = $repo->getDataViewerStats();
    $samples = $repo->getDataViewerSamples();
    echo 'OK: ' . count($stats) . ' stats, ' . count($samples) . ' samples' . PHP_EOL;
    foreach ($samples as $k => $v) {
        echo $k . ': ' . count($v) . ' rows' . PHP_EOL;
    }
} catch (\Throwable $e) {
    echo 'ERROR: ' . $e->getMessage() . PHP_EOL;
    echo 'At: ' . $e->getFile() . ':' . $e->getLine() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
}
