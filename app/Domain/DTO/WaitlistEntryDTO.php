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
    ) {}

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
