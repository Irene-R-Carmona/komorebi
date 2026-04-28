<?php

declare(strict_types=1);

namespace App\Domain\DTO;

use Override;

final readonly class AuthAuditLogDTO implements DomainTransferObject
{
    public function __construct(
        public int $id,
        public ?int $user_id,
        public string $event_type,
        public bool $success,
        public ?string $reason,
        public ?string $ip_address,
        public ?string $user_agent,
        public ?string $device_name,
        public string $created_at,
    ) {}

    #[Override]
    public function toViewArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'event_type' => $this->event_type,
            'success' => $this->success,
            'reason' => $this->reason,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'device_name' => $this->device_name,
            'created_at' => $this->created_at,
        ];
    }
}
