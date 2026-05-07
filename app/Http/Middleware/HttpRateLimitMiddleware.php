<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Env;
use App\Core\Http\ResponseFactory;
use App\Core\Result;
use App\Core\ServiceErrorCode;
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
    ) {}

    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Determinar identificador para rate limiting
        $userId = $request->getAttribute('user_id');

        if ($userId !== null) {
            // Usuario autenticado: identificar por user_id (no por IP)
            $identifier = 'user:' . $userId;
            $ip = (string) ($request->getServerParams()['REMOTE_ADDR'] ?? '0.0.0.0');
        } else {
            // Usuario anónimo: respetar X-Forwarded-For solo si viene de un proxy de confianza
            $remoteAddr = (string) ($request->getServerParams()['REMOTE_ADDR'] ?? '0.0.0.0');
            $trustedProxy = Env::get('TRUSTED_PROXY_IP', '');

            if ($trustedProxy !== '' && $remoteAddr === $trustedProxy) {
                $forwarded = $request->getHeaderLine('X-Forwarded-For');
                $ip = \trim(\explode(',', $forwarded)[0]) ?: $remoteAddr;
            } else {
                $ip = $remoteAddr;
            }

            $identifier = $ip;
        }

        $status = $this->rateLimitingService->isBlocked($this->action, $identifier);

        if ($status['blocked']) {
            $retryAfterSeconds = ($status['minutes_remaining'] ?? 1) * 60;

            return $this->response->problem(
                Result::fail('Demasiadas peticiones. Por favor espera antes de reintentar.', ServiceErrorCode::RATE_LIMIT),
                429,
            )->withHeader('Retry-After', (string) $retryAfterSeconds);
        }

        $this->rateLimitingService->recordAttempt($this->action, $identifier);

        return $handler->handle($request);
    }
}
