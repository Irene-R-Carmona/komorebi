<?php

declare(strict_types=1);

namespace App\Domain\Mappers;

use App\Domain\DTO\LoyaltyRewardCatalogDTO;
use Override;

final readonly class LoyaltyRewardCatalogMapper implements MapperInterface
{
    #[Override]
    public function toDTO(array $row): LoyaltyRewardCatalogDTO
    {
        return new LoyaltyRewardCatalogDTO(
            id: (int) $row['id'],
            reward_type: (string) $row['reward_type'],
            name_es: (string) $row['name_es'],
            name_en: (string) $row['name_en'],
            stamps_required: (int) ($row['stamps_required'] ?? 0),
            tier_required: (string) ($row['tier_required'] ?? 'bronze'),
            validity_days: (int) ($row['validity_days'] ?? 30),
            is_active: (bool) ($row['is_active'] ?? true),
            display_order: (int) ($row['display_order'] ?? 0),
            icon: isset($row['icon']) ? (string) $row['icon'] : null,
        );
    }
}
