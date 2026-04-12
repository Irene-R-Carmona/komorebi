<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Core\Http\ResponseFactory;
use App\Core\Session;
use App\Http\Controllers\Api\AbstractApiController;
use App\Services\LoyaltyService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * API de fidelización: validación de códigos, canje y uso desde TPV.
 *
 * Extrae los métodos JSON que antes vivían en Public\LoyaltyController.
 */
final class LoyaltyController extends AbstractApiController
{
    public function __construct(
        ResponseFactory $response,
        private readonly LoyaltyService $loyaltyService,
    ) {
        parent::__construct($response);
    }

    /**
     * POST /api/loyalty/redeem
     */
    public function redeem(ServerRequestInterface $request): ResponseInterface
    {
        $userId = Session::userId();

        if (!$userId) {
            return $this->unauthorized('No autenticado');
        }

        $body = $request->getParsedBody();
        $rewardType = $body['reward_type'] ?? null;

        if (!$rewardType) {
            return $this->unprocessable('Tipo de recompensa requerido', 'missing_reward_type');
        }

        $result = $this->loyaltyService->redeemReward((int) $userId, $rewardType);

        if (!$result->ok) {
            return $this->unprocessable($result->getMessage(), 'redemption_error');
        }

        return $this->success([
            'message' => 'Recompensa canjeada exitosamente',
            'result'  => $result->data,
        ]);
    }

    /**
     * GET /api/loyalty/validate/{code}
     *
     * @param array<string, string> $params
     */
    public function validateCode(array $params, ServerRequestInterface $request): ResponseInterface
    {
        $code = $params['code'] ?? null;

        if (!$code) {
            return $this->unprocessable('Código requerido', 'missing_code');
        }

        $result = $this->loyaltyService->validateRedemptionCode($code);

        if (!$result->ok) {
            return $this->unprocessable($result->getMessage(), 'invalid_code');
        }

        return $this->success($result->data);
    }

    /**
     * POST /api/loyalty/use
     */
    public function use(ServerRequestInterface $request): ResponseInterface
    {
        $body = $request->getParsedBody();
        $code = $body['code'] ?? null;

        if (!$code) {
            return $this->unprocessable('Código requerido', 'missing_code');
        }

        $result = $this->loyaltyService->useReward($code);

        if (!$result->ok) {
            return $this->unprocessable($result->getMessage(), 'use_error');
        }

        return $this->success($result->data);
    }
}
