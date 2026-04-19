<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Repositories\Contracts\LoyaltyRepositoryInterface;
use PDO;

final class LoyaltyRepository implements LoyaltyRepositoryInterface
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    // ── LoyaltyCard ──────────────────────────────────────────────

    public function findOrCreateCardByUserId(int $userId): array
    {
        $card = $this->findCardByUserId($userId);
        if ($card) {
            return $card;
        }

        $this->db->prepare(
            "INSERT INTO loyalty_cards (user_id, stamps, current_tier, visits_count)
             VALUES (?, 0, 'bronze', 0)"
        )->execute([$userId]);

        return $this->findCardByUserId($userId);
    }

    public function findCardById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM loyalty_cards WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findCardByUserId(int $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM loyalty_cards WHERE user_id = ? LIMIT 1');
        $stmt->execute([$userId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function addStamps(int $cardId, int $stamps): bool
    {
        return $this->db->prepare(
            'UPDATE loyalty_cards
             SET stamps = stamps + ?,
                 visits_count = visits_count + ?,
                 last_stamp_at = CURRENT_TIMESTAMP,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = ?'
        )->execute([$stamps, $stamps, $cardId]);
    }

    public function updateTier(int $cardId, string $tier): bool
    {
        return $this->db->prepare(
            'UPDATE loyalty_cards SET current_tier = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?'
        )->execute([$tier, $cardId]);
    }

    public function consumeStamps(int $cardId, int $stamps): bool
    {
        return $this->db->prepare(
            'UPDATE loyalty_cards
             SET stamps = stamps - ?,
                 total_rewards_redeemed = total_rewards_redeemed + 1,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = ? AND stamps >= ?'
        )->execute([$stamps, $cardId, $stamps]);
    }

    public function lockCardForUpdate(int $userId): void
    {
        $this->db->prepare('SELECT id FROM loyalty_cards WHERE user_id = ? FOR UPDATE')
            ->execute([$userId]);
    }

    // ── LoyaltyRewardCatalog ─────────────────────────────────────

    public function findCatalogByType(string $type): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM loyalty_reward_catalog WHERE reward_type = ? AND is_active = TRUE LIMIT 1'
        );
        $stmt->execute([$type]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getCatalogRewardsForTier(string $tier): array
    {
        $tierOrder = ['bronze' => 1, 'silver' => 2, 'gold' => 3, 'platinum' => 4];
        $userTierLevel = $tierOrder[$tier] ?? 1;

        $stmt = $this->db->query(
            'SELECT * FROM loyalty_reward_catalog WHERE is_active = TRUE ORDER BY display_order ASC'
        );
        $all = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return \array_values(\array_filter($all, static function (array $reward) use ($tierOrder, $userTierLevel): bool {
            return $userTierLevel >= ($tierOrder[$reward['tier_required']] ?? 1);
        }));
    }

    // ── LoyaltyReward ────────────────────────────────────────────

    public function createReward(array $data): int
    {
        $this->db->prepare(
            'INSERT INTO loyalty_rewards
                (user_id, loyalty_card_id, reward_type, stamps_cost, redemption_code, expires_at, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([
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

    public function findRewardsByUserId(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT lr.*, lrc.name_es, lrc.description_es, lrc.icon
             FROM loyalty_rewards lr
             LEFT JOIN loyalty_reward_catalog lrc ON lr.reward_type = lrc.reward_type
             WHERE lr.user_id = ?
             ORDER BY lr.redeemed_at DESC'
        );
        $stmt->execute([$userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findRewardByCode(string $code): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT lr.*, lrc.name_es, lrc.description_es
             FROM loyalty_rewards lr
             LEFT JOIN loyalty_reward_catalog lrc ON lr.reward_type = lrc.reward_type
             WHERE lr.redemption_code = ? LIMIT 1'
        );
        $stmt->execute([$code]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function markRewardsExpired(array $ids): bool
    {
        if (empty($ids)) {
            return true;
        }

        $placeholders = \implode(',', \array_fill(0, \count($ids), '?'));

        return $this->db->prepare(
            "UPDATE loyalty_rewards SET status = 'expired'
             WHERE id IN ($placeholders) AND status = 'pending'"
        )->execute($ids);
    }

    public function markRewardUsed(int $id): bool
    {
        return $this->db->prepare(
            "UPDATE loyalty_rewards
             SET status = 'used', used_at = CURRENT_TIMESTAMP
             WHERE id = ? AND status = 'pending'"
        )->execute([$id]);
    }
}
