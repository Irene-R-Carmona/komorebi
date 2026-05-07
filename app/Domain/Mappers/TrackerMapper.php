<?php

declare(strict_types=1);

namespace App\Domain\Mappers;

use App\Domain\DTO\TrackerDTO;
use Override;

final readonly class TrackerMapper implements MapperInterface
{
    #[Override]
    public function toDTO(array $row): TrackerDTO
    {
        return new TrackerDTO(
            id: (int) $row['id'],
            cafe_id: (int) $row['cafe_id'],
            code: (string) $row['code'],
            type: (string) $row['type'],
            status: (string) $row['status'],
            last_assigned_at: isset($row['last_assigned_at']) ? (string) $row['last_assigned_at'] : null,
            cafe_name: isset($row['cafe_name']) ? (string) $row['cafe_name'] : null,
        );
    }
}
