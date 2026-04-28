<?php

declare(strict_types=1);

namespace App\Domain\DTO;

use Override;

final readonly class ReviewDTO implements DomainTransferObject
{
    public function __construct(
        public int $id,
        public int $user_id,
        public int $cafe_id,
        public string $cafe_name,
        public string $user_name,
        public int $rating,
        public string $title,
        public string $body,
        public string $status,
        public string $created_at,
    ) {}

    #[Override]
    public function toViewArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'cafe_id' => $this->cafe_id,
            'cafe_name' => $this->cafe_name,
            'user_name' => $this->user_name,
            'rating' => $this->rating,
            'title' => $this->title,
            'body' => $this->body,
            'status' => $this->status,
            'created_at' => $this->created_at,
        ];
    }
}
