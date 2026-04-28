<?php

declare(strict_types=1);

namespace App\Domain\Mappers;

use App\Domain\DTO\SupervisorAssignmentDTO;
use Override;

final readonly class SupervisorAssignmentMapper implements MapperInterface
{
    #[Override]
    public function toDTO(array $row): SupervisorAssignmentDTO
    {
        return new SupervisorAssignmentDTO(
            id: (int) $row['id'],
            supervisor_id: (int) $row['supervisor_id'],
            reservation_id: (int) $row['reservation_id'],
            table_code: (string) $row['table_code'],
            cafe_id: (int) $row['cafe_id'],
            is_active: (bool) $row['is_active'],
            assigned_at: (string) $row['assigned_at'],
            created_at: (string) $row['created_at'],
        );
    }
}
