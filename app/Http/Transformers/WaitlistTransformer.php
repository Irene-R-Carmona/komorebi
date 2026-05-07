<?php

declare(strict_types=1);

namespace App\Http\Transformers;

use Override;

/**
 * Transforma una fila del JOIN waitlist + time_slots + cafes para la API.
 *
 * Expone: posición en cola, estado, slot, café — pero nunca datos internos.
 */
final class WaitlistTransformer extends AbstractTransformer
{
    #[Override]
    public function transform(array $data): array
    {
        return [
            'id' => (int) ($data['id'] ?? 0),
            'token' => (string) ($data['token'] ?? ''),
            'status' => (string) ($data['status'] ?? ''),
            'position' => isset($data['position']) ? (int) $data['position'] : null,
            'time_slot_id' => (int) ($data['time_slot_id'] ?? 0),
            'slot_date' => isset($data['slot_date']) ? (string) $data['slot_date'] : null,
            'slot_time' => isset($data['slot_time']) ? (string) $data['slot_time'] : null,
            'cafe_name' => isset($data['cafe_name']) ? (string) $data['cafe_name'] : null,
            'guest_count' => (int) ($data['guest_count'] ?? 0),
            'contact_email' => (string) ($data['contact_email'] ?? ''),
            'special_requests' => isset($data['special_requests']) ? (string) $data['special_requests'] : null,
            'notified_at' => isset($data['notified_at']) ? (string) $data['notified_at'] : null,
            'expires_at' => isset($data['expires_at']) ? (string) $data['expires_at'] : null,
            'created_at' => (string) ($data['created_at'] ?? ''),
            'user_name' => isset($data['user_name']) ? (string) $data['user_name'] : null,
            'user_email' => isset($data['user_email']) ? (string) $data['user_email'] : null,
        ];
    }
}
