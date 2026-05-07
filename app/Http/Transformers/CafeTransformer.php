<?php

declare(strict_types=1);

namespace App\Http\Transformers;

use Override;

/**
 * Transforma una fila de la tabla `cafes` para la API pública.
 *
 * Excluye: deleted_at, updated_at (internos).
 * Normaliza types: floats, ints, bools.
 */
final class CafeTransformer extends AbstractTransformer
{
    #[Override]
    public function transform(array $data): array
    {
        return [
            'id' => (int) ($data['id'] ?? 0),
            'slug' => (string) ($data['slug'] ?? ''),
            'name' => (string) ($data['name'] ?? ''),
            'japanese_name' => (string) ($data['japanese_name'] ?? ''),
            'location' => (string) ($data['location'] ?? ''),
            'category' => (string) ($data['category'] ?? ''),
            'animal_type' => (string) ($data['animal_type'] ?? ''),
            'description' => (string) ($data['description'] ?? ''),
            'price_per_hour' => (float) ($data['price_per_hour'] ?? 0.0),
            'rating_avg' => (float) ($data['rating_avg'] ?? 0.0),
            'rating_count' => (int) ($data['rating_count'] ?? 0),
            'opening_time' => (string) ($data['opening_time'] ?? ''),
            'closing_time' => (string) ($data['closing_time'] ?? ''),
            'capacity_max' => (int) ($data['capacity_max'] ?? 0),
            'latitude' => isset($data['latitude']) ? (float) $data['latitude'] : null,
            'longitude' => isset($data['longitude']) ? (float) $data['longitude'] : null,
            'timezone' => (string) ($data['timezone'] ?? 'Europe/Madrid'),
            'is_active' => (bool) ($data['is_active'] ?? false),
            'has_reservations' => (bool) ($data['has_reservations'] ?? false),
            'image_url' => isset($data['image_url']) ? (string) $data['image_url'] : null,
            'created_at' => (string) ($data['created_at'] ?? ''),
        ];
    }
}
