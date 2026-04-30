<?php

declare(strict_types=1);

namespace App\Domain\DTO;

use Override;

final readonly class TrackerDTO implements DomainTransferObject
{
    public function __construct(
        public int $id,
        public int $cafe_id,
        public string $code,
        public string $type,
        public string $status,
        public ?string $last_assigned_at,
        public ?string $cafe_name,
    ) {
    }

    #[Override]
    public function toViewArray(): array
    {
        return [
            'id' => $this->id,
            'cafe_id' => $this->cafe_id,
            'code' => $this->code,
            'type' => $this->type,
            'status' => $this->status,
            'last_assigned_at' => $this->last_assigned_at,
            'cafe_name' => $this->cafe_name,
        ];
    }
}
