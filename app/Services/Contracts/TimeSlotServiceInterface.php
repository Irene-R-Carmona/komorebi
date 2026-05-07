<?php

declare(strict_types=1);

namespace App\Services\Contracts;

interface TimeSlotServiceInterface
{
    public function getAvailableSlots(string $date, ?int $cafeId = null, ?int $guests = null): array;
}
