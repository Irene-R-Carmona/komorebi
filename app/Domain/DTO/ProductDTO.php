<?php

declare(strict_types=1);

namespace App\Domain\DTO;

use Override;

final readonly class ProductDTO implements DomainTransferObject
{
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
        public ?string $description,
        public float $price,
        public int $category_id,
        public string $category_name,
        public array $allergens,
        public bool $is_active,
        public ?string $image_url,
        public string $product_type,
        public ?int $min_pax,
        public ?int $max_pax,
        public ?int $duration_minutes,
        public ?string $attributes,
        public ?string $target_cafe_types,
        public ?string $target_animal_types,
        public ?int $stock_quantity,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) ($data['id'] ?? 0),
            name: (string) ($data['name'] ?? ''),
            slug: (string) ($data['slug'] ?? ''),
            description: isset($data['description']) ? (string) $data['description'] : null,
            price: (float) ($data['price'] ?? 0.0),
            category_id: (int) ($data['category_id'] ?? 0),
            category_name: (string) ($data['category_name'] ?? ''),
            allergens: (array) ($data['allergens'] ?? []),
            is_active: (bool) ($data['is_active'] ?? false),
            image_url: isset($data['image_url']) ? (string) $data['image_url'] : null,
            product_type: (string) ($data['product_type'] ?? ''),
            min_pax: isset($data['min_pax']) ? (int) $data['min_pax'] : null,
            max_pax: isset($data['max_pax']) ? (int) $data['max_pax'] : null,
            duration_minutes: isset($data['duration_minutes']) ? (int) $data['duration_minutes'] : null,
            attributes: isset($data['attributes']) ? (string) $data['attributes'] : null,
            target_cafe_types: isset($data['target_cafe_types']) ? (string) $data['target_cafe_types'] : null,
            target_animal_types: isset($data['target_animal_types']) ? (string) $data['target_animal_types'] : null,
            stock_quantity: isset($data['stock_quantity']) ? (int) $data['stock_quantity'] : null,
        );
    }

    #[Override]
    public function toViewArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'price' => $this->price,
            'category_id' => $this->category_id,
            'category_name' => $this->category_name,
            'allergens' => $this->allergens,
            'is_active' => $this->is_active,
            'image_url' => $this->image_url,
            'product_type' => $this->product_type,
            'min_pax' => $this->min_pax,
            'max_pax' => $this->max_pax,
            'duration_minutes' => $this->duration_minutes,
            'attributes' => $this->attributes,
            'target_cafe_types' => $this->target_cafe_types,
            'target_animal_types' => $this->target_animal_types,
            'stock_quantity' => $this->stock_quantity,
        ];
    }
}
