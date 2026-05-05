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
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) ($data['id'] ?? 0),
            user_id: isset($data['user_id']) ? (int) $data['user_id'] : null,
            event_type: (string) ($data['event_type'] ?? ''),
            success: (bool) ($data['success'] ?? false),
            reason: isset($data['reason']) ? (string) $data['reason'] : null,
            ip_address: isset($data['ip_address']) ? (string) $data['ip_address'] : null,
            user_agent: isset($data['user_agent']) ? (string) $data['user_agent'] : null,
            device_name: isset($data['device_name']) ? (string) $data['device_name'] : null,
            created_at: (string) ($data['created_at'] ?? ''),
        );
    }

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
