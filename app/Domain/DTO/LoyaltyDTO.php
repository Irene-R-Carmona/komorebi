<?php

declare(strict_types=1);

namespace App\Domain\DTO;

use Override;

final readonly class LoyaltyDTO implements DomainTransferObject
{
    public function __construct(
        public int $user_id,
        public int $points_balance,
        public string $tier_name,
        public int $tier_level,
        public int $stamps_count,
        public ?int $next_reward_at,
    ) {}

    #[Override]
    public function toViewArray(): array
    {
        return [
            'user_id' => $this->user_id,
            'points_balance' => $this->points_balance,
            'tier_name' => $this->tier_name,
            'tier_level' => $this->tier_level,
            'stamps_count' => $this->stamps_count,
            'next_reward_at' => $this->next_reward_at,
        ];
    }
}
