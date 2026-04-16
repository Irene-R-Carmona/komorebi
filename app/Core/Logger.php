<?php

declare(strict_types=1);

namespace App\Core;

use Monolog\Formatter\JsonFormatter;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger as Monolog;
use Psr\Log\LoggerInterface;

/**
 * Logger 12-Factor: Streams a stdout/stderr, nunca archivos en contenedores.
 *
 * Canales disponibles: app (default), http, db, queue, auth
 * En desarrollo: LineFormatter legible con context y extra visibles
 * En producción: JsonFormatter estructurado para ingesta por ELK/Loki
 *
 * Todos los logs llevan automáticamente el contexto del request (request_id,
 * method, path) inyectado por LogContextProcessor.
 */
final class Logger
{
    /** @var array<string, LoggerInterface> */
    private static array $channels = [];

    public static function get(): LoggerInterface
    {
        return self::channel('app');
    }

    public static function channel(string $name = 'app'): LoggerInterface
    {
        if (isset(self::$channels[$name])) {
            return self::$channels[$name];
        }

        $level = Env::get('LOG_LEVEL', 'info');
        $isProd = Env::get('APP_ENV', 'production') === 'production';

        $log = new Monolog($name);

        // 12-Factor: Siempre a stdout
        $handler = new StreamHandler('php://stdout', self::parseLevel($level));

        // Formato diferente según entorno
        if ($isProd) {
            $handler->setFormatter(new JsonFormatter());
        } else {
            $handler->setFormatter(new LineFormatter(
                "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
                'H:i:s',
                true,
                true
            ));
        }

        $log->pushHandler($handler);
        $log->pushProcessor(new LogContextProcessor());

        self::$channels[$name] = $log;

        return $log;
    }

    /**
     * Borra los canales cacheados. Útil en tests para aislar configuración.
     */
    public static function reset(): void
    {
        self::$channels = [];
    }

    // Proxies estáticos (canal 'app' por defecto)
    public static function emergency(string $msg, array $ctx = []): void
    {
        self::get()->emergency($msg, $ctx);
    }

    public static function alert(string $msg, array $ctx = []): void
    {
        self::get()->alert($msg, $ctx);
    }

    public static function critical(string $msg, array $ctx = []): void
    {
        self::get()->critical($msg, $ctx);
    }

    public static function error(string $msg, array $ctx = []): void
    {
        self::get()->error($msg, $ctx);
    }

    public static function warning(string $msg, array $ctx = []): void
    {
        self::get()->warning($msg, $ctx);
    }

    public static function notice(string $msg, array $ctx = []): void
    {
        self::get()->notice($msg, $ctx);
    }

    public static function info(string $msg, array $ctx = []): void
    {
        self::get()->info($msg, $ctx);
    }

    public static function debug(string $msg, array $ctx = []): void
    {
        self::get()->debug($msg, $ctx);
    }

    private static function parseLevel(string $level): Level
    {
        $name = \strtoupper($level);

        try {
            return Level::fromName($name);
        } catch (\TypeError | \ValueError $e) {
            return Level::Info;
        }
    }
}
