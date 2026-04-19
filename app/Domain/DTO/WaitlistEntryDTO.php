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
        public string $slot_date,
        public string $slot_time,
        public string $cafe_name,
        public int $guest_count,
        public string $contact_email,
        public ?string $expires_at,
    ) {
    }

    #[Override]
    public static function fromArray(array $data): static
    {
        return new static(
            id: (int) $data['id'],
            token: (string) $data['token'],
            status: (string) ($data['status'] ?? 'waiting'),
            position: isset($data['position']) ? (int) $data['position'] : null,
            slot_date: (string) ($data['slot_date'] ?? ''),
            slot_time: (string) ($data['slot_time'] ?? ''),
            cafe_name: (string) ($data['cafe_name'] ?? ''),
            guest_count: (int) ($data['guest_count'] ?? 1),
            contact_email: (string) ($data['contact_email'] ?? ''),
            expires_at: isset($data['expires_at']) ? (string) $data['expires_at'] : null,
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
            'slot_date' => $this->slot_date,
            'slot_time' => $this->slot_time,
            'cafe_name' => $this->cafe_name,
            'guest_count' => $this->guest_count,
            'contact_email' => $this->contact_email,
            'expires_at' => $this->expires_at,
        ];
    }
}
