<?php

declare(strict_types=1);

namespace App\Domain\Mappers;

use App\Domain\DTO\ReviewDTO;
use Override;

final readonly class ReviewMapper implements MapperInterface
{
    #[Override]
    public function toDTO(array $row): ReviewDTO
    {
        return new ReviewDTO(
            id: (int) $row['id'],
            user_id: (int) ($row['user_id'] ?? 0),
            cafe_id: (int) $row['cafe_id'],
            cafe_name: (string) ($row['cafe_name'] ?? ''),
            user_name: (string) ($row['user_name'] ?? ''),
            rating: (int) ($row['rating'] ?? 0),
            title: (string) ($row['title'] ?? ''),
            body: (string) ($row['body'] ?? ''),
            status: (string) ($row['status'] ?? 'pending'),
            created_at: (string) ($row['created_at'] ?? ''),
        );
    }
}
