<?php

declare(strict_types=1);

namespace App\Core;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger as Monolog;
use Monolog\Level;
use Psr\Log\LoggerInterface;

/**
 * Logger 12-Factor: Streams a stdout/stderr, nunca archivos en contenedores.
 *
 * En desarrollo: Formato legible
 * En producción: Preparado para JSON (si se configura LOG_FORMAT=json)
 */
final class Logger
{
    private static ?LoggerInterface $instance = null;

    public static function get(): LoggerInterface
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $level = Config::getString('logging.level', 'info');
        $isProd = Config::getString('app.env', 'production') === 'production';

        $log = new Monolog('komorebi');

        // 12-Factor: Siempre a stdout
        $handler = new StreamHandler('php://stdout', self::parseLevel($level));

        // Formato diferente según entorno
        if ($isProd) {
            $handler->setFormatter(new LineFormatter(
                "[%datetime%] %channel%.%level_name%: %message% %context%\n",
                'Y-m-d H:i:s'
            ));
        } else {
            $handler->setFormatter(new LineFormatter(
                "[%datetime%] %level_name%: %message%\n",
                'H:i:s'
            ));
        }

        $log->pushHandler($handler);
        self::$instance = $log;

        return $log;
    }

    // Proxies estáticos
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
        $name = strtoupper($level);
        try {
            return Level::fromName($name);
        } catch (\TypeError | \ValueError $e) {
            return Level::Info;
        }
    }
}
