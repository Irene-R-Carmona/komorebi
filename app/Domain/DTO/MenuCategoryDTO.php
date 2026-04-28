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
    ) {}

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
