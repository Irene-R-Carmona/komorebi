<?php

declare(strict_types=1);

namespace App\Domain\DTO;

use Override;

final readonly class ReservationItemDTO implements DomainTransferObject
{
    public function __construct(
        public int $id,
        public int $reservation_id,
        public int $product_id,
        public int $quantity,
        public float $unit_price,
        public string $status,
        public string $created_at,
    ) {}

    #[Override]
    public function toViewArray(): array
    {
        return [
            'id' => $this->id,
            'reservation_id' => $this->reservation_id,
            'product_id' => $this->product_id,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'status' => $this->status,
            'created_at' => $this->created_at,
        ];
    }
}
