<?php

declare(strict_types=1);

namespace App\Domain\Mappers;

use App\Domain\DTO\FavoriteDTO;
use Override;

final readonly class FavoriteMapper implements MapperInterface
{
    #[Override]
    public function toDTO(array $row): FavoriteDTO
    {
        return new FavoriteDTO(
            user_id: (int) $row['user_id'],
            cafe_id: (int) $row['cafe_id'],
            created_at: (string) $row['created_at'],
        );
    }
}
