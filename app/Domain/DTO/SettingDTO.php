<?php

declare(strict_types=1);

namespace App\Domain\DTO;

use Override;

final readonly class SettingDTO implements DomainTransferObject
{
    public function __construct(
        public string $key,
        public string $value,
        public string $type,
        public string $group_name,
        public ?string $description,
        public bool $is_public,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            key: (string) ($data['key'] ?? ''),
            value: (string) ($data['value'] ?? ''),
            type: (string) ($data['type'] ?? ''),
            group_name: (string) ($data['group_name'] ?? ''),
            description: isset($data['description']) ? (string) $data['description'] : null,
            is_public: (bool) ($data['is_public'] ?? false),
        );
    }

    #[Override]
    public function toViewArray(): array
    {
        return [
            'key' => $this->key,
            'value' => $this->value,
            'type' => $this->type,
            'group_name' => $this->group_name,
            'description' => $this->description,
            'is_public' => $this->is_public,
        ];
    }
}
