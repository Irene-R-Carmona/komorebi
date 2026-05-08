<?php

declare(strict_types=1);

namespace App\Core;

use App\Exceptions\AuthenticationException;
use App\Exceptions\AuthorizationException;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\ConfigurationException;
use App\Exceptions\DatabaseException;
use App\Exceptions\ExternalServiceException;
use App\Exceptions\NotFoundException;
use App\Exceptions\RateLimitException;
use App\Exceptions\ValidationException;
use App\Services\TelegramService;
use App\Support\IpHelper;
use Exception;
use Throwable;

/**
 * Sistema de Logging Estructurado para Excepciones
 *
 * Proporciona logging contextual y categorizado para todas las excepciones
 * del sistema, facilitando debugging y monitoreo.
 */
final class ExceptionLogger
{
    private const array SEVERITY_MAP = [
        ValidationException::class => 'INFO',
        NotFoundException::class => 'WARNING',
        AuthenticationException::class => 'WARNING',
        AuthorizationException::class => 'WARNING',
        BusinessRuleException::class => 'INFO',
        RateLimitException::class => 'WARNING',
        DatabaseException::class => 'ERROR',
        ConfigurationException::class => 'CRITICAL',
        ExternalServiceException::class => 'ERROR',
    ];

    /**
     * Registra una excepción con contexto estructurado
     *
     * @param Throwable   $exception Excepción a registrar
     * @param string|null $context   Contexto adicional (controlador, servicio, etc.)
     *
     * @return void
     */
    public static function log(Throwable $exception, ?string $context = null): void
    {
        $severity = self::getSeverity($exception);
        $contextData = \array_merge(
            self::extractContext($exception),
            [
                'context' => $context ?? 'SYSTEM',
                'exception_class' => \get_class($exception),
                'file' => \basename($exception->getFile()),
                'line' => $exception->getLine(),
            ]
        );

        $channel = Logger::channel('app');

        match ($severity) {
            'CRITICAL' => $channel->critical($exception->getMessage(), $contextData),
            'ERROR' => $channel->error($exception->getMessage(), $contextData),
            'WARNING' => $channel->warning($exception->getMessage(), $contextData),
            default => $channel->info($exception->getMessage(), $contextData),
        };

        if ($severity === 'CRITICAL') {
            self::notifyCriticalError($exception, $context);
        }
    }

    /**
     * Registra una excepción de forma compacta (para catch blocks)
     *
     * @param Throwable $exception
     * @param string    $action    Acción que se estaba realizando
     * @param array     $data      Datos relacionados
     *
     * @return void
     */
    public static function logQuick(Throwable $exception, string $action, array $data = []): void
    {
        Logger::error($exception->getMessage(), [
            'action' => $action,
            'exception' => \get_class($exception),
            'data' => $data,
            'file' => \basename($exception->getFile()),
            'line' => $exception->getLine(),
        ]);
    }

    /**
     * Determina la severidad de la excepción
     */
    private static function getSeverity(Throwable $exception): string
    {
        $class = \get_class($exception);

        return self::SEVERITY_MAP[$class] ?? 'ERROR';
    }

    /**
     * Extrae contexto de excepciones personalizadas
     */
    private static function extractContext(Throwable $exception): array
    {
        $context = [
            'type' => \get_class($exception),
            'code' => $exception->getCode(),
        ];

        // Extraer contexto específico según tipo de excepción
        $context = \array_merge($context, self::extractExceptionData($exception));

        // Información de sesión si existe (GDPR - anonimización)
        $context = \array_merge($context, self::extractSessionData());

        // Información de request (GDPR - anonimización IP)
        $context['request'] = self::extractRequestData();

        return $context;
    }

    /**
     * Extrae datos específicos de cada tipo de excepción
     */
    private static function extractExceptionData(Throwable $exception): array
    {
        if ($exception instanceof ValidationException) {
            return [
                'errors' => $exception->getErrors(),
                'http_code' => $exception->getHttpCode(),
            ];
        }

        if ($exception instanceof NotFoundException) {
            return [
                'resource_type' => $exception->getResourceType(),
                'resource_id' => $exception->getResourceId(),
                'http_code' => $exception->getHttpCode(),
            ];
        }

        if ($exception instanceof AuthenticationException) {
            return [
                'reason' => $exception->getReason(),
                'http_code' => $exception->getHttpCode(),
            ];
        }

        if ($exception instanceof AuthorizationException) {
            return [
                'permission' => $exception->getPermission(),
                'resource' => $exception->getResource(),
                'http_code' => $exception->getHttpCode(),
            ];
        }

        if ($exception instanceof BusinessRuleException) {
            return [
                'rule_code' => $exception->getRuleCode(),
                'business_context' => $exception->getContext(),
                'http_code' => $exception->getHttpCode(),
            ];
        }

        if ($exception instanceof DatabaseException) {
            return [
                'query' => $exception->getQuery(),
                'params' => $exception->getParams(),
                'http_code' => $exception->getHttpCode(),
            ];
        }

        if ($exception instanceof RateLimitException) {
            return [
                'retry_after' => $exception->getRetryAfter(),
                'limit' => $exception->getLimit(),
                'action' => $exception->getAction(),
                'http_code' => $exception->getHttpCode(),
            ];
        }

        if ($exception instanceof ConfigurationException) {
            return [
                'config_key' => $exception->getConfigKey(),
                'http_code' => $exception->getHttpCode(),
            ];
        }

        if ($exception instanceof ExternalServiceException) {
            return [
                'service_name' => $exception->getServiceName(),
                'service_http_code' => $exception->getServiceHttpCode(),
                'http_code' => $exception->getHttpCode(),
            ];
        }

        return [];
    }

    /**
     * Extrae datos de sesión con anonimización GDPR
     */
    private static function extractSessionData(): array
    {
        if (!Session::has('user_id')) {
            return [];
        }

        $email = Session::get('user_email');

        return [
            'user_id' => Session::get('user_id'),
            'user_email_hash' => $email ? \hash('sha256', $email) : null,
        ];
    }

    /**
     * Extrae datos de request con anonimización IP
     */
    private static function extractRequestData(): array
    {
        return [
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
            'uri' => $_SERVER['REQUEST_URI'] ?? 'N/A',
            'ip' => self::anonymizeIp(IpHelper::resolve($_SERVER)),
        ];
    }

    /**
     * Anonimiza dirección IP según GDPR Art. 5 (minimización de datos)
     *
     * IPv4: 192.168.1.50 → 192.168.0.0
     * IPv6: 2001:db8::1 → 2001:db8::
     *
     * @param string $ip Dirección IP original
     *
     * @return string IP anonimizada
     */
    private static function anonymizeIp(string $ip): string
    {
        if ($ip === 'N/A' || $ip === 'CLI') {
            return $ip;
        }

        // IPv4: Eliminar últimos 2 octetos
        if (\filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = \explode('.', $ip);

            return $parts[0] . '.' . $parts[1] . '.0.0';
        }

        // IPv6: Eliminar últimos 64 bits
        if (\filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $parts = \explode(':', $ip);

            return \implode(':', \array_slice($parts, 0, 4)) . '::';
        }

        return 'INVALID_IP';
    }

    /**
     * Notifica errores críticos (email, Telegram, Slack, etc.)
     */
    private static function notifyCriticalError(Throwable $exception, ?string $context): void
    {
        // Solo notificar en producción
        if (Env::get('APP_ENV') !== 'production') {
            return;
        }

        $message = \sprintf(
            "ERROR CRÍTICO en %s\n\n" .
                "Tipo: %s\n" .
                "Mensaje: %s\n" .
                "Archivo: %s:%d\n" .
                "Contexto: %s\n" .
                'Hora: %s',
            Env::get('APP_NAME', 'Komorebi'),
            \get_class($exception),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $context ?? 'N/A',
            \date('Y-m-d H:i:s')
        );

        // Notificación Telegram: condicional si TELEGRAM_BOT_TOKEN está configurado
        if (Env::get('TELEGRAM_BOT_TOKEN') !== '') {
            try {
                new TelegramService()->sendAlert(
                    '🚨',
                    'Error Crítico — ' . Env::get('APP_NAME', 'Komorebi'),
                    $message
                );
            } catch (Throwable) {
                // Silenciar: este método nunca debe interrumpir el manejo de excepciones
            }
        }
    }

    /**
     * Genera un ID único para tracking de errores
     */
    public static function generateErrorId(): string
    {
        return \uniqid('ERR-', true);
    }

    /**
     * Registra métricas de excepciones (para dashboard de monitoreo)
     */
    public static function recordMetrics(Throwable $exception): void
    {
        // Incrementar contador en Redis/Cache
        $key = 'metrics:exceptions:' . \date('Y-m-d');
        $exceptionType = \get_class($exception);

        try {
            Cache::increment("$key:total");
            Cache::increment("$key:type:$exceptionType");
            Cache::increment("$key:severity:" . self::getSeverity($exception));
        } catch (Exception) {
            // Silenciar errores de métricas
        }
    }

    /**
     * Obtiene estadísticas de excepciones del día
     */
    public static function getDailyStats(): array
    {
        $key = 'metrics:exceptions:' . \date('Y-m-d');

        return [
            'total' => Cache::get("$key:total", 0),
            'critical' => Cache::get("$key:severity:CRITICAL", 0),
            'errors' => Cache::get("$key:severity:ERROR", 0),
            'warnings' => Cache::get("$key:severity:WARNING", 0),
            'info' => Cache::get("$key:severity:INFO", 0),
        ];
    }

    /**
     * Limpia logs antiguos (ejecutar en cron job)
     */
    public static function cleanOldLogs(int $daysToKeep = 30): void
    {
        $logDir = __DIR__ . '/../../storage/logs/';
        $threshold = \time() - ($daysToKeep * 86400);

        if (!\is_dir($logDir)) {
            return;
        }

        $files = \glob($logDir . '*.log');

        foreach ($files as $file) {
            if (\filemtime($file) < $threshold) {
                \unlink($file);
            }
        }
    }
}
