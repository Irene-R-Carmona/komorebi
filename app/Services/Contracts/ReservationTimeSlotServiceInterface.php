<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Core\Result;

interface ReservationTimeSlotServiceInterface
{
    public function createReservationWithSlot(array $data): Result;

    public function cancelReservationAndPromote(int $reservationId): Result;

    public function addToWaitlist(array $data): Result;

    public function confirmWaitlistEntry(string $token): Result;
}
