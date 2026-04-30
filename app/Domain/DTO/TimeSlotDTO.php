<?php

declare(strict_types=1);

namespace App\Domain\DTO;

use Override;

final readonly class TimeSlotDTO implements DomainTransferObject
{
    public function __construct(
        public int $id,
        public int $cafe_id,
        public string $slot_date,
        public string $slot_time,
        public int $total_capacity,
        public int $available_spots,
        public int $reserved_spots,
        public bool $is_blocked,
        public ?string $blocked_reason,
        public int $duration_minutes,
        public string $created_at,
        public string $updated_at,
    ) {
    }

    #[Override]
    public function toViewArray(): array
    {
        return [
            'id' => $this->id,
            'cafe_id' => $this->cafe_id,
            'slot_date' => $this->slot_date,
            'slot_time' => $this->slot_time,
            'total_capacity' => $this->total_capacity,
            'available_spots' => $this->available_spots,
            'reserved_spots' => $this->reserved_spots,
            'is_blocked' => $this->is_blocked,
            'blocked_reason' => $this->blocked_reason,
            'duration_minutes' => $this->duration_minutes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
