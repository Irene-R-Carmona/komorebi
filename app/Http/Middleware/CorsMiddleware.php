<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Http\ResponseFactory;
use InvalidArgumentException;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * CORS Middleware PSR-15.
 *
 * - Peticiones sin Origin: pasa al handler sin añadir headers (no es CORS).
 * - Preflight OPTIONS con origen permitido: devuelve 204 con Access-Control-* sin llamar al handler.
 * - Preflight OPTIONS con origen no permitido: devuelve 403.
 * - Peticiones reales con origen permitido: añade Access-Control-Allow-Origin (y optionalmente Credentials).
 *
 * Configuración:
 *   allowedOrigins = ['*']                         → acepta cualquier origen (solo para dev)
 *   allowedOrigins = ['https://app.example.com']   → lista explícita
 *   credentials    = true                          → Access-Control-Allow-Credentials: true
 *                                                     (incompatible con wildcard, lanza InvalidArgumentException)
 *   maxAge         = 7200                          → Access-Control-Max-Age en segundos
 */
final class CorsMiddleware implements MiddlewareInterface
{
    private const ALLOW_METHODS = 'GET, POST, PUT, PATCH, DELETE, OPTIONS';
    private const ALLOW_HEADERS = 'Content-Type, Authorization, X-Requested-With, X-CSRF-Token';

    /** @param string[] $allowedOrigins */
    public function __construct(
        private readonly ResponseFactory $response,
        private readonly array $allowedOrigins,
        private readonly bool $credentials = false,
        private readonly int $maxAge = 7200,
    ) {
        if ($this->credentials && \in_array('*', $this->allowedOrigins, true)) {
            throw new InvalidArgumentException(
                'Access-Control-Allow-Credentials: true es incompatible con el wildcard "*" (RFC 6454 §7.2).'
            );
        }
    }

    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $origin = $request->getHeaderLine('Origin');

        // No es una petición CORS (sin Origin header) — pasar al handler tal cual
        if ($origin === '') {
            return $handler->handle($request);
        }

        $isAllowed = $this->isOriginAllowed($origin);
        $isPreflight = \strtoupper($request->getMethod()) === 'OPTIONS';

        if ($isPreflight) {
            if (!$isAllowed) {
                return $this->response->html('', 403);
            }

            return $this->buildPreflightResponse($origin);
        }

        $response = $handler->handle($request);

        if (!$isAllowed) {
            return $response;
        }

        return $this->addCorsHeaders($response, $origin);
    }

    private function isOriginAllowed(string $origin): bool
    {
        if (\in_array('*', $this->allowedOrigins, true)) {
            return true;
        }

        return \in_array($origin, $this->allowedOrigins, true);
    }

    private function buildPreflightResponse(string $origin): ResponseInterface
    {
        $allowOrigin = \in_array('*', $this->allowedOrigins, true) ? '*' : $origin;

        $response = $this->response->html('', 204)
            ->withHeader('Access-Control-Allow-Origin', $allowOrigin)
            ->withHeader('Access-Control-Allow-Methods', self::ALLOW_METHODS)
            ->withHeader('Access-Control-Allow-Headers', self::ALLOW_HEADERS)
            ->withHeader('Access-Control-Max-Age', (string) $this->maxAge)
            ->withHeader('Vary', 'Origin');

        if ($this->credentials) {
            $response = $response->withHeader('Access-Control-Allow-Credentials', 'true');
        }

        return $response;
    }

    private function addCorsHeaders(ResponseInterface $response, string $origin): ResponseInterface
    {
        $allowOrigin = \in_array('*', $this->allowedOrigins, true) ? '*' : $origin;

        $response = $response
            ->withHeader('Access-Control-Allow-Origin', $allowOrigin)
            ->withHeader('Vary', 'Origin');

        if ($this->credentials) {
            $response = $response->withHeader('Access-Control-Allow-Credentials', 'true');
        }

        return $response;
    }
}
