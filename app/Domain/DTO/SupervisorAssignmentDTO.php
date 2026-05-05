<?php

declare(strict_types=1);

namespace App\Domain\DTO;

use Override;

final readonly class SupervisorAssignmentDTO implements DomainTransferObject
{
    public function __construct(
        public int $id,
        public int $supervisor_id,
        public int $reservation_id,
        public string $table_code,
        public int $cafe_id,
        public bool $is_active,
        public string $assigned_at,
        public string $created_at,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) ($data['id'] ?? 0),
            supervisor_id: (int) ($data['supervisor_id'] ?? 0),
            reservation_id: (int) ($data['reservation_id'] ?? 0),
            table_code: (string) ($data['table_code'] ?? ''),
            cafe_id: (int) ($data['cafe_id'] ?? 0),
            is_active: (bool) ($data['is_active'] ?? false),
            assigned_at: (string) ($data['assigned_at'] ?? ''),
            created_at: (string) ($data['created_at'] ?? ''),
        );
    }

    #[Override]
    public function toViewArray(): array
    {
        return [
            'id' => $this->id,
            'supervisor_id' => $this->supervisor_id,
            'reservation_id' => $this->reservation_id,
            'table_code' => $this->table_code,
            'cafe_id' => $this->cafe_id,
            'is_active' => $this->is_active,
            'assigned_at' => $this->assigned_at,
            'created_at' => $this->created_at,
        ];
    }
}
