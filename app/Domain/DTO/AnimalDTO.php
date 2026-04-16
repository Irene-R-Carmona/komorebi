<?php

declare(strict_types=1);

namespace App\Domain\DTO;

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
    ) {
    }

    #[\Override]
    public static function fromArray(array $data): static
    {
        return new static(
            id: (int) $data['id'],
            cafe_id: (int) $data['cafe_id'],
            name: (string) $data['name'],
            species: (string) ($data['species'] ?? ''),
            description: isset($data['description']) ? (string) $data['description'] : null,
            image_url: isset($data['image_url']) ? (string) $data['image_url'] : null,
            is_active: (bool) ($data['is_active'] ?? true),
        );
    }

    #[\Override]
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
