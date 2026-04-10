<?php

declare(strict_types=1);

namespace App\Http\ExceptionRenderers;

use App\Core\Result;
use App\Core\ServiceErrorCode;
use App\Core\View;
use App\Exceptions\AuthorizationException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Renderiza AuthorizationException → 403.
 * API: JSON.
 * HTML: vista errors/403.
 */
final class AuthorizationExceptionRenderer extends AbstractExceptionRenderer
{
    #[\Override]
    public function supports(\Throwable $e): bool
    {
        return $e instanceof AuthorizationException;
    }

    #[\Override]
    public function priority(): int
    {
        return 80;
    }

    #[\Override]
    public function render(\Throwable $e, ServerRequestInterface $request): ResponseInterface
    {
        assert($e instanceof AuthorizationException);

        if ($this->isApiRequest($request)) {
            return $this->response->problem(
                Result::fail($e->getMessage(), ServiceErrorCode::FORBIDDEN, context: ['permission' => $e->getPermission()]),
                403
            );
        }

        $html = View::renderToString('errors/403', [
            'message'    => $e->getMessage(),
            'permission' => $e->getPermission(),
        ]);

        return $this->response->html($html, 403);
    }
}
