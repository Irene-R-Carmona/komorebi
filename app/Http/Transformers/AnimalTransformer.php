<?php

declare(strict_types=1);

namespace App\Http\Transformers;

/**
 * Transforma una fila de la tabla `animals` para la API pública.
 *
 * Excluye SIEMPRE campos sensibles u operativos:
 *   last_check_at (deprecated), last_health_check, current_zone_id, deleted_at.
 */
final class AnimalTransformer extends AbstractTransformer
{
    #[\Override]
    public function transform(array $data): array
    {
        return [
            'id' => (int) ($data['id'] ?? 0),
            'cafe_id' => (int) ($data['cafe_id'] ?? 0),
            'name' => (string) ($data['name'] ?? ''),
            'species_type' => (string) ($data['species_type'] ?? ''),
            'age' => isset($data['age']) ? (int) $data['age'] : null,
            'personality' => isset($data['personality']) ? (string) $data['personality'] : null,
            'description' => isset($data['description']) ? (string) $data['description'] : null,
            'interaction_level' => (int) ($data['interaction_level'] ?? 3),
            'attributes' => \is_array($data['attributes'] ?? null) ? $data['attributes'] : null,
            'image_url' => isset($data['image_url']) ? (string) $data['image_url'] : null,
            'current_status' => (string) ($data['current_status'] ?? 'active'),
        ];
    }
}
