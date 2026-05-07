<?php

declare(strict_types=1);

namespace App\Http\ExceptionRenderers;

use App\Core\Flash;
use App\Core\Result;
use App\Core\ServiceErrorCode;
use App\Exceptions\ValidationException;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * Renderiza ValidationException → 422.
 * API: JSON con errores por campo.
 * HTML: redirect de vuelta con mensajes Flash.
 */
final class ValidationExceptionRenderer extends AbstractExceptionRenderer
{
    #[Override]
    public function supports(Throwable $e): bool
    {
        return $e instanceof ValidationException;
    }

    #[Override]
    public function priority(): int
    {
        return 90;
    }

    #[Override]
    public function render(Throwable $e, ServerRequestInterface $request): ResponseInterface
    {
        \assert($e instanceof ValidationException);

        if ($this->isApiRequest($request)) {
            return $this->response->problem(
                Result::fail($e->getMessage(), ServiceErrorCode::VALIDATION_ERROR, context: ['errors' => $e->getErrors()]),
                $e->getHttpCode()
            );
        }

        Flash::error($e->getMessage());
        foreach ($e->getErrors() as $field => $msg) {
            Flash::error("$field: $msg");
        }

        $referer = $request->getHeaderLine('Referer') ?: '/';

        return $this->response->redirect($referer, 302);
    }
}
