<?php

declare(strict_types=1);

namespace App\Domain\DTO;

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
        public ?string $pass_name,
        public ?string $check_in_at,
        public ?string $check_out_at,
        public ?float $final_amount,
        public ?string $payment_status,
        public ?string $payment_method,
        public ?string $notes,
    ) {}

    #[\Override]
    public static function fromArray(array $data): static
    {
        return new static(
            id: (int) $data['id'],
            uuid: (string) $data['uuid'],
            cafe_id: (int) $data['cafe_id'],
            user_id: (int) $data['user_id'],
            date: (string) $data['date'],
            time: (string) $data['time'],
            guest_count: (int) ($data['guest_count'] ?? 1),
            status: (string) ($data['status'] ?? 'pending'),
            pass_name: isset($data['pass_name']) ? (string) $data['pass_name'] : null,
            check_in_at: isset($data['check_in_at']) ? (string) $data['check_in_at'] : null,
            check_out_at: isset($data['check_out_at']) ? (string) $data['check_out_at'] : null,
            final_amount: isset($data['final_amount']) ? (float) $data['final_amount'] : null,
            payment_status: isset($data['payment_status']) ? (string) $data['payment_status'] : null,
            payment_method: isset($data['payment_method']) ? (string) $data['payment_method'] : null,
            notes: isset($data['notes']) ? (string) $data['notes'] : null,
        );
    }

    #[\Override]
    public function toViewArray(): array
    {
        return [
            'id'             => $this->id,
            'uuid'           => $this->uuid,
            'cafe_id'        => $this->cafe_id,
            'user_id'        => $this->user_id,
            'date'           => $this->date,
            'time'           => $this->time,
            'guest_count'    => $this->guest_count,
            'status'         => $this->status,
            'pass_name'      => $this->pass_name,
            'check_in_at'    => $this->check_in_at,
            'check_out_at'   => $this->check_out_at,
            'final_amount'   => $this->final_amount,
            'payment_status' => $this->payment_status,
            'payment_method' => $this->payment_method,
            'notes'          => $this->notes,
        ];
    }
}
