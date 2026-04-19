<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Http\ResponseFactory;
use App\Services\Contracts\RateLimitingServiceInterface;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Middleware de rate limiting basado en IP.
 *
 * Delega la lógica de conteo y bloqueo a RateLimitingService.
 * Devuelve 429 con header Retry-After cuando el identificador está bloqueado.
 */
final class HttpRateLimitMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly ResponseFactory $response,
        private readonly RateLimitingServiceInterface $rateLimitingService,
        private readonly string $action,
    ) {
    }

    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $ip = (string) ($request->getServerParams()['REMOTE_ADDR'] ?? '0.0.0.0');

        $status = $this->rateLimitingService->isBlocked($this->action, $ip);

        if ($status['blocked']) {
            $retryAfterSeconds = ($status['minutes_remaining'] ?? 1) * 60;

            return $this->response->json(
                ['error' => 'Demasiadas peticiones. Por favor espera antes de reintentar.'],
                429,
            )->withHeader('Retry-After', (string) $retryAfterSeconds);
        }

        $this->rateLimitingService->recordAttempt($this->action, $ip, $ip);

        return $handler->handle($request);
    }
}
