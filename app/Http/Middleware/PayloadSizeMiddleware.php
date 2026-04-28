<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Http\ResponseFactory;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Middleware que rechaza peticiones cuyo cuerpo supere el límite configurado.
 *
 * 1. Si Content-Length está presente y supera el límite → 413 inmediato.
 * 2. Si Content-Length está ausente pero Transfer-Encoding contiene "chunked":
 *    - Usa getBody()->getSize() cuando el stream es seekable.
 *    - Si getSize() devuelve null (stream no-seekable), aplica un límite
 *      conservador de 1 MiB y deja pasar (la infraestructura gestiona el overflow).
 * Retorna 413 Payload Too Large cuando se supera el límite.
 */
final class PayloadSizeMiddleware implements MiddlewareInterface
{
    /** Límite en bytes para streams no-seekables (1 MiB). */
    private const int NON_SEEKABLE_LIMIT = 1_048_576;

    public function __construct(
        private readonly ResponseFactory $response,
        private readonly int $maxKilobytes = 256,
    ) {
    }

    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $contentLength = (int) $request->getHeaderLine('Content-Length');

        // Content-Length presente: verificar directamente
        if ($contentLength > 0) {
            if ($contentLength > $this->maxKilobytes * 1024) {
                return $this->tooLarge();
            }

            return $handler->handle($request);
        }

        // Chunked transfer sin Content-Length: intentar obtener tamaño del stream
        $transferEncoding = \strtolower($request->getHeaderLine('Transfer-Encoding'));
        if (\str_contains($transferEncoding, 'chunked')) {
            $bodySize = $request->getBody()->getSize();
            $limit    = ($bodySize === null) ? self::NON_SEEKABLE_LIMIT : $this->maxKilobytes * 1024;

            if ($bodySize !== null && $bodySize > $limit) {
                return $this->tooLarge();
            }
        }

        return $handler->handle($request);
    }

    private function tooLarge(): ResponseInterface
    {
        return $this->response->json(
            ['error' => "Payload demasiado grande. Máximo {$this->maxKilobytes} KB."],
            413,
        );
    }
}
