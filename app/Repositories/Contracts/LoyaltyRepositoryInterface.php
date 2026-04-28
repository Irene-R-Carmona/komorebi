<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Domain\DTO\LoyaltyCardDTO;
use App\Domain\DTO\LoyaltyRewardDTO;

interface LoyaltyRepositoryInterface
{
    // ── LoyaltyCard ──────────────────────────────────────────────

    /** Obtiene o crea la tarjeta del usuario. */
    public function findOrCreateCardByUserId(int $userId): LoyaltyCardDTO;

    public function findCardById(int $id): ?LoyaltyCardDTO;

    public function findCardByUserId(int $userId): ?LoyaltyCardDTO;

    public function addStamps(int $cardId, int $stamps): bool;

    public function updateTier(int $cardId, string $tier): bool;

    public function consumeStamps(int $cardId, int $stamps): bool;

    /** SELECT … FOR UPDATE para serializar recanjes concurrentes. */
    public function lockCardForUpdate(int $userId): void;

    // ── LoyaltyRewardCatalog ─────────────────────────────────────

    public function findCatalogByType(string $type): ?array;

    /** @return array<int, array<string, mixed>> */
    public function getCatalogRewardsForTier(string $tier): array;

    // ── LoyaltyReward ────────────────────────────────────────────

    /** @param array<string, mixed> $data */
    public function createReward(array $data): int;

    /** @return array<int, array<string, mixed>> */
    public function findRewardsByUserId(int $userId): array;

    public function findRewardByCode(string $code): ?LoyaltyRewardDTO;

    /** @param int[] $ids */
    public function markRewardsExpired(array $ids): bool;

    public function markRewardUsed(int $id): bool;

    /**
     * Ranking de usuarios por sellos acumulados (RANK() OVER).
     * @return array<int, array<string, mixed>>
     */
    public function getLeaderboard(int $limit = 10): array;
}
