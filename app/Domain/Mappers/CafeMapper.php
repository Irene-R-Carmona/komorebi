<?php

declare(strict_types=1);

namespace App\Domain\Mappers;

use App\Domain\DTO\CafeDTO;
use Override;

final readonly class CafeMapper implements MapperInterface
{
    #[Override]
    public function toDTO(array $row): CafeDTO
    {
        return new CafeDTO(
            id: (int) $row['id'],
            slug: (string) $row['slug'],
            name: (string) $row['name'],
            japanese_name: isset($row['japanese_name']) ? (string) $row['japanese_name'] : null,
            description: isset($row['description']) ? (string) $row['description'] : null,
            location: (string) ($row['location'] ?? ''),
            category: (string) ($row['category'] ?? ''),
            animal_type: (string) ($row['animal_type'] ?? ''),
            price_per_hour: (float) ($row['price_per_hour'] ?? 0.0),
            capacity_max: (int) ($row['capacity_max'] ?? 0),
            rating_avg: (float) ($row['rating_avg'] ?? 0.0),
            opening_time: (string) ($row['opening_time'] ?? ''),
            closing_time: (string) ($row['closing_time'] ?? ''),
            timezone: (string) ($row['timezone'] ?? 'UTC'),
            is_active: (bool) ($row['is_active'] ?? true),
            has_reservations: (bool) ($row['has_reservations'] ?? false),
            image_url: isset($row['image_url']) ? (string) $row['image_url'] : null,
        );
    }
}
