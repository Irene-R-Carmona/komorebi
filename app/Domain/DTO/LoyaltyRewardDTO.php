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

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) ($data['id'] ?? 0),
            user_id: (int) ($data['user_id'] ?? 0),
            loyalty_card_id: (int) ($data['loyalty_card_id'] ?? 0),
            reward_type: (string) ($data['reward_type'] ?? ''),
            stamps_cost: (int) ($data['stamps_cost'] ?? 0),
            status: (string) ($data['status'] ?? ''),
            redemption_code: (string) ($data['redemption_code'] ?? ''),
            redeemed_at: (string) ($data['redeemed_at'] ?? ''),
            used_at: isset($data['used_at']) ? (string) $data['used_at'] : null,
            expires_at: isset($data['expires_at']) ? (string) $data['expires_at'] : null,
            notes: isset($data['notes']) ? (string) $data['notes'] : null,
            created_at: (string) ($data['created_at'] ?? ''),
        );
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
