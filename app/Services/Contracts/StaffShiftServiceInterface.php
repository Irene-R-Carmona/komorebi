<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Core\Result;

interface StaffShiftServiceInterface
{
    public function getWeekShifts(int $cafeId): Result;

    public function getStaffHistory(int $userId, int $cafeId): Result;

    public function assignShift(
        int $userId,
        int $cafeId,
        string $date,
        string $start,
        string $end,
        ?string $notes,
        int $createdBy,
    ): Result;

    public function getPerformanceMetrics(int $userId, int $cafeId): Result;
}
