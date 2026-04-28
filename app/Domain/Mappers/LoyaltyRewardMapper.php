<?php

declare(strict_types=1);

namespace App\Domain\Mappers;

use App\Domain\DTO\LoyaltyRewardDTO;
use Override;

final readonly class LoyaltyRewardMapper implements MapperInterface
{
    #[Override]
    public function toDTO(array $row): LoyaltyRewardDTO
    {
        return new LoyaltyRewardDTO(
            id: (int) $row['id'],
            user_id: (int) $row['user_id'],
            loyalty_card_id: (int) $row['loyalty_card_id'],
            reward_type: (string) $row['reward_type'],
            stamps_cost: (int) ($row['stamps_cost'] ?? 0),
            status: (string) ($row['status'] ?? 'pending'),
            redemption_code: (string) $row['redemption_code'],
            redeemed_at: (string) $row['redeemed_at'],
            used_at: isset($row['used_at']) ? (string) $row['used_at'] : null,
            expires_at: isset($row['expires_at']) ? (string) $row['expires_at'] : null,
            notes: isset($row['notes']) ? (string) $row['notes'] : null,
            created_at: (string) $row['created_at'],
        );
    }
}
