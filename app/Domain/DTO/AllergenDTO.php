<?php

declare(strict_types=1);

namespace App\Domain\DTO;

use Override;

final readonly class AllergenDTO implements DomainTransferObject
{
    public function __construct(
        public int $id,
        public string $code,
        public string $name,
        public ?string $japanese_name,
        public ?string $icon_class,
        public ?string $icon_color,
        public string $severity,
        public ?string $description,
    ) {}

    #[Override]
    public function toViewArray(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'japanese_name' => $this->japanese_name,
            'icon_class' => $this->icon_class,
            'icon_color' => $this->icon_color,
            'severity' => $this->severity,
            'description' => $this->description,
        ];
    }
}
