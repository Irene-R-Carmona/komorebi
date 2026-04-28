<?php

declare(strict_types=1);

namespace App\Domain\Mappers;

use App\Domain\DTO\UserDTO;
use Override;

final readonly class UserMapper implements MapperInterface
{
    #[Override]
    public function toDTO(array $row): UserDTO
    {
        $roles = $row['roles'] ?? [];
        if (\is_string($roles)) {
            $roles = \json_decode($roles, true) ?? [];
        }

        return new UserDTO(
            id: (int) $row['id'],
            uuid: (string) $row['uuid'],
            name: (string) $row['name'],
            email: (string) $row['email'],
            avatar: isset($row['avatar']) ? (string) $row['avatar'] : null,
            roles: \is_array($roles) ? $roles : [],
            is_active: (bool) ($row['is_active'] ?? true),
            cafe_id: isset($row['cafe_id']) ? (int) $row['cafe_id'] : null,
            created_at: (string) ($row['created_at'] ?? ''),
            preferences: isset($row['preferences']) ? (string) $row['preferences'] : null,
        );
    }
}
