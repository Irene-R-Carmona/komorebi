<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

interface LoyaltyRepositoryInterface
{
    // ── LoyaltyCard ──────────────────────────────────────────────

    /** Obtiene o crea la tarjeta del usuario. */
    public function findOrCreateCardByUserId(int $userId): array;

    public function findCardById(int $id): ?array;

    public function findCardByUserId(int $userId): ?array;

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

    public function findRewardByCode(string $code): ?array;

    /** @param int[] $ids */
    public function markRewardsExpired(array $ids): bool;

    public function markRewardUsed(int $id): bool;
}
