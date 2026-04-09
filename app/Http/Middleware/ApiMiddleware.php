<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Http\ResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Middleware PSR-15 para rutas API.
 *
 * Verifica que la petición sea JSON/AJAX.
 */
final class ApiMiddleware implements MiddlewareInterface
{
    private ResponseFactory $response;

    public function __construct(ResponseFactory $response)
    {
        $this->response = $response;
    }

    /**
     * @throws \JsonException
     */
    #[\Override]
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $accept = $request->getHeaderLine('Accept');
        $contentType = $request->getHeaderLine('Content-Type');
        $xRequested = $request->getHeaderLine('X-Requested-With');

        $isApi = \str_contains($accept, 'application/json')
            || \str_contains($contentType, 'application/json')
            || \strtolower($xRequested) === 'xmlhttprequest';

        if (!$isApi) {
            return $this->response->json([
                'error' => 'Se requiere petición API (Accept: application/json)',
            ], 400);
        }

        return $handler->handle($request);
    }
}
