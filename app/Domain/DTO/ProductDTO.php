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
        public bool $is_available,
        public ?string $image_url,
    ) {
    }

    #[Override]
    public static function fromArray(array $data): static
    {
        return new static(
            id: (int) $data['id'],
            name: (string) $data['name'],
            slug: (string) $data['slug'],
            description: isset($data['description']) ? (string) $data['description'] : null,
            price: (float) ($data['price'] ?? 0.0),
            category_id: (int) ($data['category_id'] ?? 0),
            category_name: (string) ($data['category_name'] ?? ''),
            allergens: \is_array($data['allergens'] ?? null) ? $data['allergens'] : [],
            is_available: (bool) ($data['is_available'] ?? true),
            image_url: isset($data['image_url']) ? (string) $data['image_url'] : null,
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
            'is_available' => $this->is_available,
            'image_url' => $this->image_url,
        ];
    }
}
