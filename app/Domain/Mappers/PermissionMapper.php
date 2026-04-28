<?php

declare(strict_types=1);

namespace App\Domain\Mappers;

use App\Domain\DTO\PermissionDTO;
use Override;

final readonly class PermissionMapper implements MapperInterface
{
    #[Override]
    public function toDTO(array $row): PermissionDTO
    {
        return new PermissionDTO(
            id: (int) $row['id'],
            code: (string) $row['code'],
            name: (string) $row['name'],
            description: isset($row['description']) ? (string) $row['description'] : null,
            resource: isset($row['resource']) ? (string) $row['resource'] : null,
            action: isset($row['action']) ? (string) $row['action'] : null,
        );
    }
}
