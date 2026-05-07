<?php

declare(strict_types=1);

namespace App\Http\ExceptionRenderers;

use App\Core\Result;
use App\Core\ServiceErrorCode;
use App\Core\View;
use App\Exceptions\NotFoundException;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * Renderiza NotFoundException → 404.
 * API: JSON.
 * HTML: vista errors/404.
 */
final class NotFoundExceptionRenderer extends AbstractExceptionRenderer
{
    #[Override]
    public function supports(Throwable $e): bool
    {
        return $e instanceof NotFoundException;
    }

    #[Override]
    public function priority(): int
    {
        return 80;
    }

    #[Override]
    public function render(Throwable $e, ServerRequestInterface $request): ResponseInterface
    {
        \assert($e instanceof NotFoundException);

        if ($this->isApiRequest($request)) {
            return $this->response->problem(
                Result::fail($e->getMessage(), ServiceErrorCode::NOT_FOUND, context: ['resource_type' => $e->getResourceType()]),
                404
            );
        }

        $html = View::renderToString('errors/404', [
            'message' => $e->getMessage(),
            'resource_type' => $e->getResourceType(),
        ], [], 'errors');

        return $this->response->html($html, 404);
    }
}
