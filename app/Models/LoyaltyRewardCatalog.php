<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * Modelo LoyaltyRewardCatalog - Catálogo de recompensas disponibles
 */
final class LoyaltyRewardCatalog
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Obtener todas las recompensas activas
     */
    public function getActiveRewards(): array
    {
        $stmt = $this->db->query(
            'SELECT * FROM loyalty_reward_catalog
             WHERE is_active = TRUE
             ORDER BY display_order ASC, stamps_required ASC'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener recompensa por tipo
     */
    public function findByType(string $type): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM loyalty_reward_catalog
             WHERE reward_type = ? AND is_active = TRUE
             LIMIT 1'
        );
        $stmt->execute([$type]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    /**
     * Obtener recompensas disponibles para un tier específico
     */
    public function getRewardsForTier(string $tier): array
    {
        $tierOrder = ['bronze' => 1, 'silver' => 2, 'gold' => 3, 'platinum' => 4];
        $userTierLevel = $tierOrder[$tier] ?? 1;

        $stmt = $this->db->query(
            'SELECT * FROM loyalty_reward_catalog
             WHERE is_active = TRUE
             ORDER BY display_order ASC'
        );

        $allRewards = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Filtrar recompensas según tier del usuario
        return \array_filter($allRewards, function ($reward) use ($tierOrder, $userTierLevel) {
            $requiredTierLevel = $tierOrder[$reward['tier_required']] ?? 1;

            return $userTierLevel >= $requiredTierLevel;
        });
    }
}
