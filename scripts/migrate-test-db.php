<?php

declare(strict_types=1);

/**
 * Script temporal para aplicar migraciones a komorebi_test.
 * Uso: php scripts/migrate-test-db.php
 */

$host = 'db';
$dbname = 'komorebi_test';
$user = \getenv('MIGRATE_DB_USER') ?: 'komorebi_user';
$pass = \getenv('MIGRATE_DB_PASS') ?: 'komorebi';

$pdo = new PDO(
    "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
    $user,
    $pass,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
    ]
);

$migrationDir = __DIR__ . '/../migrations';
$files = glob($migrationDir . '/*.sql');
\sort($files);

foreach ($files as $file) {
    $sql = \file_get_contents($file);
    if ($sql === false) {
        echo "ERROR: No se pudo leer {$file}\n";
        continue;
    }
    try {
        $pdo->exec($sql);
        echo "OK: " . \basename($file) . "\n";
    } catch (\PDOException $e) {
        echo "ERROR en " . \basename($file) . ": " . $e->getMessage() . "\n";
    }
}

echo "Migraciones completadas.\n";
