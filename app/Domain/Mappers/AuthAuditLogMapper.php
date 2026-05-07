<?php

declare(strict_types=1);

namespace App\Domain\Mappers;

use App\Domain\DTO\AuthAuditLogDTO;
use Override;

final readonly class AuthAuditLogMapper implements MapperInterface
{
    #[Override]
    public function toDTO(array $row): AuthAuditLogDTO
    {
        return new AuthAuditLogDTO(
            id: (int) $row['id'],
            user_id: isset($row['user_id']) ? (int) $row['user_id'] : null,
            event_type: (string) $row['event_type'],
            success: (bool) ($row['success'] ?? true),
            reason: isset($row['reason']) ? (string) $row['reason'] : null,
            ip_address: isset($row['ip_address']) ? (string) $row['ip_address'] : null,
            user_agent: isset($row['user_agent']) ? (string) $row['user_agent'] : null,
            device_name: isset($row['device_name']) ? (string) $row['device_name'] : null,
            created_at: (string) $row['created_at'],
        );
    }
}
