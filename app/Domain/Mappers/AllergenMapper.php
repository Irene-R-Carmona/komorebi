<?php

declare(strict_types=1);

namespace App\Domain\Mappers;

use App\Domain\DTO\AllergenDTO;
use Override;

final readonly class AllergenMapper implements MapperInterface
{
    #[Override]
    public function toDTO(array $row): AllergenDTO
    {
        return new AllergenDTO(
            id: (int) $row['id'],
            code: (string) $row['code'],
            name: (string) $row['name'],
            japanese_name: isset($row['japanese_name']) ? (string) $row['japanese_name'] : null,
            icon_class: isset($row['icon_class']) ? (string) $row['icon_class'] : null,
            icon_color: isset($row['icon_color']) ? (string) $row['icon_color'] : null,
            severity: (string) ($row['severity'] ?? 'medium'),
            description: isset($row['description']) ? (string) $row['description'] : null,
        );
    }
}
