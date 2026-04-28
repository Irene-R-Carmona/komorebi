<?php

declare(strict_types=1);

namespace App\Domain\DTO;

use Override;

final readonly class StaffShiftDTO implements DomainTransferObject
{
    public function __construct(
        public int $id,
        public int $user_id,
        public int $cafe_id,
        public string $shift_date,
        public string $shift_start,
        public string $shift_end,
        public ?string $notes,
        public ?int $created_by,
        public string $created_at,
        public string $updated_at,
        public ?string $deleted_at = null,
        public ?string $staff_name = null,
    ) {}

    #[Override]
    public function toViewArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'cafe_id' => $this->cafe_id,
            'shift_date' => $this->shift_date,
            'shift_start' => $this->shift_start,
            'shift_end' => $this->shift_end,
            'notes' => $this->notes,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
            'staff_name' => $this->staff_name,
        ];
    }
}
