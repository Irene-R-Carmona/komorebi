<?php

declare(strict_types=1);

namespace App\Domain\DTO;

use Override;

final readonly class AuditLogDTO implements DomainTransferObject
{
    public function __construct(
        public int $id,
        public ?int $user_id,
        public string $action,
        public ?string $resource_type,
        public ?int $resource_id,
        public ?string $old_values,
        public ?string $new_values,
        public ?string $ip_address,
        public ?string $user_agent,
        public string $created_at,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) ($data['id'] ?? 0),
            user_id: isset($data['user_id']) ? (int) $data['user_id'] : null,
            action: (string) ($data['action'] ?? ''),
            resource_type: isset($data['resource_type']) ? (string) $data['resource_type'] : null,
            resource_id: isset($data['resource_id']) ? (int) $data['resource_id'] : null,
            old_values: isset($data['old_values']) ? (string) $data['old_values'] : null,
            new_values: isset($data['new_values']) ? (string) $data['new_values'] : null,
            ip_address: isset($data['ip_address']) ? (string) $data['ip_address'] : null,
            user_agent: isset($data['user_agent']) ? (string) $data['user_agent'] : null,
            created_at: (string) ($data['created_at'] ?? ''),
        );
    }

    #[Override]
    public function toViewArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'action' => $this->action,
            'resource_type' => $this->resource_type,
            'resource_id' => $this->resource_id,
            'old_values' => $this->old_values,
            'new_values' => $this->new_values,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'created_at' => $this->created_at,
        ];
    }
}
