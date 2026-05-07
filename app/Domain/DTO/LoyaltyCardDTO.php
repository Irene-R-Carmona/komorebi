<?php

declare(strict_types=1);

namespace App\Domain\DTO;

use Override;

/**
 * DTO para la tabla `loyalty_cards` (mapeo directo de filas PDO).
 *
 * Nota: `LoyaltyDTO` (ya existente) es un DTO de vista con campos derivados.
 * Este DTO representa los datos crudos de la tarjeta de fidelización.
 */
final readonly class LoyaltyCardDTO implements DomainTransferObject
{
    public function __construct(
        public int $id,
        public int $user_id,
        public int $stamps,
        public string $current_tier,
        public int $visits_count,
        public int $total_rewards_redeemed,
        public ?string $last_stamp_at,
        public string $created_at,
        public string $updated_at,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) ($data['id'] ?? 0),
            user_id: (int) ($data['user_id'] ?? 0),
            stamps: (int) ($data['stamps'] ?? 0),
            current_tier: (string) ($data['current_tier'] ?? 'bronze'),
            visits_count: (int) ($data['visits_count'] ?? 0),
            total_rewards_redeemed: (int) ($data['total_rewards_redeemed'] ?? 0),
            last_stamp_at: isset($data['last_stamp_at']) ? (string) $data['last_stamp_at'] : null,
            created_at: (string) ($data['created_at'] ?? ''),
            updated_at: (string) ($data['updated_at'] ?? ''),
        );
    }

    #[Override]
    public function toViewArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'stamps' => $this->stamps,
            'current_tier' => $this->current_tier,
            'visits_count' => $this->visits_count,
            'total_rewards_redeemed' => $this->total_rewards_redeemed,
            'last_stamp_at' => $this->last_stamp_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
