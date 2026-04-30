<?php

declare(strict_types=1);

namespace App\Domain\DTO;

use Override;

final readonly class LoyaltyRewardDTO implements DomainTransferObject
{
    public function __construct(
        public int $id,
        public int $user_id,
        public int $loyalty_card_id,
        public string $reward_type,
        public int $stamps_cost,
        public string $status,
        public string $redemption_code,
        public string $redeemed_at,
        public ?string $used_at,
        public ?string $expires_at,
        public ?string $notes,
        public string $created_at,
    ) {
    }

    #[Override]
    public function toViewArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'loyalty_card_id' => $this->loyalty_card_id,
            'reward_type' => $this->reward_type,
            'stamps_cost' => $this->stamps_cost,
            'status' => $this->status,
            'redemption_code' => $this->redemption_code,
            'redeemed_at' => $this->redeemed_at,
            'used_at' => $this->used_at,
            'expires_at' => $this->expires_at,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
        ];
    }
}
