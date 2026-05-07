<?php

declare(strict_types=1);

namespace App\Http\Transformers;

use Override;

/**
 * Transforma una fila de la tabla `products` para la API pública.
 *
 * Excluye SIEMPRE campos operativos de cocina:
 *   station, recipe_steps, critical_check, prep_time, ingredients_list.
 */
final class ProductTransformer extends AbstractTransformer
{
    #[Override]
    public function transform(array $data): array
    {
        return [
            'id' => (int) ($data['id'] ?? 0),
            'name' => (string) ($data['name'] ?? ''),
            'japanese_name' => isset($data['japanese_name']) ? (string) $data['japanese_name'] : null,
            'slug' => (string) ($data['slug'] ?? ''),
            'description' => isset($data['description']) ? (string) $data['description'] : null,
            'price' => (int) ($data['price'] ?? 0),
            'product_type' => (string) ($data['product_type'] ?? 'item'),
            'category_id' => (int) ($data['category_id'] ?? 0),
            'category_name' => isset($data['category_name']) ? (string) $data['category_name'] : null,
            'allergens' => \is_array($data['allergens'] ?? null) ? $data['allergens'] : [],
            'attributes' => \is_array($data['attributes'] ?? null) ? $data['attributes'] : null,
            'target_cafe_types' => \is_array($data['target_cafe_types'] ?? null) ? $data['target_cafe_types'] : null,
            'target_animal_types' => \is_array($data['target_animal_types'] ?? null) ? $data['target_animal_types'] : null,
            'calories' => isset($data['calories']) ? (int) $data['calories'] : null,
            'duration_minutes' => isset($data['duration_minutes']) ? (int) $data['duration_minutes'] : null,
            'min_pax' => isset($data['min_pax']) ? (int) $data['min_pax'] : null,
            'max_pax' => isset($data['max_pax']) ? (int) $data['max_pax'] : null,
            'image_url' => isset($data['image_url']) ? (string) $data['image_url'] : null,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }
}
