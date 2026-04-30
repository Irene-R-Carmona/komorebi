<?php

declare(strict_types=1);

/**
 * Healthcheck endpoint 12-Factor para Docker/Kubernetes.
 *
 * Pública (sin token): {"status":"ok"} — liveness probe para k8s/Docker.
 * Con cabecera X-Health-Token válida: detalles completos de conectividad.
 *
 * Verifica (solo con token válido):
 * - Conexión a Base de Datos
 * - Conexión a Redis (si está configurado)
 * - Espacio en disco (básico)
 * - Colas de workers
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Env;

header('Content-Type: application/json');

// --- Token gate -------------------------------------------------------
$healthToken = Env::get('HEALTH_TOKEN', '');
$requestToken = $_SERVER['HTTP_X_HEALTH_TOKEN'] ?? '';
$tokenValid = $healthToken !== '' && hash_equals($healthToken, $requestToken);

if ($healthToken === '' || !$tokenValid) {
    // Liveness probe público — sin detalles internos
    echo json_encode(['status' => 'ok']);
    exit(0);
}
// ----------------------------------------------------------------------

$checks = [];
$healthy = true;
$statusCode = 200;

// 1. Verificar Base de Datos
try {
    // Railway expone MYSQL_URL como variable primaria del plugin MySQL.
    // Fallback a variables individuales DB_* para entornos locales y Docker.
    $mysqlUrl = Env::get('MYSQL_URL') ?: Env::get('DATABASE_URL') ?: '';

    if ($mysqlUrl !== '') {
        $parts = parse_url($mysqlUrl);
        $dbHost = $parts['host'] ?? 'db';
        $dbPort = (int) ($parts['port'] ?? 3306);
        $dbName = ltrim($parts['path'] ?? '/komorebi', '/');
        $dbUser = urldecode($parts['user'] ?? 'root');
        $dbPass = urldecode($parts['pass'] ?? '');
    } else {
        $dbHost = Env::get('DB_HOST', 'db');
        $dbPort = (int) Env::get('DB_PORT', '3306');
        $dbName = Env::get('DB_DATABASE', 'komorebi');
        $dbUser = Env::get('DB_USERNAME', 'root');
        $dbPass = Env::get('DB_PASSWORD', '');
    }

    $pdo = new PDO(
        "mysql:host=$dbHost;port=$dbPort;dbname=$dbName;charset=utf8mb4",
        $dbUser,
        $dbPass,
        [PDO::ATTR_TIMEOUT => 2, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $pdo->query('SELECT 1');

    $checks['database'] = ['status' => 'ok'];
} catch (Throwable $e) {
    $checks['database'] = ['status' => 'error', 'message' => 'Connection failed'];
    $healthy = false;
    $statusCode = 503;
}

// 2. Verificar Redis (si está configurado)
$redis = null;
if (Env::get('REDIS_HOST', '') !== '') {
    try {
        $redis = new Redis();
        $redisHost = Env::get('REDIS_HOST', 'cache');
        $redisPort = (int) Env::get('REDIS_PORT', '6379');

        $redis->connect($redisHost, $redisPort, 2);

        $redisPass = Env::get('REDIS_PASSWORD', '');
        if ($redisPass !== '') {
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

// 4. Verificar queues de workers (Redis debe estar disponible)
if ($redis !== null) {
    try {
        $emailPending = (int) ($redis->lLen('queue:emails') ?: 0);
        $notificationPending = (int) ($redis->lLen('queue:notifications') ?: 0);

        $queueStatus = static function (int $pending): string {
            if ($pending >= 5000) {
                return 'unhealthy';
            }
            if ($pending >= 1000) {
                return 'warning';
            }

            return 'ok';
        };

        $emailStatus = $queueStatus($emailPending);
        $notificationStatus = $queueStatus($notificationPending);

        $checks['workers'] = [
            'emails' => [
                'status' => $emailStatus,
                'pending' => $emailPending,
            ],
            'notifications' => [
                'status' => $notificationStatus,
                'pending' => $notificationPending,
            ],
        ];

        if ($emailStatus === 'unhealthy' || $notificationStatus === 'unhealthy') {
            $healthy = false;
            $statusCode = 503;
        }
    } catch (Throwable $e) {
        $checks['workers'] = ['status' => 'error', 'message' => 'Queue check failed'];
    }
}

// Respuesta
http_response_code($statusCode);
echo json_encode([
    'status' => $healthy ? 'healthy' : 'unhealthy',
    'timestamp' => date('c'),
    'version' => Env::get('APP_VERSION', 'unknown'),
    'checks' => $checks,
], JSON_PRETTY_PRINT);
