<?php

declare(strict_types=1);

namespace App\Http\ExceptionRenderers;

use App\Core\Flash;
use App\Core\Result;
use App\Core\ServiceErrorCode;
use App\Core\Session;
use App\Exceptions\AuthenticationException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Renderiza AuthenticationException → 401.
 * API: JSON.
 * HTML: redirect a /login con Flash.
 */
final class AuthenticationExceptionRenderer extends AbstractExceptionRenderer
{
    #[\Override]
    public function supports(\Throwable $e): bool
    {
        return $e instanceof AuthenticationException;
    }

    #[\Override]
    public function priority(): int
    {
        return 80;
    }

    #[\Override]
    public function render(\Throwable $e, ServerRequestInterface $request): ResponseInterface
    {
        \assert($e instanceof AuthenticationException);

        if ($this->isApiRequest($request)) {
            return $this->response->problem(
                Result::fail($e->getMessage(), ServiceErrorCode::UNAUTHORIZED, context: ['reason' => $e->getReason()]),
                401
            );
        }

        Flash::error($e->getMessage());
        Session::set('redirect_after_login', (string) $request->getUri());

        return $this->response->redirect('/login', 302);
    }
}
