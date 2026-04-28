<?php

declare(strict_types=1);

namespace App\Domain\Mappers;

use App\Domain\DTO\SettingDTO;
use Override;

final readonly class SettingMapper implements MapperInterface
{
    #[Override]
    public function toDTO(array $row): SettingDTO
    {
        return new SettingDTO(
            key: (string) $row['key'],
            value: (string) $row['value'],
            type: (string) ($row['type'] ?? 'string'),
            group_name: (string) ($row['group_name'] ?? 'general'),
            description: isset($row['description']) ? (string) $row['description'] : null,
            is_public: (bool) ($row['is_public'] ?? false),
        );
    }
}
