<?php

declare(strict_types=1);

namespace App\Domain\DTO;

use Override;

final readonly class UserDTO implements DomainTransferObject
{
    public function __construct(
        public int $id,
        public string $uuid,
        public string $name,
        public string $email,
        public ?string $avatar,
        public array $roles,
        public bool $is_active,
        public ?int $cafe_id,
        public string $created_at,
        public ?string $preferences = null,
    ) {}

    #[Override]
    public function toViewArray(): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'email' => $this->email,
            'avatar' => $this->avatar,
            'roles' => $this->roles,
            'is_active' => $this->is_active,
            'cafe_id' => $this->cafe_id,
            'created_at' => $this->created_at,
            'preferences' => $this->preferences,
        ];
    }
}
