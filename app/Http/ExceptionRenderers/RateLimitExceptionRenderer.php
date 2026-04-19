<?php

declare(strict_types=1);

namespace App\Http\ExceptionRenderers;

use App\Core\View;
use App\Exceptions\RateLimitException;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * Renderiza RateLimitException → 429.
 * API: JSON con header Retry-After.
 * HTML: vista errors/429 con header Retry-After.
 */
final class RateLimitExceptionRenderer extends AbstractExceptionRenderer
{
    #[Override]
    public function supports(Throwable $e): bool
    {
        return $e instanceof RateLimitException;
    }

    #[Override]
    public function priority(): int
    {
        return 85;
    }

    #[Override]
    public function render(Throwable $e, ServerRequestInterface $request): ResponseInterface
    {
        \assert($e instanceof RateLimitException);

        if ($this->isApiRequest($request)) {
            return $this->response
                ->json([
                    'error' => $e->getMessage(),
                    'retry_after' => $e->getRetryAfter(),
                    'action' => $e->getAction(),
                ], 429)
                ->withHeader('Retry-After', (string) $e->getRetryAfter());
        }

        $html = View::renderToString('errors/429', [
            'message' => $e->getMessage(),
            'retry_after' => $e->getRetryAfter(),
        ]);

        return $this->response->html($html, 429)
            ->withHeader('Retry-After', (string) $e->getRetryAfter());
    }
}
