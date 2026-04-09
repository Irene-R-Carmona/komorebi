<?php

declare(strict_types=1);

namespace App\Http\ExceptionRenderers;

use App\Core\Flash;
use App\Exceptions\BusinessRuleException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Renderiza BusinessRuleException → HTTP code de la excepción (por defecto 400).
 * API: JSON.
 * HTML: redirect de vuelta con Flash.
 */
final class BusinessRuleExceptionRenderer extends AbstractExceptionRenderer
{
    #[\Override]
    public function supports(\Throwable $e): bool
    {
        return $e instanceof BusinessRuleException;
    }

    #[\Override]
    public function priority(): int
    {
        return 70;
    }

    #[\Override]
    public function render(\Throwable $e, ServerRequestInterface $request): ResponseInterface
    {
        assert($e instanceof BusinessRuleException);

        if ($this->isApiRequest($request)) {
            return $this->response->json([
                'error'     => $e->getMessage(),
                'rule_code' => $e->getRuleCode(),
                'context'   => $e->getContext(),
            ], $e->getHttpCode());
        }

        Flash::error($e->getMessage());
        $referer = $request->getHeaderLine('Referer') ?: '/';
        return $this->response->redirect($referer, 302);
    }
}
