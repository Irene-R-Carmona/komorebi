<?php

declare(strict_types=1);

/**
 * Front Controller — Komorebi Café
 *
 * Soporta dos modos de ejecución:
 *   - Normal (PHP-FPM / CLI): ejecución única por request, comportamiento clásico.
 *   - Worker Mode (FrankenPHP): el proceso arranca UNA VEZ y sirve múltiples requests
 *     en un bucle; el bootstrap pesado (container, routes, compilación PHP-DI) ocurre
 *     solo al inicio del proceso, reduciendo drásticamente la latencia de cada request.
 *
 * Arquitectura 12-Factor:
 *   - Configuración desde variables de entorno (Factor III)
 *   - Logs a stdout/stderr (Factor XI)
 *   - Procesos stateless — sesiones en Redis/DB (Factor VI)
 */

// ============================================================================
// SECCIÓN A — ARRANQUE DEL PROCESO (se ejecuta una sola vez en Worker Mode)
// ============================================================================

// --- Errores ----------------------------------------------------------------
$env = $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? getenv('APP_ENV') ?: 'production';
$isProduction = $env === 'production';

error_reporting($isProduction ? E_ALL & ~E_DEPRECATED & ~E_STRICT : E_ALL);
ini_set('display_errors', $isProduction ? '0' : '1');
ini_set('display_startup_errors', $isProduction ? '0' : '1');
ini_set('log_errors', '1');
ini_set('error_log', 'php://stderr');

// --- Autoload ---------------------------------------------------------------
require_once __DIR__ . '/../vendor/autoload.php';

// --- Error tracking (Sentry — opcional) ------------------------------------
if (
    ($dsn = ($_ENV['SENTRY_DSN'] ?? $_SERVER['SENTRY_DSN'] ?? getenv('SENTRY_DSN') ?: ''))
    && function_exists('\Sentry\init')
) {
    \Sentry\init(['dsn' => $dsn, 'environment' => $env]);
}

use App\Core\Config;
use App\Core\Container;
use App\Core\Csrf;
use App\Core\ExceptionHandler;
use App\Core\Http\RequestFactory;
use App\Core\Http\ResponseEmitter;
use App\Core\Http\ResponseFactory;
use App\Core\Logger;
use App\Core\MiddlewareFactory;
use App\Core\MiddlewarePipeline;

// --- Configuración 12-Factor ------------------------------------------------
try {
    Config::init();
} catch (RuntimeException $e) {
    http_response_code(500);
    error_log('[CONFIG ERROR] ' . $e->getMessage());
    echo $isProduction
        ? 'Error interno del servidor'
        : '<h1>Error de Configuración</h1><pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
    exit(1);
}

ExceptionHandler::register();
date_default_timezone_set(Config::getString('app.timezone', 'UTC'));

// --- PHP-DI: compilación de container (solo producción) ---------------------
if ($isProduction) {
    Container::enableCompilation(__DIR__ . '/../storage/cache/di');
}

// --- Service Providers (Database, Cache, Events, Queue…) -------------------
require_once __DIR__ . '/../bootstrap/container.php';

// --- INI de sesión: valores estáticos por proceso ---------------------------
$sessionDriver = Config::getString('session.driver', 'file');

if ($sessionDriver === 'redis') {
    $redisHost = Config::getString('cache.redis.host', 'localhost');
    $redisPort = Config::getInt('cache.redis.port', 6379);
    $redisPass = Config::getString('cache.redis.password', '');
    $savePath = "tcp://$redisHost:$redisPort" . ($redisPass !== '' ? "?auth=$redisPass" : '');
    ini_set('session.save_handler', 'redis');
    ini_set('session.save_path', $savePath);
} else {
    if ($isProduction) {
        Logger::warning('Usando session.files en producción (no recomendado para 12-Factor)');
    }
    ini_set('session.save_path', '/tmp/sessions');
    if (!\is_dir('/tmp/sessions')) {
        try {
            \mkdir('/tmp/sessions', 0o777, true);
        } catch (ErrorException) {
            // Race condition: otro worker ya creó el directorio — ignorar
        }
        if (!\is_dir('/tmp/sessions')) {
            throw new RuntimeException('No se pudo crear /tmp/sessions');
        }
    }
}

ini_set('session.use_only_cookies', '1');
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_lifetime', '0');
ini_set('session.gc_maxlifetime', (string) (Config::getInt('session.lifetime', 120) * 60));
ini_set('session.name', 'komorebi_session');

// --- Router (cargado una vez por proceso) -----------------------------------
$router = require __DIR__ . '/../app/routes.php';

// Límite de requests por worker para controlar el crecimiento de memoria
$maxRequests = (int) (getenv('MAX_REQUESTS') ?: 500);
$requestsHandled = 0;

// ============================================================================
// SECCIÓN B — HANDLER PER-REQUEST
// ============================================================================

$handler = static function () use ($router, $isProduction): void {
    // Buffer de salida aislado por request (evita output accidental en headers)
    ob_start();

    // HTTPS se detecta por request (puede cambiar si hay proxy intermedio)
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
        || (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on');

    ini_set('session.cookie_secure', $isHttps ? '1' : '0');

    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    Csrf::init();

    try {
        $request = RequestFactory::fromGlobals();
        $pipeline = new MiddlewarePipeline($router);

        $pipeline->pipe(new \App\Middleware\SecurityHeadersMiddleware());
        $mwFactory = new MiddlewareFactory(new ResponseFactory());
        $pipeline->pipe($mwFactory->requestLog());
        $pipeline->pipe($mwFactory->errorHandler());

        $response = $pipeline->handle($request);
        new ResponseEmitter()->emit($response);
    } catch (Throwable $e) {
        ExceptionHandler::handle($e);
    } finally {
        // Persistir y cerrar sesión ANTES de que FrankenPHP reinicie el contexto
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        // Limpiar $_SESSION para evitar fuga de datos entre requests en Worker Mode
        $_SESSION = [];
        if (ob_get_level()) {
            @ob_end_flush();
        }
    }
};

// ============================================================================
// SECCIÓN C — BUCLE WORKER O EJECUCIÓN ÚNICA
// ============================================================================

if (function_exists('frankenphp_handle_request')) {
    // Worker Mode: reutilizar el proceso hasta MAX_REQUESTS y luego reciclar
    while (frankenphp_handle_request($handler) && ++$requestsHandled < $maxRequests) {
        gc_collect_cycles();
    }
} else {
    // Modo normal (PHP-FPM, CLI, tests): ejecución única por invocación
    $handler();
    register_shutdown_function(static function (): void {
        if (ob_get_level()) {
            @ob_end_flush();
        }
    });
}
