<?php

declare(strict_types=1);

namespace App\Domain\DTO;

use Override;

final readonly class WaitlistEntryDTO implements DomainTransferObject
{
    public function __construct(
        public int $id,
        public string $token,
        public string $status,
        public ?int $position,
        public int $time_slot_id,
        public int $user_id,
        public string $slot_date,
        public string $slot_time,
        public string $cafe_name,
        public int $guest_count,
        public string $contact_email,
        public ?string $expires_at,
        public ?string $special_requests,
        public ?string $created_at = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) ($data['id'] ?? 0),
            token: (string) ($data['token'] ?? ''),
            status: (string) ($data['status'] ?? 'waiting'),
            position: isset($data['position']) ? (int) $data['position'] : null,
            time_slot_id: (int) ($data['time_slot_id'] ?? 0),
            user_id: (int) ($data['user_id'] ?? 0),
            slot_date: (string) ($data['slot_date'] ?? ''),
            slot_time: (string) ($data['slot_time'] ?? ''),
            cafe_name: (string) ($data['cafe_name'] ?? ''),
            guest_count: (int) ($data['guest_count'] ?? 1),
            contact_email: (string) ($data['contact_email'] ?? ''),
            expires_at: isset($data['expires_at']) ? (string) $data['expires_at'] : null,
            special_requests: isset($data['special_requests']) ? (string) $data['special_requests'] : null,
            created_at: isset($data['created_at']) ? (string) $data['created_at'] : null,
        );
    }

    #[Override]
    public function toViewArray(): array
    {
        return [
            'id' => $this->id,
            'token' => $this->token,
            'status' => $this->status,
            'position' => $this->position,
            'time_slot_id' => $this->time_slot_id,
            'user_id' => $this->user_id,
            'slot_date' => $this->slot_date,
            'slot_time' => $this->slot_time,
            'cafe_name' => $this->cafe_name,
            'guest_count' => $this->guest_count,
            'contact_email' => $this->contact_email,
            'expires_at' => $this->expires_at,
            'special_requests' => $this->special_requests,
        ];
    }
}
