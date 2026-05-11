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
        public ?int $pass_duration_minutes,
        public ?string $check_in_at,
        public ?string $check_out_at,
        public ?float $final_amount,
        public ?string $payment_status,
        public ?string $payment_method,
        public ?string $notes,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) ($data['id'] ?? 0),
            uuid: (string) ($data['uuid'] ?? ''),
            cafe_id: (int) ($data['cafe_id'] ?? 0),
            user_id: (int) ($data['user_id'] ?? 0),
            date: (string) ($data['date'] ?? ''),
            time: (string) ($data['time'] ?? ''),
            guest_count: (int) ($data['guest_count'] ?? 1),
            status: (string) ($data['status'] ?? 'pending'),
            time_slot_id: isset($data['time_slot_id']) ? (int) $data['time_slot_id'] : null,
            pass_name: isset($data['pass_name']) ? (string) $data['pass_name'] : null,
            pass_duration_minutes: isset($data['pass_duration_minutes']) ? (int) $data['pass_duration_minutes'] : null,
            check_in_at: isset($data['check_in_at']) ? (string) $data['check_in_at'] : null,
            check_out_at: isset($data['check_out_at']) ? (string) $data['check_out_at'] : null,
            final_amount: isset($data['final_amount']) ? (float) $data['final_amount'] : null,
            payment_status: isset($data['payment_status']) ? (string) $data['payment_status'] : null,
            payment_method: isset($data['payment_method']) ? (string) $data['payment_method'] : null,
            notes: isset($data['notes']) ? (string) $data['notes'] : null,
        );
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
