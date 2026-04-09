<?php

declare(strict_types=1);

namespace App\Http\Transformers;

/**
 * Transforma una fila del JOIN reviews + users + cafes para la API.
 *
 * Expone solo campos públicos (sin IDs internos de usuario).
 */
final class ReviewTransformer extends AbstractTransformer
{
    #[\Override]
    public function transform(array $data): array
    {
        return [
            'id'         => (int) ($data['id'] ?? 0),
            'cafe_id'    => (int) ($data['cafe_id'] ?? 0),
            'cafe_name'  => isset($data['cafe_name']) ? (string) $data['cafe_name'] : null,
            'user_name'  => isset($data['user_name']) ? (string) $data['user_name'] : null,
            'rating'     => (int) ($data['rating'] ?? 0),
            'title'      => (string) ($data['title'] ?? ''),
            'body'       => (string) ($data['body'] ?? ''),
            'status'     => (string) ($data['status'] ?? ''),
            'created_at' => (string) ($data['created_at'] ?? ''),
        ];
    }
}
