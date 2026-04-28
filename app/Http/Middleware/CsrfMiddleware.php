<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Csrf;
use App\Core\Http\ResponseFactory;
use App\Core\Result;
use App\Core\ServiceErrorCode;
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
            // Las peticiones autenticadas con Bearer token no necesitan CSRF
            if ($request->getAttribute('auth_method') === 'bearer') {
                return $handler->handle($request);
            }

            try {
                if (!Csrf::validate($request)) {
                    return $this->response->problem(
                        Result::fail('Token CSRF inválido o expirado.', ServiceErrorCode::FORBIDDEN),
                        403
                    );
                }
            } catch (Exception) {
                return $this->response->problem(
                    Result::fail('Token CSRF inválido o expirado.', ServiceErrorCode::FORBIDDEN),
                    403
                );
            }
        }

        return $handler->handle($request);
    }
}
