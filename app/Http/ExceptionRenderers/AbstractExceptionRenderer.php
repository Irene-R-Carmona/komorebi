<?php

declare(strict_types=1);

namespace App\Http\ExceptionRenderers;

use App\Core\Http\ExceptionRendererInterface;
use App\Core\Http\ResponseFactory;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Base para Renderers de excepciones.
 * Provee inyección de ResponseFactory y detección de petición API.
 */
abstract class AbstractExceptionRenderer implements ExceptionRendererInterface
{
    public function __construct(
        protected readonly ResponseFactory $response,
    ) {}

    /**
     * Detecta si la petición espera respuesta JSON (API/AJAX).
     */
    protected function isApiRequest(ServerRequestInterface $request): bool
    {
        $accept         = $request->getHeaderLine('Accept');
        $contentType    = $request->getHeaderLine('Content-Type');
        $xRequestedWith = $request->getHeaderLine('X-Requested-With');
        $path           = $request->getUri()->getPath();

        return \str_contains($accept, 'application/json')
            || \str_contains($contentType, 'application/json')
            || \strtolower($xRequestedWith) === 'xmlhttprequest'
            || \str_starts_with($path, '/api/');
    }
}
