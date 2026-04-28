<?php

declare(strict_types=1);

namespace App\Domain\Mappers;

use App\Domain\DTO\ReservationItemDTO;
use Override;

final readonly class ReservationItemMapper implements MapperInterface
{
    #[Override]
    public function toDTO(array $row): ReservationItemDTO
    {
        return new ReservationItemDTO(
            id: (int) $row['id'],
            reservation_id: (int) $row['reservation_id'],
            product_id: (int) $row['product_id'],
            quantity: (int) ($row['quantity'] ?? 1),
            unit_price: (float) ($row['unit_price'] ?? 0.0),
            status: (string) ($row['status'] ?? 'pending'),
            created_at: (string) $row['created_at'],
        );
    }
}
