<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Core\Result;
use DateMalformedStringException;

interface AvailabilityServiceInterface
{
    /**
     * @throws DateMalformedStringException
     */
    public function getAvailableSlots(int $cafeId, int $passId, string $dateYmd, int $guests): Result;

    /**
     * @throws DateMalformedStringException
     */
    public function assertSlotAvailable(int $cafeId, int $passId, string $dateYmd, string $timeHHMM, int $guests): Result;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAvailableCafesForReservation(): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAvailableCafesById(): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAvailablePassesForReservation(): array;
}
