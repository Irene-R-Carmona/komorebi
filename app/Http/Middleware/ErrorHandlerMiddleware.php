<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\ExceptionLogger;
use App\Core\Http\ExceptionRendererRegistry;
use App\Core\Http\ResponseFactory;
use App\Core\WideEvent;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

/**
 * Middleware PSR-15 que captura Throwable no manejados del pipeline.
 *
 * Delega el renderizado al ExceptionRendererRegistry.
 * Si ningún renderer soporta la excepción, retorna 500 JSON genérico.
 */
final class ErrorHandlerMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly ExceptionRendererRegistry $registry,
        private readonly ResponseFactory $response,
    ) {
    }

    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (Throwable $e) {
            WideEvent::setSection('error', [
                'type' => \get_class($e),
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'file' => \basename($e->getFile()),
                'line' => $e->getLine(),
            ]);

            ExceptionLogger::log($e);

            $renderer = $this->registry->find($e);

            if ($renderer !== null) {
                return $renderer->render($e, $request);
            }

            return $this->response->json(['error' => 'Error interno del servidor.'], 500);
        }
    }
}
