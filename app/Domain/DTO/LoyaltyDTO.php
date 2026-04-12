<?php

declare(strict_types=1);

namespace App\Domain\DTO;

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

    #[\Override]
    public static function fromArray(array $data): static
    {
        return new static(
            user_id: (int) $data['user_id'],
            points_balance: (int) ($data['points_balance'] ?? 0),
            tier_name: (string) ($data['tier_name'] ?? 'bronze'),
            tier_level: (int) ($data['tier_level'] ?? 1),
            stamps_count: (int) ($data['stamps_count'] ?? 0),
            next_reward_at: isset($data['next_reward_at']) ? (int) $data['next_reward_at'] : null,
        );
    }

    #[\Override]
    public function toViewArray(): array
    {
        return [
            'user_id'        => $this->user_id,
            'points_balance' => $this->points_balance,
            'tier_name'      => $this->tier_name,
            'tier_level'     => $this->tier_level,
            'stamps_count'   => $this->stamps_count,
            'next_reward_at' => $this->next_reward_at,
        ];
    }
}
