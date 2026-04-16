<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Http\ResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Middleware que rechaza peticiones cuyo cuerpo supere el límite configurado.
 *
 * Evalúa el header Content-Length. Si está ausente o es cero, deja pasar.
 * Retorna 413 Payload Too Large cuando se supera el límite.
 */
final class PayloadSizeMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly ResponseFactory $response,
        private readonly int $maxKilobytes = 256,
    ) {
    }

    #[\Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $contentLength = (int) $request->getHeaderLine('Content-Length');

        if ($contentLength > 0 && $contentLength > $this->maxKilobytes * 1024) {
            return $this->response->json(
                ['error' => "Payload demasiado grande. Máximo {$this->maxKilobytes} KB."],
                413,
            );
        }

        return $handler->handle($request);
    }
}
