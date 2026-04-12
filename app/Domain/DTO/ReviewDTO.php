<?php

declare(strict_types=1);

namespace App\Domain\DTO;

final readonly class ReviewDTO implements DomainTransferObject
{
    public function __construct(
        public int $id,
        public int $cafe_id,
        public string $cafe_name,
        public string $user_name,
        public int $rating,
        public string $title,
        public string $body,
        public string $status,
        public string $created_at,
    ) {}

    #[\Override]
    public static function fromArray(array $data): static
    {
        return new static(
            id: (int) $data['id'],
            cafe_id: (int) $data['cafe_id'],
            cafe_name: (string) ($data['cafe_name'] ?? ''),
            user_name: (string) ($data['user_name'] ?? ''),
            rating: (int) ($data['rating'] ?? 0),
            title: (string) ($data['title'] ?? ''),
            body: (string) ($data['body'] ?? ''),
            status: (string) ($data['status'] ?? 'pending'),
            created_at: (string) ($data['created_at'] ?? ''),
        );
    }

    #[\Override]
    public function toViewArray(): array
    {
        return [
            'id'         => $this->id,
            'cafe_id'    => $this->cafe_id,
            'cafe_name'  => $this->cafe_name,
            'user_name'  => $this->user_name,
            'rating'     => $this->rating,
            'title'      => $this->title,
            'body'       => $this->body,
            'status'     => $this->status,
            'created_at' => $this->created_at,
        ];
    }
}
