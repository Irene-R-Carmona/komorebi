<?php

declare(strict_types=1);

/**
 * Database Configuration
 */
return [
    'connection' => env('DB_CONNECTION', 'mysql'),
    'host' => env('DB_HOST', 'localhost'),
    'port' => (int) env('DB_PORT', 3306),
    'database' => env('DB_DATABASE', 'komorebi_db'),
    'username' => env('DB_USERNAME', 'root'),
    'password' => env('DB_PASSWORD', ''),
    'charset' => env('DB_CHARSET', 'utf8mb4'),
    'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
    'prefix' => '',
    'strict' => true,
    'engine' => 'InnoDB',
];
