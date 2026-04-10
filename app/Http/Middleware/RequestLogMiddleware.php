<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\LogContext;
use App\Core\Logger;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Middleware PSR-15 de logging de requests.
 *
 * Por cada request:
 * 1. Resetea LogContext (limpia contexto de request anterior)
 * 2. Genera un request_id único (16 hex chars = 64 bits de entropía)
 * 3. Popula LogContext con method, path y request_id
 * 4. Procesa el request y mide duración
 * 5. Loguea el resultado (method, path, status, duration_ms)
 * 6. Resetea LogContext para liberar memoria
 *
 * Debe colocarse después de SecurityHeadersMiddleware y antes de errorHandler
 * para que el request_id esté disponible durante el manejo de errores.
 */
final class RequestLogMiddleware implements MiddlewareInterface
{
    #[\Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        LogContext::reset();

        $requestId = bin2hex(random_bytes(8));
        LogContext::set('request_id', $requestId);
        LogContext::set('method', $request->getMethod());
        LogContext::set('path', $request->getUri()->getPath());

        $start = hrtime(true);

        $response = $handler->handle($request);

        $durationMs = (int) ((hrtime(true) - $start) / 1_000_000);

        Logger::channel('http')->info(
            $request->getMethod() . ' ' . $request->getUri()->getPath(),
            [
                'status'      => $response->getStatusCode(),
                'duration_ms' => $durationMs,
            ]
        );

        LogContext::reset();

        return $response;
    }
}
