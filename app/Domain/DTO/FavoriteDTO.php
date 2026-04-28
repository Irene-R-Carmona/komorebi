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
    ) {}

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
