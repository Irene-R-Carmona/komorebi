<?php

declare(strict_types=1);

namespace App\Http\ExceptionRenderers;

use App\Core\Env;
use App\Core\View;
use App\Exceptions\DatabaseException;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * Renderiza DatabaseException → 500.
 * Nunca expone detalles de BD en producción.
 * API: JSON genérico.
 * HTML: vista errors/500.
 */
final class DatabaseExceptionRenderer extends AbstractExceptionRenderer
{
    #[Override]
    public function supports(Throwable $e): bool
    {
        return $e instanceof DatabaseException;
    }

    #[Override]
    public function priority(): int
    {
        return 60;
    }

    #[Override]
    public function render(Throwable $e, ServerRequestInterface $request): ResponseInterface
    {
        \assert($e instanceof DatabaseException);

        $isDebug = (bool) (Env::get('APP_DEBUG', '') ?: (Env::get('APP_ENV', '') !== 'production'));
        $message = $isDebug
            ? $e->getMessage()
            : 'Error interno del servidor. Por favor, intenta de nuevo.';

        if ($this->isApiRequest($request)) {
            return $this->response->json(['error' => $message], 500);
        }

        $html = View::renderToString('errors/500', [
            'message' => $message,
            'show_details' => $isDebug,
        ], [], 'errors');

        return $this->response->html($html, 500);
    }
}
