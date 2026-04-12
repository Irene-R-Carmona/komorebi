<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Core\Result;

interface WaitlistServiceInterface
{
    public function joinWaitlist(int $timeSlotId, int $userId, array $data): Result;

    public function promoteNext(int $timeSlotId): Result;

    public function confirmPromotion(string $token, array $reservationData = []): Result;

    public function expireTokens(): Result;

    public function getPosition(int $userId, int $timeSlotId): Result;

    public function cancelWaitlist(int $waitlistId, int $userId): Result;

    public function getUserHistory(int $userId, int $limit = 10): Result;

    public function getWaitlistStatus(string $token): Result;

    public function getUserWaitlists(int $userId, bool $activeOnly = true): Result;
}
