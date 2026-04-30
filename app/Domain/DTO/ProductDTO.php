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
