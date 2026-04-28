<?php

declare(strict_types=1);

namespace App\Domain\Mappers;

use App\Domain\DTO\StaffShiftDTO;
use Override;

final readonly class StaffShiftMapper implements MapperInterface
{
    #[Override]
    public function toDTO(array $row): StaffShiftDTO
    {
        return new StaffShiftDTO(
            id: (int) $row['id'],
            user_id: (int) $row['user_id'],
            cafe_id: (int) $row['cafe_id'],
            shift_date: (string) $row['shift_date'],
            shift_start: (string) $row['shift_start'],
            shift_end: (string) $row['shift_end'],
            notes: isset($row['notes']) ? (string) $row['notes'] : null,
            created_by: isset($row['created_by']) ? (int) $row['created_by'] : null,
            created_at: (string) $row['created_at'],
            updated_at: (string) $row['updated_at'],
            deleted_at: isset($row['deleted_at']) ? (string) $row['deleted_at'] : null,
            staff_name: isset($row['staff_name']) ? (string) $row['staff_name'] : null,
        );
    }
}
