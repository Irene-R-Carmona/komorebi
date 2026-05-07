<?php

declare(strict_types=1);

namespace App\Domain\Mappers;

use App\Domain\DTO\TimeSlotDTO;
use Override;

final readonly class TimeSlotMapper implements MapperInterface
{
    #[Override]
    public function toDTO(array $row): TimeSlotDTO
    {
        return new TimeSlotDTO(
            id: (int) $row['id'],
            cafe_id: (int) $row['cafe_id'],
            slot_date: (string) $row['slot_date'],
            slot_time: (string) $row['slot_time'],
            total_capacity: (int) ($row['total_capacity'] ?? 20),
            available_spots: (int) ($row['available_spots'] ?? 0),
            reserved_spots: (int) ($row['reserved_spots'] ?? 0),
            is_blocked: (bool) ($row['is_blocked'] ?? false),
            blocked_reason: isset($row['blocked_reason']) ? (string) $row['blocked_reason'] : null,
            duration_minutes: (int) ($row['duration_minutes'] ?? 60),
            created_at: (string) $row['created_at'],
            updated_at: (string) $row['updated_at'],
        );
    }
}
