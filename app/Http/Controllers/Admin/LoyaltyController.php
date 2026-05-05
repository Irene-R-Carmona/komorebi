<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\View;
use App\Repositories\Contracts\LoyaltyRepositoryInterface;
use Psr\Http\Message\ResponseInterface;
use Random\RandomException;

/**
 * Controlador de Fidelización (Admin) — SSR únicamente.
 * Las mutaciones están en Api\V1\Admin\LoyaltyApiController.
 *
 * @throws RandomException
 */
final class LoyaltyController
{
    private LoyaltyRepositoryInterface $loyaltyRepo;

    public function __construct(?LoyaltyRepositoryInterface $loyaltyRepo = null)
    {
        $this->loyaltyRepo = $loyaltyRepo ?? Container::make(LoyaltyRepositoryInterface::class);
    }

    /** GET /admin/loyalty */
    public function index(): ?ResponseInterface
    {
        $tierDist = $this->loyaltyRepo->getTierDistribution();
        $catalog = $this->loyaltyRepo->getAllCatalog();
        $redemptionStats = $this->loyaltyRepo->getRedemptionStats();
        $recentRedemptions = $this->loyaltyRepo->getRecentRedemptions(10);

        View::render('admin/loyalty/index', [
            'titulo' => 'Fidelización',
            'csrf_token' => Csrf::token(),
            'tier_distribution' => $tierDist,
            'catalog' => $catalog,
            'redemption_stats' => $redemptionStats,
            'recent_redemptions' => $recentRedemptions,
        ], [], 'backoffice');

        return null;
    }
}
