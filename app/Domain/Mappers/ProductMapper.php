<?php

declare(strict_types=1);

namespace App\Domain\Mappers;

use App\Domain\DTO\ProductDTO;
use Override;

final readonly class ProductMapper implements MapperInterface
{
    #[Override]
    public function toDTO(array $row): ProductDTO
    {
        $allergens = $row['allergens'] ?? [];
        if (\is_string($allergens)) {
            $allergens = \json_decode($allergens, true) ?? [];
        }

        return new ProductDTO(
            id: (int) $row['id'],
            name: (string) $row['name'],
            slug: (string) $row['slug'],
            description: isset($row['description']) ? (string) $row['description'] : null,
            price: (float) ($row['price'] ?? 0.0),
            category_id: (int) $row['category_id'],
            category_name: (string) ($row['category_name'] ?? ''),
            allergens: \is_array($allergens) ? $allergens : [],
            is_active: (bool) ($row['is_active'] ?? true),
            image_url: isset($row['image_url']) ? (string) $row['image_url'] : null,
            product_type: (string) ($row['product_type'] ?? 'item'),
            min_pax: isset($row['min_pax']) ? (int) $row['min_pax'] : null,
            max_pax: isset($row['max_pax']) ? (int) $row['max_pax'] : null,
            duration_minutes: isset($row['duration_minutes']) ? (int) $row['duration_minutes'] : null,
            attributes: isset($row['attributes']) ? (string) $row['attributes'] : null,
            target_cafe_types: isset($row['target_cafe_types']) ? (string) $row['target_cafe_types'] : null,
            target_animal_types: isset($row['target_animal_types']) ? (string) $row['target_animal_types'] : null,
            stock_quantity: isset($row['stock_quantity']) ? (int) $row['stock_quantity'] : null,
        );
    }
}
