<?php

declare(strict_types=1);

namespace App\Domain\Mappers;

use App\Domain\DTO\AuditLogDTO;
use Override;

final readonly class AuditLogMapper implements MapperInterface
{
    #[Override]
    public function toDTO(array $row): AuditLogDTO
    {
        return new AuditLogDTO(
            id: (int) $row['id'],
            user_id: isset($row['user_id']) ? (int) $row['user_id'] : null,
            action: (string) $row['action'],
            resource_type: isset($row['resource_type']) ? (string) $row['resource_type'] : null,
            resource_id: isset($row['resource_id']) ? (int) $row['resource_id'] : null,
            old_values: isset($row['old_values']) ? (string) $row['old_values'] : null,
            new_values: isset($row['new_values']) ? (string) $row['new_values'] : null,
            ip_address: isset($row['ip_address']) ? (string) $row['ip_address'] : null,
            user_agent: isset($row['user_agent']) ? (string) $row['user_agent'] : null,
            created_at: (string) $row['created_at'],
        );
    }
}
