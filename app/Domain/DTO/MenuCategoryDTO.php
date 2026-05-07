<?php

declare(strict_types=1);

namespace App\Domain\DTO;

use Override;

final readonly class MenuCategoryDTO implements DomainTransferObject
{
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
        public int $display_order,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) ($data['id'] ?? 0),
            name: (string) ($data['name'] ?? ''),
            slug: (string) ($data['slug'] ?? ''),
            display_order: (int) ($data['display_order'] ?? 0),
        );
    }

    #[Override]
    public function toViewArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'display_order' => $this->display_order,
        ];
    }
}
