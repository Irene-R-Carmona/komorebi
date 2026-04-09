<?php

declare(strict_types=1);

namespace App\Http\ExceptionRenderers;

use App\Core\Env;
use App\Core\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Renderer de último recurso: captura cualquier Throwable no cubierto.
 * Siempre retorna 500. Prioridad mínima (1) para ceder ante renderers específicos.
 */
final class FallbackExceptionRenderer extends AbstractExceptionRenderer
{
    #[\Override]
    public function supports(\Throwable $e): bool
    {
        return true;
    }

    #[\Override]
    public function priority(): int
    {
        return 1;
    }

    #[\Override]
    public function render(\Throwable $e, ServerRequestInterface $request): ResponseInterface
    {
        $isDebug = (bool) (Env::get('APP_DEBUG', '') ?: (Env::get('APP_ENV', '') !== 'production'));

        if ($this->isApiRequest($request)) {
            return $this->response->json(['error' => 'Error interno del servidor.'], 500);
        }

        $html = View::renderToString('errors/500', [
            'message'      => $isDebug ? $e->getMessage() : 'Error interno del servidor.',
            'show_details' => $isDebug,
        ]);

        return $this->response->html($html, 500);
    }
}
