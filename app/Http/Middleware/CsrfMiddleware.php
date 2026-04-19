<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Csrf;
use App\Core\Http\ResponseFactory;
use Exception;
use JsonException;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Middleware PSR-15 para protección CSRF.
 *
 * Verifica token CSRF en peticiones POST/PUT/DELETE/PATCH.
 */
final class CsrfMiddleware implements MiddlewareInterface
{
    private ResponseFactory $response;

    public function __construct(ResponseFactory $response)
    {
        $this->response = $response;
    }

    /**
     * @throws JsonException
     */
    #[Override]
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $method = $request->getMethod();

        if (\in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'], true)) {
            try {
                if (!Csrf::validate($request)) {
                    return $this->response->json([
                        'error' => 'Token CSRF inválido o expirado.',
                    ], 403);
                }
            } catch (Exception) {
                return $this->response->json([
                    'error' => 'Token CSRF inválido o expirado.',
                ], 403);
            }
        }

        return $handler->handle($request);
    }
}
