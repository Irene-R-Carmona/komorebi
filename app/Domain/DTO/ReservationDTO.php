<?php

declare(strict_types=1);

namespace App\Domain\DTO;

use Override;

final readonly class ReservationDTO implements DomainTransferObject
{
    public function __construct(
        public int $id,
        public string $uuid,
        public int $cafe_id,
        public int $user_id,
        public string $date,
        public string $time,
        public int $guest_count,
        public string $status,
        public ?int $time_slot_id,
        public ?string $pass_name,
        public ?string $check_in_at,
        public ?string $check_out_at,
        public ?float $final_amount,
        public ?string $payment_status,
        public ?string $payment_method,
        public ?string $notes,
    ) {
    }

    #[Override]
    public function toViewArray(): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'cafe_id' => $this->cafe_id,
            'user_id' => $this->user_id,
            'date' => $this->date,
            'time' => $this->time,
            'guest_count' => $this->guest_count,
            'status' => $this->status,
            'time_slot_id' => $this->time_slot_id,
            'pass_name' => $this->pass_name,
            'check_in_at' => $this->check_in_at,
            'check_out_at' => $this->check_out_at,
            'final_amount' => $this->final_amount,
            'payment_status' => $this->payment_status,
            'payment_method' => $this->payment_method,
            'notes' => $this->notes,
        ];
    }
}
