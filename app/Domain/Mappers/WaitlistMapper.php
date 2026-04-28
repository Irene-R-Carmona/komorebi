<?php

declare(strict_types=1);

namespace App\Domain\Mappers;

use App\Domain\DTO\WaitlistEntryDTO;
use Override;

final readonly class WaitlistMapper implements MapperInterface
{
    #[Override]
    public function toDTO(array $row): WaitlistEntryDTO
    {
        return new WaitlistEntryDTO(
            id: (int) $row['id'],
            token: (string) $row['token'],
            status: (string) ($row['status'] ?? 'waiting'),
            position: isset($row['position']) ? (int) $row['position'] : null,
            time_slot_id: (int) ($row['time_slot_id'] ?? 0),
            user_id: (int) ($row['user_id'] ?? 0),
            slot_date: (string) ($row['slot_date'] ?? ''),
            slot_time: (string) ($row['slot_time'] ?? ''),
            cafe_name: (string) ($row['cafe_name'] ?? ''),
            guest_count: (int) ($row['guest_count'] ?? 1),
            contact_email: (string) ($row['contact_email'] ?? ''),
            expires_at: isset($row['expires_at']) ? (string) $row['expires_at'] : null,
            special_requests: isset($row['special_requests']) ? (string) $row['special_requests'] : null,
        );
    }
}
