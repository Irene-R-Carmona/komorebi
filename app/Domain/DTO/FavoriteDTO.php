<?php

declare(strict_types=1);

namespace App\Domain\DTO;

use Override;

final readonly class FavoriteDTO implements DomainTransferObject
{
    public function __construct(
        public int $user_id,
        public int $cafe_id,
        public string $created_at,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            user_id: (int) ($data['user_id'] ?? 0),
            cafe_id: (int) ($data['cafe_id'] ?? 0),
            created_at: (string) ($data['created_at'] ?? ''),
        );
    }

    #[Override]
    public function toViewArray(): array
    {
        return [
            'user_id' => $this->user_id,
            'cafe_id' => $this->cafe_id,
            'created_at' => $this->created_at,
        ];
    }
}
