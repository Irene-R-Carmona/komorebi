<?php

declare(strict_types=1);

namespace App\Domain\DTO;

use Override;

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
        public bool $has_reservations,
        public ?string $image_url,
    ) {
    }

    #[Override]
    public function toViewArray(): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'japanese_name' => $this->japanese_name,
            'description' => $this->description,
            'location' => $this->location,
            'category' => $this->category,
            'animal_type' => $this->animal_type,
            'price_per_hour' => $this->price_per_hour,
            'capacity_max' => $this->capacity_max,
            'rating_avg' => $this->rating_avg,
            'opening_time' => $this->opening_time,
            'closing_time' => $this->closing_time,
            'timezone' => $this->timezone,
            'is_active' => $this->is_active,
            'has_reservations' => $this->has_reservations,
            'image_url' => $this->image_url,
        ];
    }
}
