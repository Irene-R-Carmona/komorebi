<?php

declare(strict_types=1);

namespace App\Domain\DTO;

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
    ) {
    }

    #[\Override]
    public static function fromArray(array $data): static
    {
        return new static(
            id: (int) $data['id'],
            uuid: (string) $data['uuid'],
            name: (string) $data['name'],
            email: (string) $data['email'],
            avatar: isset($data['avatar']) ? (string) $data['avatar'] : null,
            roles: \is_array($data['roles'] ?? null) ? $data['roles'] : [],
            is_active: (bool) ($data['is_active'] ?? true),
            cafe_id: isset($data['cafe_id']) ? (int) $data['cafe_id'] : null,
            created_at: (string) ($data['created_at'] ?? ''),
        );
    }

    #[\Override]
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
        ];
    }
}
