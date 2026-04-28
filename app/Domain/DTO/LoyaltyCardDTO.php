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
    ) {}

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
