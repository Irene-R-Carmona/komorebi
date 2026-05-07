<?php

declare(strict_types=1);

use App\Core\Env;

/**
 * Database Configuration
 *
 * En Railway, usar DATABASE_URL o MYSQL_URL en lugar de variables DB_* individuales.
 * Este archivo es referencia de configuración; la conexión real se gestiona en Database.php.
 */
return [
    'connection' => Env::get('DB_CONNECTION', 'mysql'),
    'host' => Env::get('DB_HOST', 'localhost'),
    'port' => (int) Env::get('DB_PORT', '3306'),
    'database' => Env::get('DB_DATABASE', 'komorebi_db'),
    'username' => Env::get('DB_USERNAME', 'root'),
    'password' => Env::get('DB_PASSWORD'),
    'charset' => Env::get('DB_CHARSET', 'utf8mb4'),
    'collation' => Env::get('DB_COLLATION', 'utf8mb4_unicode_ci'),
    'prefix' => '',
    'strict' => true,
    'engine' => 'InnoDB',
];
