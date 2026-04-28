<?php

declare(strict_types=1);

namespace App\Domain\DTO;

use Override;

final readonly class RoleDTO implements DomainTransferObject
{
    public function __construct(
        public int $id,
        public string $code,
        public string $name,
        public ?string $description,
    ) {}

    #[Override]
    public function toViewArray(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
        ];
    }
}
