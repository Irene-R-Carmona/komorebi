<?php

declare(strict_types=1);

namespace App\Domain\DTO;

use Override;

final readonly class AnimalDTO implements DomainTransferObject
{
    public function __construct(
        public int $id,
        public int $cafe_id,
        public string $name,
        public string $species,
        public ?string $description,
        public ?string $image_url,
        public bool $is_active,
    ) {}

    #[Override]
    public function toViewArray(): array
    {
        return [
            'id' => $this->id,
            'cafe_id' => $this->cafe_id,
            'name' => $this->name,
            'species' => $this->species,
            'description' => $this->description,
            'image_url' => $this->image_url,
            'is_active' => $this->is_active,
        ];
    }
}
