<?php

declare(strict_types=1);

namespace App\Domain\DTO;

final readonly class CafeDTO implements DomainTransferObject
{
    public function __construct(
        public int $id,
        public string $slug,
        public string $name,
        public ?string $japanese_name,
        public ?string $description,
        public string $location,
        public string $category,
        public string $animal_type,
        public float $price_per_hour,
        public int $capacity_max,
        public float $rating_avg,
        public string $opening_time,
        public string $closing_time,
        public string $timezone,
        public bool $is_active,
        public ?string $image_url,
    ) {}

    #[\Override]
    public static function fromArray(array $data): static
    {
        return new static(
            id: (int) $data['id'],
            slug: (string) $data['slug'],
            name: (string) $data['name'],
            japanese_name: isset($data['japanese_name']) ? (string) $data['japanese_name'] : null,
            description: isset($data['description']) ? (string) $data['description'] : null,
            location: (string) ($data['location'] ?? ''),
            category: (string) ($data['category'] ?? ''),
            animal_type: (string) ($data['animal_type'] ?? ''),
            price_per_hour: (float) ($data['price_per_hour'] ?? 0.0),
            capacity_max: (int) ($data['capacity_max'] ?? 0),
            rating_avg: (float) ($data['rating_avg'] ?? 0.0),
            opening_time: (string) ($data['opening_time'] ?? ''),
            closing_time: (string) ($data['closing_time'] ?? ''),
            timezone: (string) ($data['timezone'] ?? 'UTC'),
            is_active: (bool) ($data['is_active'] ?? true),
            image_url: isset($data['image_url']) ? (string) $data['image_url'] : null,
        );
    }

    #[\Override]
    public function toViewArray(): array
    {
        return [
            'id'             => $this->id,
            'slug'           => $this->slug,
            'name'           => $this->name,
            'japanese_name'  => $this->japanese_name,
            'description'    => $this->description,
            'location'       => $this->location,
            'category'       => $this->category,
            'animal_type'    => $this->animal_type,
            'price_per_hour' => $this->price_per_hour,
            'capacity_max'   => $this->capacity_max,
            'rating_avg'     => $this->rating_avg,
            'opening_time'   => $this->opening_time,
            'closing_time'   => $this->closing_time,
            'timezone'       => $this->timezone,
            'is_active'      => $this->is_active,
            'image_url'      => $this->image_url,
        ];
    }
}
