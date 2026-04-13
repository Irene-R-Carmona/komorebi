<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Cache;
use App\Core\LogContext;
use App\Core\Logger;
use App\Core\Session;
use App\Core\WideEvent;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Middleware PSR-15 de logging de requests.
 *
 * Por cada request emite un único Canonical Log Line (Wide Event) al canal 'http'
 * con el contexto completo: request, usuario, resultado y performance.
 *
 * Flujo:
 * 1. Resetea WideEvent y LogContext (aislamiento de requests anteriores)
 * 2. Genera request_id único (16 hex chars = 64 bits de entropía)
 * 3. Popula WideEvent y LogContext con contexto de request
 * 4. Procesa el request y mide duración en finally (siempre se emite)
 * 5. Añade contexto de usuario desde Session si está autenticado
 * 6. Emite el canonical event completo al canal 'http'
 * 7. Resetea WideEvent y LogContext para el siguiente request
 *
 * Controllers y services pueden enriquecer el evento llamando a
 * WideEvent::setSection() durante el ciclo de vida de la request.
 *
 * Debe colocarse después de SecurityHeadersMiddleware y antes de errorHandler
 * para que el request_id esté disponible durante el manejo de errores.
 */
final class RequestLogMiddleware implements MiddlewareInterface
{
    #[\Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        WideEvent::reset();
        LogContext::reset();

        $requestId = bin2hex(random_bytes(8));
        $serverParams = $request->getServerParams();

        // Contexto de infraestructura
        WideEvent::set('request_id', $requestId);
        WideEvent::set('timestamp', (new \DateTimeImmutable())->format(\DateTimeInterface::RFC3339_EXTENDED));
        WideEvent::set('method', $request->getMethod());
        WideEvent::set('path', $request->getUri()->getPath());
        WideEvent::set('ip', self::anonymizeIp((string) ($serverParams['REMOTE_ADDR'] ?? 'N/A')));
        WideEvent::set('user_agent', $serverParams['HTTP_USER_AGENT'] ?? null);

        // LogContext mantiene la propagación automática a otros loggers
        LogContext::set('request_id', $requestId);
        LogContext::set('method', $request->getMethod());
        LogContext::set('path', $request->getUri()->getPath());

        $start = hrtime(true);
        $parsedBody = $request->getParsedBody();

        try {
            $response = $handler->handle($request);

            WideEvent::set('status', $response->getStatusCode());
            WideEvent::set('outcome', $response->getStatusCode() < 400 ? 'success' : 'error');

            return $response;
        } finally {
            $status = isset($response) ? $response->getStatusCode() : 500;
            WideEvent::set('duration_ms', (int) ((hrtime(true) - $start) / 1_000_000));

            // Incluir body sanitizado solo en errores 4xx (útil para debug sin exponer PII)
            if ($status >= 400 && $status < 500 && is_array($parsedBody)) {
                WideEvent::setSection('request_body', self::sanitizeBody($parsedBody));
            }

            // Métricas de cache del request actual
            $cacheStats = Cache::getStats();
            if ($cacheStats['hits'] > 0 || $cacheStats['misses'] > 0) {
                WideEvent::setSection('cache', $cacheStats);
            }

            // Contexto de usuario (si está autenticado en este request)
            if (Session::isAuthenticated()) {
                WideEvent::setSection('user', [
                    'id'   => Session::userId(),
                    'role' => Session::role(),
                ]);
            }

            Logger::channel('http')->info('canonical', WideEvent::all());

            WideEvent::reset();
            LogContext::reset();
            Cache::resetStats();
        }
    }

    /**
     * Elimina campos sensibles del body para evitar filtrar PII/credenciales en logs.
     *
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    private static function sanitizeBody(array $body): array
    {
        $sensitiveKeys = [
            'password',
            'password_confirmation',
            'current_password',
            'new_password',
            'token',
            '_token',
            'cvv',
            'secret',
            'authorization',
        ];

        $sanitized = [];
        foreach ($body as $key => $value) {
            $lowerKey = strtolower((string) $key);
            $isSensitive = in_array($lowerKey, $sensitiveKeys, true)
                || str_starts_with($lowerKey, 'card_');

            $sanitized[$key] = $isSensitive ? '[REDACTED]' : $value;
        }

        return $sanitized;
    }

    /**
     * Anonimiza dirección IP según GDPR Art. 5 (minimización de datos).
     *
     * IPv4: 192.168.1.50 → 192.168.0.0
     * IPv6: 2001:db8::1 → 2001:db8::
     */
    private static function anonymizeIp(string $ip): string
    {
        if ($ip === 'N/A' || $ip === 'CLI') {
            return $ip;
        }

        if (\filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = \explode('.', $ip);

            return $parts[0] . '.' . $parts[1] . '.0.0';
        }

        if (\filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $normalized = \inet_ntop((string) \inet_pton($ip));
            if ($normalized === false) {
                return 'N/A';
            }
            $parts = \explode(':', $normalized);

            return \implode(':', \array_slice($parts, 0, 4)) . '::';
        }

        return 'N/A';
    }
}
