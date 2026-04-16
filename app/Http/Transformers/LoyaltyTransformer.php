<?php

declare(strict_types=1);

namespace App\Http\Transformers;

/**
 * Transforma una fila de `loyalty_cards` para la API.
 *
 * Excluye SIEMPRE: updated_at (interno), created_at (no relevante para el cliente).
 */
final class LoyaltyTransformer extends AbstractTransformer
{
    #[\Override]
    public function transform(array $data): array
    {
        return [
            'id' => (int) ($data['id'] ?? 0),
            'user_id' => (int) ($data['user_id'] ?? 0),
            'stamps_count' => (int) ($data['stamps'] ?? 0),
            'tier' => (string) ($data['current_tier'] ?? 'bronze'),
            'visits_count' => (int) ($data['visits_count'] ?? 0),
            'total_rewards_redeemed' => (int) ($data['total_rewards_redeemed'] ?? 0),
            'last_stamp_at' => isset($data['last_stamp_at']) ? (string) $data['last_stamp_at'] : null,
        ];
    }
}
