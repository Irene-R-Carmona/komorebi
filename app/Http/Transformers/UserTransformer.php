<?php

declare(strict_types=1);

namespace App\Http\Transformers;

/**
 * Transforma una fila de la tabla `users` para la API.
 *
 * Excluye SIEMPRE: password, login_attempts, locked_until,
 *                  last_ip_address, last_login, deleted_at,
 *                  anonymized_at, email_verified_at, preferences.
 */
final class UserTransformer extends AbstractTransformer
{
    #[\Override]
    public function transform(array $data): array
    {
        return [
            'id'         => (int) ($data['id'] ?? 0),
            'uuid'       => (string) ($data['uuid'] ?? ''),
            'name'       => (string) ($data['name'] ?? ''),
            'email'      => (string) ($data['email'] ?? ''),
            'avatar'     => isset($data['avatar']) ? (string) $data['avatar'] : null,
            'role'       => isset($data['role']) ? (string) $data['role'] : null,
            'cafe_id'    => isset($data['cafe_id']) ? (int) $data['cafe_id'] : null,
            'is_active'  => (bool) ($data['is_active'] ?? false),
            'created_at' => (string) ($data['created_at'] ?? ''),
        ];
    }
}
