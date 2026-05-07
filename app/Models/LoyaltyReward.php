<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * Modelo LoyaltyReward - Recompensas canjeadas del sistema de fidelización
 */
final class LoyaltyReward
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Crear nueva recompensa canjeada
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO loyalty_rewards
                (user_id, loyalty_card_id, reward_type, stamps_cost, redemption_code, expires_at, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );

        $stmt->execute([
            $data['user_id'],
            $data['loyalty_card_id'],
            $data['reward_type'],
            $data['stamps_cost'],
            $data['redemption_code'],
            $data['expires_at'] ?? null,
            $data['notes'] ?? null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Obtener recompensas de un usuario
     */
    public function findByUserId(int $userId, ?string $status = null): array
    {
        $sql = 'SELECT lr.*,
                       lrc.name_es, lrc.description_es, lrc.icon
                FROM loyalty_rewards lr
                LEFT JOIN loyalty_reward_catalog lrc ON lr.reward_type = lrc.reward_type
                WHERE lr.user_id = ?';

        $params = [$userId];

        if ($status !== null) {
            $sql .= ' AND lr.status = ?';
            $params[] = $status;
        }

        $sql .= ' ORDER BY lr.redeemed_at DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar recompensa por código de canje
     */
    public function findByRedemptionCode(string $code): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT lr.*,
                    lrc.name_es, lrc.description_es
             FROM loyalty_rewards lr
             LEFT JOIN loyalty_reward_catalog lrc ON lr.reward_type = lrc.reward_type
             WHERE lr.redemption_code = ? LIMIT 1'
        );
        $stmt->execute([$code]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    /**
     * Marcar recompensa como usada
     */
    public function markAsUsed(int $rewardId): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE loyalty_rewards
             SET status = 'used', used_at = CURRENT_TIMESTAMP
             WHERE id = ? AND status = 'pending'"
        );

        return $stmt->execute([$rewardId]);
    }

    /**
     * Obtener recompensas pendientes expiradas (para cronjob)
     */
    public function getExpiredRewards(): array
    {
        $stmt = $this->db->query(
            "SELECT * FROM loyalty_rewards
             WHERE status = 'pending'
               AND expires_at < CURRENT_TIMESTAMP"
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Marcar recompensas como expiradas
     */
    public function markExpired(array $rewardIds): bool
    {
        if (empty($rewardIds)) {
            return true;
        }

        $placeholders = \implode(',', \array_fill(0, \count($rewardIds), '?'));
        $stmt = $this->db->prepare(
            "UPDATE loyalty_rewards
             SET status = 'expired'
             WHERE id IN ($placeholders) AND status = 'pending'"
        );

        return $stmt->execute($rewardIds);
    }

    /**
     * Obtener estadísticas de recompensas (para admin)
     */
    public function getStatistics(): array
    {
        $stmt = $this->db->query(
            "SELECT
                COUNT(*) as total_rewards,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
                COUNT(CASE WHEN status = 'used' THEN 1 END) as used_count,
                COUNT(CASE WHEN status = 'expired' THEN 1 END) as expired_count,
                COUNT(CASE WHEN reward_type = 'drink_free' THEN 1 END) as drink_free_count,
                COUNT(CASE WHEN reward_type = 'entry_free' THEN 1 END) as entry_free_count
             FROM loyalty_rewards"
        );

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }
}
