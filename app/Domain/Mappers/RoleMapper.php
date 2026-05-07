<?php

declare(strict_types=1);

namespace App\Domain\Mappers;

use App\Domain\DTO\RoleDTO;
use Override;

final readonly class RoleMapper implements MapperInterface
{
    #[Override]
    public function toDTO(array $row): RoleDTO
    {
        return new RoleDTO(
            id: (int) $row['id'],
            code: (string) $row['code'],
            name: (string) $row['name'],
            description: isset($row['description']) ? (string) $row['description'] : null,
        );
    }
}
