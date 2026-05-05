<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Domain\DTO\LoyaltyCardDTO;
use App\Domain\DTO\LoyaltyRewardDTO;
use App\Domain\Mappers\LoyaltyCardMapper;
use App\Domain\Mappers\LoyaltyRewardMapper;
use App\Repositories\Contracts\LoyaltyRepositoryInterface;
use Override;
use PDO;
use RuntimeException;

final class LoyaltyRepository extends AbstractRepository implements LoyaltyRepositoryInterface
{
    private LoyaltyCardMapper $cardMapper;
    private LoyaltyRewardMapper $rewardMapper;

    public function __construct(?PDO $db = null)
    {
        parent::__construct($db);
        $this->cardMapper = new LoyaltyCardMapper();
        $this->rewardMapper = new LoyaltyRewardMapper();
    }

    #[Override]
    protected function getTable(): string
    {
        return 'loyalty_cards';
    }

    #[Override]
    protected function getSelectFields(): array
    {
        return ['id', 'user_id', 'stamps', 'current_tier', 'visits_count', 'last_stamp_at', 'total_rewards_redeemed', 'created_at', 'updated_at'];
    }

    // ── LoyaltyCard ──────────────────────────────────────────────

    public function findOrCreateCardByUserId(int $userId): LoyaltyCardDTO
    {
        $card = $this->findCardByUserId($userId);
        if ($card !== null) {
            return $card;
        }

        $this->getDb()->prepare(
            'INSERT INTO loyalty_cards (user_id, stamps, visits_count)
             VALUES (?, 0, 0)'
        )->execute([$userId]);

        $created = $this->findCardByUserId($userId);
        if ($created === null) {
            throw new RuntimeException('Failed to create loyalty card for user ' . $userId);
        }

        return $created;
    }

    public function findCardById(int $id): ?LoyaltyCardDTO
    {
        $stmt = $this->getDb()->prepare('SELECT * FROM loyalty_cards WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $this->cardMapper->toDTO($row) : null;
    }

    public function findCardByUserId(int $userId): ?LoyaltyCardDTO
    {
        $stmt = $this->getDb()->prepare('SELECT * FROM loyalty_cards WHERE user_id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $this->cardMapper->toDTO($row) : null;
    }

    public function addStamps(int $cardId, int $stamps): bool
    {
        return $this->getDb()->prepare(
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
        // No-op: current_tier es una columna GENERATED ALWAYS (ver migration 024).
        // MySQL actualiza el tier automáticamente al modificar visits_count.
        return true;
    }

    /**
     * Ranking de usuarios por sellos acumulados.
     * @return array<int, array<string, mixed>>
     */
    public function getLeaderboard(int $limit = 10): array
    {
        $stmt = $this->getDb()->prepare(
            'SELECT user_id,
                    stamps,
                    current_tier,
                    RANK() OVER (ORDER BY stamps DESC) AS `rank`
             FROM loyalty_cards
             ORDER BY stamps DESC
             LIMIT ?'
        );
        $stmt->execute([$limit]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function consumeStamps(int $cardId, int $stamps): bool
    {
        return $this->getDb()->prepare(
            'UPDATE loyalty_cards
             SET stamps = stamps - ?,
                 total_rewards_redeemed = total_rewards_redeemed + 1,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = ? AND stamps >= ?'
        )->execute([$stamps, $cardId, $stamps]);
    }

    public function lockCardForUpdate(int $userId): void
    {
        $this->getDb()->prepare('SELECT id FROM loyalty_cards WHERE user_id = ? FOR UPDATE')
            ->execute([$userId]);
    }

    // ── LoyaltyRewardCatalog ─────────────────────────────────────

    public function findCatalogByType(string $type): ?array
    {
        $stmt = $this->getDb()->prepare(
            'SELECT * FROM loyalty_reward_catalog WHERE reward_type = ? AND is_active = TRUE LIMIT 1'
        );
        $stmt->execute([$type]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getCatalogRewardsForTier(string $tier): array
    {
        $tierOrder = ['bronze' => 1, 'silver' => 2, 'gold' => 3, 'platinum' => 4];
        $userTierLevel = $tierOrder[$tier] ?? 1;

        $stmt = $this->getDb()->query(
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
        $this->getDb()->prepare(
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

        return (int) $this->getDb()->lastInsertId();
    }

    public function findRewardsByUserId(int $userId): array
    {
        $stmt = $this->getDb()->prepare(
            'SELECT lr.*, lrc.name_es, lrc.description_es, lrc.icon
             FROM loyalty_rewards lr
             LEFT JOIN loyalty_reward_catalog lrc ON lr.reward_type = lrc.reward_type
             WHERE lr.user_id = ?
             ORDER BY lr.redeemed_at DESC'
        );
        $stmt->execute([$userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findRewardByCode(string $code): ?LoyaltyRewardDTO
    {
        $stmt = $this->getDb()->prepare(
            'SELECT lr.*, lrc.name_es, lrc.description_es
             FROM loyalty_rewards lr
             LEFT JOIN loyalty_reward_catalog lrc ON lr.reward_type = lrc.reward_type
             WHERE lr.redemption_code = ? LIMIT 1'
        );
        $stmt->execute([$code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $this->rewardMapper->toDTO($row) : null;
    }

    public function markRewardsExpired(array $ids): bool
    {
        if (empty($ids)) {
            return true;
        }

        $placeholders = \implode(',', \array_fill(0, \count($ids), '?'));

        return $this->getDb()->prepare(
            "UPDATE loyalty_rewards SET status = 'expired'
             WHERE id IN ($placeholders) AND status = 'pending'"
        )->execute($ids);
    }

    public function markRewardUsed(int $id): bool
    {
        return $this->getDb()->prepare(
            "UPDATE loyalty_rewards
             SET status = 'used', used_at = CURRENT_TIMESTAMP
             WHERE id = ? AND status = 'pending'"
        )->execute([$id]);
    }

    // ── Admin ─────────────────────────────────────────────────────

    /** @return array<string, int> Claves: bronze, silver, gold, platinum */
    public function getTierDistribution(): array
    {
        $stmt = $this->getDb()->query(
            'SELECT current_tier, COUNT(*) AS total
             FROM loyalty_cards
             GROUP BY current_tier'
        );
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        $result = ['bronze' => 0, 'silver' => 0, 'gold' => 0, 'platinum' => 0];
        foreach ($rows as $row) {
            $result[$row['current_tier']] = (int) $row['total'];
        }

        return $result;
    }

    /**
     * @return array{ items: array<int, array<string, mixed>>, total: int, page: int, per_page: int, has_next: bool }
     */
    public function getAllCardsPaginated(int $page, int $perPage, string $search = ''): array
    {
        $offset = ($page - 1) * $perPage;
        $where = '';
        $params = [];
        if ($search !== '') {
            $where = 'WHERE u.email LIKE ? OR u.name LIKE ?';
            $like = '%' . $search . '%';
            $params = [$like, $like];
        }
        $total = (int) $this->getDb()->query(
            "SELECT COUNT(*) FROM loyalty_cards lc JOIN users u ON u.id = lc.user_id $where",
        )->fetchColumn();

        $stmt = $this->getDb()->prepare(
            "SELECT lc.id, lc.stamps, lc.visits_count, lc.current_tier,
                    lc.total_rewards_redeemed, lc.last_stamp_at, lc.created_at,
                    u.name AS user_name, u.email AS user_email
             FROM loyalty_cards lc
             JOIN users u ON u.id = lc.user_id
             $where
             ORDER BY lc.stamps DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->execute(\array_merge($params, [$perPage, $offset]));
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'has_next' => ($offset + \count($items)) < $total,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function getAllCatalog(): array
    {
        $stmt = $this->getDb()->query(
            'SELECT id, reward_type, name_es, stamps_required, tier_required,
                    validity_days, is_active, display_order, icon
             FROM loyalty_reward_catalog
             ORDER BY display_order, id'
        );

        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    public function toggleCatalogItem(int $id, bool $isActive): bool
    {
        return $this->getDb()->prepare(
            'UPDATE loyalty_reward_catalog SET is_active = ? WHERE id = ?'
        )->execute([(int) $isActive, $id]);
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function getRecentRedemptions(int $limit = 20, array $filters = []): array
    {
        $conditions = [];
        $params = [];
        if (!empty($filters['status'])) {
            $conditions[] = 'lr.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['reward_type'])) {
            $conditions[] = 'lr.reward_type = ?';
            $params[] = $filters['reward_type'];
        }
        $where = $conditions ? 'WHERE ' . \implode(' AND ', $conditions) : '';
        $params[] = $limit;
        $stmt = $this->getDb()->prepare(
            "SELECT lr.id, lr.reward_type, lr.stamps_cost, lr.status,
                    lr.redeemed_at, lr.used_at, lr.expires_at, lr.redemption_code,
                    u.name AS user_name, u.email AS user_email
             FROM loyalty_rewards lr
             JOIN users u ON u.id = lr.user_id
             $where
             ORDER BY lr.redeemed_at DESC
             LIMIT ?"
        );
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<string, mixed> */
    public function getRedemptionStats(): array
    {
        $row = $this->getDb()->query(
            "SELECT
               COUNT(*) AS total,
               SUM(CASE WHEN status = 'used' THEN 1 ELSE 0 END) AS used,
               SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending,
               SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) AS expired,
               SUM(CASE WHEN redeemed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS last_30_days
             FROM loyalty_rewards"
        )->fetch(PDO::FETCH_ASSOC);

        return $row ?: ['total' => 0, 'used' => 0, 'pending' => 0, 'expired' => 0, 'last_30_days' => 0];
    }
}
