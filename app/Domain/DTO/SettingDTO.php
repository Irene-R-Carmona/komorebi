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
    ) {}

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
