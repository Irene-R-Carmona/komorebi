<?php

declare(strict_types=1);

namespace App\Domain\Mappers;

use App\Domain\DTO\AnimalDTO;
use Override;

final readonly class AnimalMapper implements MapperInterface
{
    #[Override]
    public function toDTO(array $row): AnimalDTO
    {
        return new AnimalDTO(
            id: (int) $row['id'],
            cafe_id: (int) $row['cafe_id'],
            name: (string) $row['name'],
            species: (string) ($row['species_type'] ?? ''),
            description: isset($row['description']) ? (string) $row['description'] : null,
            image_url: isset($row['image_url']) ? (string) $row['image_url'] : null,
            is_active: (bool) (($row['current_status'] ?? '') === 'active'),
        );
    }
}
