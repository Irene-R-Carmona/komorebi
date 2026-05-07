<?php

declare(strict_types=1);

namespace App\Domain\Mappers;

use App\Domain\DTO\MenuCategoryDTO;
use Override;

final readonly class MenuCategoryMapper implements MapperInterface
{
    #[Override]
    public function toDTO(array $row): MenuCategoryDTO
    {
        return new MenuCategoryDTO(
            id: (int) $row['id'],
            name: (string) $row['name'],
            slug: (string) $row['slug'],
            display_order: (int) ($row['display_order'] ?? 0),
        );
    }
}
