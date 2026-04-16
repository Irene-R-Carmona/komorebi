<?php

declare(strict_types=1);

namespace App\Http\Transformers;

/**
 * Transforma una fila de la tabla `reservations` para la API.
 *
 * Excluye: tracker_id, current_zone_id, protocol_*, deleted_at,
 *          payment_notes (internos/operativos).
 * Normaliza types: ints, strings, bools.
 */
final class ReservationTransformer extends AbstractTransformer
{
    #[\Override]
    public function transform(array $data): array
    {
        return [
            'id' => (int) ($data['id'] ?? 0),
            'uuid' => (string) ($data['uuid'] ?? ''),
            'cafe_id' => (int) ($data['cafe_id'] ?? 0),
            'user_id' => (int) ($data['user_id'] ?? 0),
            'date' => (string) ($data['reservation_date'] ?? ''),
            'time' => (string) ($data['reservation_time'] ?? ''),
            'guest_count' => (int) ($data['guest_count'] ?? 0),
            'status' => (string) ($data['status'] ?? ''),
            'pass_name' => isset($data['pass_name']) ? (string) $data['pass_name'] : null,
            'pass_duration' => isset($data['pass_duration_minutes']) ? (int) $data['pass_duration_minutes'] : null,
            'check_in_at' => isset($data['check_in_at']) ? (string) $data['check_in_at'] : null,
            'check_out_at' => isset($data['check_out_at']) ? (string) $data['check_out_at'] : null,
            'final_amount' => isset($data['final_amount']) ? (float) $data['final_amount'] : null,
            'payment_status' => isset($data['payment_status']) ? (string) $data['payment_status'] : null,
            'payment_method' => isset($data['payment_method']) ? (string) $data['payment_method'] : null,
            'notes' => isset($data['notes']) ? (string) $data['notes'] : null,
            'created_at' => (string) ($data['created_at'] ?? ''),
            'updated_at' => (string) ($data['updated_at'] ?? ''),
        ];
    }
}
