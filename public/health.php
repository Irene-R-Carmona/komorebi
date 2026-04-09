<?php

declare(strict_types=1);

/**
 * Healthcheck endpoint 12-Factor para Docker/Kubernetes.
 *
 * Verifica:
 * - Conexión a Base de Datos
 * - Conexión a Redis (si está configurado)
 * - Espacio en disco (básico)
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Config;

// Configuración mínima sin fallos si falta algo
try {
    // Intentar cargar config, pero no fallar si falta APP_KEY (solo verificar conectividad)
    if (getenv('DB_HOST')) {
        Config::init();
    }
} catch (Throwable $e) {
    // Continuar con verificación básica
}

header('Content-Type: application/json');

$checks = [];
$healthy = true;
$statusCode = 200;

// 1. Verificar Base de Datos
try {
    $dbHost = getenv('DB_HOST') ?: 'db';
    $dbName = getenv('DB_DATABASE') ?: 'komorebi';
    $dbUser = getenv('DB_USERNAME') ?: 'root';
    $dbPass = getenv('DB_PASSWORD') ?: '';

    $pdo = new PDO(
        "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4",
        $dbUser,
        $dbPass,
        [PDO::ATTR_TIMEOUT => 2, PDO::ERRMODE_EXCEPTION => false]
    );
    $pdo->query('SELECT 1');

    $checks['database'] = ['status' => 'ok', 'latency_ms' => 0];
} catch (Throwable $e) {
    $checks['database'] = ['status' => 'error', 'message' => 'Connection failed'];
    $healthy = false;
    $statusCode = 503;
}

// 2. Verificar Redis (si está configurado)
if (getenv('REDIS_HOST')) {
    try {
        $redis = new Redis();
        $redisHost = getenv('REDIS_HOST') ?: 'cache';
        $redisPort = getenv('REDIS_PORT') ?: 6379;

        $redis->connect($redisHost, $redisPort, 2);

        if ($redisPass = getenv('REDIS_PASSWORD')) {
            $redis->auth($redisPass);
        }

        $redis->ping();
        $checks['redis'] = ['status' => 'ok'];
    } catch (Throwable $e) {
        $checks['redis'] = ['status' => 'error', 'message' => 'Connection failed'];
        $healthy = false;
        $statusCode = 503;
    }
}

// 3. Espacio en disco (básico)
$freeSpace = disk_free_space(__DIR__);
$totalSpace = disk_total_space(__DIR__);
$usagePercent = $totalSpace > 0 ? round((1 - $freeSpace / $totalSpace) * 100, 2) : 0;

if ($usagePercent > 90) {
    $checks['disk'] = ['status' => 'warning', 'usage_percent' => $usagePercent];
} else {
    $checks['disk'] = ['status' => 'ok', 'usage_percent' => $usagePercent];
}

// Respuesta
http_response_code($statusCode);
echo json_encode([
    'status' => $healthy ? 'healthy' : 'unhealthy',
    'timestamp' => date('c'),
    'version' => '1.0.0',
    'checks' => $checks
], JSON_PRETTY_PRINT);