<?php

declare(strict_types=1);

/**
 * Front Controller - Komorebi Café
 *
 * Arquitectura 12-Factor aplicada:
 * - Configuración vía variables de entorno (sin archivos .env en runtime)
 * - Logs a stdout/stderr (Factor XI)
 * - Procesos stateless (Factor VI) - Sesiones en Redis/DB
 * - Puerto vinculado por variable (Factor VII)
 * - Manejo graceful de errores y señales
 */

// CRÍTICO: Configuración de errores inmediata
// En producción: no mostrar errores al cliente (seguridad)
// En desarrollo: mostrar para debugging
$env = $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? getenv('APP_ENV') ?: 'production';
$isProduction = $env === 'production';

error_reporting($isProduction ? E_ALL & ~E_DEPRECATED & ~E_STRICT : E_ALL);
ini_set('display_errors', $isProduction ? '0' : '1');
ini_set('display_startup_errors', $isProduction ? '0' : '1');
ini_set('log_errors', '1');
ini_set('error_log', 'php://stderr'); // 12-Factor: logs a stderr

// Buffer de salida para poder enviar headers incluso si hay output accidental
if (!ob_get_level()) {
    ob_start();
}

// Autoload
require_once __DIR__ . '/../vendor/autoload.php';

// Error tracking (Sentry — optional, only when sentry/sentry installed AND SENTRY_DSN is set)
// package not in composer.json by default: composer require sentry/sentry
if (
    ($dsn = ($_ENV['SENTRY_DSN'] ?? $_SERVER['SENTRY_DSN'] ?? \getenv('SENTRY_DSN') ?: ''))
    && \function_exists('\Sentry\init')
) {
    \Sentry\init(['dsn' => $dsn, 'environment' => $env]);
}

use App\Core\Config;
use App\Core\Csrf;
use App\Core\ExceptionHandler;
use App\Core\Http\RequestFactory;
use App\Core\Http\ResponseEmitter;
use App\Core\Http\ResponseFactory;
use App\Core\Logger;
use App\Core\MiddlewareFactory;
use App\Core\MiddlewarePipeline;

// Inicializar configuración 12-Factor (desde $_ENV/getenv)
try {
    Config::init();
} catch (RuntimeException $e) {
    // Error crítico de configuración (falta variable requerida)
    http_response_code(500);
    error_log('[CONFIG ERROR] ' . $e->getMessage());

    if (!$isProduction) {
        echo '<h1>Error de Configuración</h1>';
        echo '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
    } else {
        echo 'Error interno del servidor';
    }
    exit(1);
}

// Actualizar configuración de errores basada en Config (más específico)
$logLevel = Config::getString('logging.level', 'info');
ini_set('error_reporting', (string) ($isProduction ? 0 : E_ALL));

// Registrar manejador de excepciones global
ExceptionHandler::register();

// Timezone consistente (12-Factor: logs con timestamp correcto)
date_default_timezone_set(Config::getString('app.timezone', 'UTC'));

// ============================================================================
// DEPENDENCY INJECTION: Container y Service Providers
// ============================================================================
// Cargar y registrar todos los service providers (Database, Cache, Event, etc.)
require_once __DIR__ . '/../bootstrap/container.php';

// ============================================================================
// CONFIGURACIÓN DE SESIONES (Factor VI: Stateless)
// ============================================================================
// 12-Factor: Las sesiones NO deben guardarse en filesystem del contenedor
// Opciones: Redis (recomendado), Database, o Cookie cifrada

$sessionDriver = Config::getString('session.driver', 'file');

if ($sessionDriver === 'redis') {
    // Redis: Stateless, permite escalar horizontalmente
    $redisHost = Config::getString('cache.redis.host', 'localhost');
    $redisPort = Config::getInt('cache.redis.port', 6379);
    $redisPass = Config::getString('cache.redis.password', '');

    $savePath = "tcp://$redisHost:$redisPort";
    if ($redisPass) {
        $savePath .= "?auth=$redisPass";
    }

    ini_set('session.save_handler', 'redis');
    ini_set('session.save_path', $savePath);
} elseif ($sessionDriver === 'database') {
    // Database: Alternativa si Redis no está disponible
    // Requiere tabla sessions en DB
    ini_set('session.save_handler', 'user');
    // El handler de DB se registraría aquí si lo implementas

} else {
    // File: SOLO para desarrollo local (no 12-Factor compliant en prod)
    if ($isProduction) {
        Logger::warning('Usando session.files en producción (no recomendado para 12-Factor)');
    }
    ini_set('session.save_path', '/tmp/sessions');
    if (!is_dir('/tmp/sessions') && !mkdir('/tmp/sessions', 0o777, true) && !is_dir('/tmp/sessions')) {
        throw new RuntimeException(sprintf('Directory "%s" was not created', '/tmp/sessions'));
    }
}

// Configuración segura de cookies de sesión
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
    || (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on');

ini_set('session.use_only_cookies', '1');
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_secure', $isHttps ? '1' : '0');
ini_set('session.cookie_lifetime', '0'); // Session cookie
// Obtener lifetime como entero de forma segura
$sessionLifetime = Config::getInt('session.lifetime', 120);
ini_set('session.gc_maxlifetime', (string) ($sessionLifetime * 60));

// Nombre de sesión personalizado (evita fingerprinting básico)
ini_set('session.name', 'komorebi_session');

// Iniciar sesión
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// ============================================================================
// SEGURIDAD: CSRF
// ============================================================================
Csrf::init();

// ============================================================================
// ROUTING Y DISPATCH PSR-7/PSR-15
// ============================================================================

try {
    // Crear Request PSR-7 desde superglobals
    $request = RequestFactory::fromGlobals();

    // Cargar router con rutas (RequestHandlerInterface - actúa como finalHandler)
    $router = require __DIR__ . '/../app/routes.php';

    // Crear pipeline de middleware con router como finalHandler
    $pipeline = new MiddlewarePipeline($router);

    // Añadir middlewares globales (orden importante)
    // 1. Security headers — siempre en todas las respuestas
    $pipeline->pipe(new \App\Middleware\SecurityHeadersMiddleware());
    // 2. Request logging — genera request_id, loguea method/path/status/duration
    $mwFactory = new MiddlewareFactory(new ResponseFactory());
    $pipeline->pipe($mwFactory->requestLog());
    // 3. Error handler — captura excepciones del pipeline y retorna respuestas PSR-7
    $pipeline->pipe($mwFactory->errorHandler());

    // Procesar request a través del pipeline
    $response = $pipeline->handle($request);

    // Emitir respuesta
    $emitter = new ResponseEmitter();
    $emitter->emit($response);
} catch (Throwable $e) {
    // Capturar cualquier error no manejado
    ExceptionHandler::handle($e);
}

// Flush del buffer de salida
register_shutdown_function(static function () {
    if (ob_get_level()) {
        @ob_end_flush();
    }
});
