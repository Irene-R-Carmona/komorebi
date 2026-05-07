<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Core\Result;

interface LoyaltyServiceInterface
{
    public function addStamp(int $userId, int $stamps = 1, ?int $reservationId = null): Result;

    public function calculateTier(int $visitsCount): string;

    public function redeemReward(int $userId, string $rewardType): Result;

    public function getCardStatus(int $userId): Result;

    public function getAvailableRewards(string $tier, int $currentStamps): array;

    public function validateRedemptionCode(string $code): Result;

    public function useReward(string $code): Result;

    /**
     * Revierte 1 sello al cancelar una reserva (Q-05).
     */
    public function reverseStamp(int $userId): Result;
}
