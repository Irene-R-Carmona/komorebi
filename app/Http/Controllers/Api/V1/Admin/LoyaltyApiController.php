<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Core\Http\ResponseFactory;
use App\Http\Controllers\Api\AbstractApiController;
use App\Repositories\Contracts\LoyaltyRepositoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class LoyaltyApiController extends AbstractApiController
{
    public function __construct(
        ResponseFactory $response,
        private readonly LoyaltyRepositoryInterface $loyaltyRepo,
    ) {
        parent::__construct($response);
    }

    /** GET /api/v1/admin/loyalty/stats */
    public function stats(ServerRequestInterface $request): ResponseInterface
    {
        return $this->success([
            'tier_distribution' => $this->loyaltyRepo->getTierDistribution(),
            'redemption_stats' => $this->loyaltyRepo->getRedemptionStats(),
        ]);
    }

    /** GET /api/v1/admin/loyalty/cards */
    public function cards(ServerRequestInterface $request): ResponseInterface
    {
        $q = $request->getQueryParams();
        $page = \max(1, (int) ($q['page'] ?? 1));
        $search = \trim((string) ($q['search'] ?? ''));

        return $this->success($this->loyaltyRepo->getAllCardsPaginated($page, 20, $search));
    }

    /** GET /api/v1/admin/loyalty/catalog */
    public function catalog(ServerRequestInterface $request): ResponseInterface
    {
        return $this->success($this->loyaltyRepo->getAllCatalog());
    }

    /** PATCH /api/v1/admin/loyalty/catalog/{id}/toggle */
    public function toggleCatalogItem(ServerRequestInterface $request, int $id): ResponseInterface
    {
        $body = (array) ($request->getParsedBody() ?? []);
        $isActive = (bool) ($body['is_active'] ?? false);
        $ok = $this->loyaltyRepo->toggleCatalogItem($id, $isActive);
        if (!$ok) {
            return $this->response->json(['ok' => false, 'error' => 'Elemento no encontrado'], 404);
        }

        return $this->success(['id' => $id, 'is_active' => $isActive]);
    }

    /** GET /api/v1/admin/loyalty/redemptions */
    public function redemptions(ServerRequestInterface $request): ResponseInterface
    {
        $q = $request->getQueryParams();
        $filters = \array_filter([
            'status' => $q['status'] ?? null,
            'reward_type' => $q['reward_type'] ?? null,
        ], static fn($v) => $v !== null && $v !== '');
        $limit = \min(100, \max(1, (int) ($q['limit'] ?? 20)));

        return $this->success($this->loyaltyRepo->getRecentRedemptions($limit, $filters));
    }
}
