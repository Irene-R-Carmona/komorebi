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
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) ($data['id'] ?? 0),
            code: (string) ($data['code'] ?? ''),
            name: (string) ($data['name'] ?? ''),
            japanese_name: isset($data['japanese_name']) ? (string) $data['japanese_name'] : null,
            icon_class: isset($data['icon_class']) ? (string) $data['icon_class'] : null,
            icon_color: isset($data['icon_color']) ? (string) $data['icon_color'] : null,
            severity: (string) ($data['severity'] ?? ''),
            description: isset($data['description']) ? (string) $data['description'] : null,
        );
    }

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
