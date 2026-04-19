<?php

declare(strict_types=1);

namespace App\Http\ExceptionRenderers;

use App\Core\Flash;
use App\Core\Result;
use App\Core\ServiceErrorCode;
use App\Exceptions\BusinessRuleException;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * Renderiza BusinessRuleException → HTTP code de la excepción (por defecto 400).
 * API: JSON.
 * HTML: redirect de vuelta con Flash.
 */
final class BusinessRuleExceptionRenderer extends AbstractExceptionRenderer
{
    #[Override]
    public function supports(Throwable $e): bool
    {
        return $e instanceof BusinessRuleException;
    }

    #[Override]
    public function priority(): int
    {
        return 70;
    }

    #[Override]
    public function render(Throwable $e, ServerRequestInterface $request): ResponseInterface
    {
        \assert($e instanceof BusinessRuleException);

        if ($this->isApiRequest($request)) {
            $context = \array_merge(
                ['rule_code' => $e->getRuleCode()],
                $e->getContext()
            );

            return $this->response->problem(
                Result::fail($e->getMessage(), ServiceErrorCode::BUSINESS_RULE, context: $context),
                $e->getHttpCode()
            );
        }

        Flash::error($e->getMessage());
        $referer = $request->getHeaderLine('Referer') ?: '/';

        return $this->response->redirect($referer, 302);
    }
}
