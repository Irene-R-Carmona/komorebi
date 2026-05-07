<?php

declare(strict_types=1);

namespace App\Domain\DTO;

use Override;

final readonly class PermissionDTO implements DomainTransferObject
{
    public function __construct(
        public int $id,
        public string $code,
        public string $name,
        public ?string $description,
        public ?string $resource,
        public ?string $action,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) ($data['id'] ?? 0),
            code: (string) ($data['code'] ?? ''),
            name: (string) ($data['name'] ?? ''),
            description: isset($data['description']) ? (string) $data['description'] : null,
            resource: isset($data['resource']) ? (string) $data['resource'] : null,
            action: isset($data['action']) ? (string) $data['action'] : null,
        );
    }

    #[Override]
    public function toViewArray(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'resource' => $this->resource,
            'action' => $this->action,
        ];
    }
}
