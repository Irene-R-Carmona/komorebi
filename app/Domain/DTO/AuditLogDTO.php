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
    ) {}

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
