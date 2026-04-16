<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * Modelo LoyaltyCard - Tarjeta de fidelización
 *
 * Sistema de sellos: 1 sello = 1 visita completada
 * Recompensas: 5 sellos = bebida gratis, 10 = entrada gratis
 * Tiers: bronze (0-9 visitas), silver (10-29), gold (30-49), platinum (50+)
 */
final class LoyaltyCard
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Obtener tarjeta por user_id (o crear si no existe)
     */
    public function findOrCreateByUserId(int $userId): ?array
    {
        $existing = $this->findByUserId($userId);
        if ($existing) {
            return $existing;
        }

        // Crear nueva tarjeta
        $stmt = $this->db->prepare(
            "INSERT INTO loyalty_cards (user_id, stamps, current_tier, visits_count)
             VALUES (?, 0, 'bronze', 0)"
        );
        $stmt->execute([$userId]);

        return $this->findByUserId($userId);
    }

    /**
     * Obtener tarjeta por user_id
     */
    public function findByUserId(int $userId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM loyalty_cards WHERE user_id = ? LIMIT 1'
        );
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    /**
     * Obtener tarjeta por ID
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM loyalty_cards WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    /**
     * Añadir sellos a una tarjeta
     *
     * @param int $cardId ID de la tarjeta
     * @param int $stamps Cantidad de sellos a añadir (default: 1)
     * @return bool
     */
    public function addStamps(int $cardId, int $stamps = 1): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE loyalty_cards
             SET stamps = stamps + ?,
                 visits_count = visits_count + ?,
                 last_stamp_at = CURRENT_TIMESTAMP,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = ?'
        );

        return $stmt->execute([$stamps, $stamps, $cardId]);
    }

    /**
     * Consumir sellos al canjear recompensa
     *
     * @param int $cardId ID de la tarjeta
     * @param int $stamps Cantidad de sellos a consumir
     * @return bool
     */
    public function consumeStamps(int $cardId, int $stamps): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE loyalty_cards
             SET stamps = stamps - ?,
                 total_rewards_redeemed = total_rewards_redeemed + 1,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = ? AND stamps >= ?'
        );

        return $stmt->execute([$stamps, $cardId, $stamps]);
    }

    /**
     * Actualizar tier de la tarjeta
     *
     * @param int $cardId ID de la tarjeta
     * @param string $tier 'bronze', 'silver', 'gold', 'platinum'
     * @return bool
     */
    public function updateTier(int $cardId, string $tier): bool
    {
        $validTiers = ['bronze', 'silver', 'gold', 'platinum'];
        if (!\in_array($tier, $validTiers, true)) {
            return false;
        }

        $stmt = $this->db->prepare(
            'UPDATE loyalty_cards
             SET current_tier = ?,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = ?'
        );

        return $stmt->execute([$tier, $cardId]);
    }

    /**
     * Obtener estadísticas de todas las tarjetas (para admin)
     */
    public function getStatistics(): array
    {
        $stmt = $this->db->query(
            "SELECT
                COUNT(*) as total_cards,
                SUM(stamps) as total_stamps_issued,
                SUM(total_rewards_redeemed) as total_rewards_redeemed,
                AVG(stamps) as avg_stamps_per_card,
                COUNT(CASE WHEN current_tier = 'bronze' THEN 1 END) as bronze_count,
                COUNT(CASE WHEN current_tier = 'silver' THEN 1 END) as silver_count,
                COUNT(CASE WHEN current_tier = 'gold' THEN 1 END) as gold_count,
                COUNT(CASE WHEN current_tier = 'platinum' THEN 1 END) as platinum_count
             FROM loyalty_cards"
        );

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Obtener top usuarios por sellos (leaderboard)
     */
    public function getTopUsers(int $limit = 10): array
    {
        $stmt = $this->db->prepare(
            'SELECT
                lc.id, lc.stamps, lc.current_tier, lc.visits_count,
                u.id as user_id, u.name, u.email
             FROM loyalty_cards lc
             INNER JOIN users u ON lc.user_id = u.id
             WHERE u.deleted_at IS NULL
             ORDER BY lc.stamps DESC, lc.visits_count DESC
             LIMIT ?'
        );
        $stmt->execute([$limit]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener tarjetas por tier
     */
    public function findByTier(string $tier): array
    {
        $validTiers = ['bronze', 'silver', 'gold', 'platinum'];
        if (!\in_array($tier, $validTiers, true)) {
            return [];
        }

        $stmt = $this->db->prepare(
            'SELECT lc.*, u.name as user_name, u.email as user_email
             FROM loyalty_cards lc
             INNER JOIN users u ON lc.user_id = u.id
             WHERE lc.current_tier = ? AND u.deleted_at IS NULL
             ORDER BY lc.visits_count DESC'
        );
        $stmt->execute([$tier]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
