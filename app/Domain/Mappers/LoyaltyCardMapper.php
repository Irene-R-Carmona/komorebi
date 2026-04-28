<?php

declare(strict_types=1);

namespace App\Domain\Mappers;

use App\Domain\DTO\LoyaltyCardDTO;
use Override;

final readonly class LoyaltyCardMapper implements MapperInterface
{
    #[Override]
    public function toDTO(array $row): LoyaltyCardDTO
    {
        return new LoyaltyCardDTO(
            id: (int) $row['id'],
            user_id: (int) $row['user_id'],
            stamps: (int) ($row['stamps'] ?? 0),
            current_tier: (string) ($row['current_tier'] ?? 'bronze'),
            visits_count: (int) ($row['visits_count'] ?? 0),
            total_rewards_redeemed: (int) ($row['total_rewards_redeemed'] ?? 0),
            last_stamp_at: isset($row['last_stamp_at']) ? (string) $row['last_stamp_at'] : null,
            created_at: (string) $row['created_at'],
            updated_at: (string) $row['updated_at'],
        );
    }
}
