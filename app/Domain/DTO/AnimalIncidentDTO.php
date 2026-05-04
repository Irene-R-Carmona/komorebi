<?php

declare(strict_types=1);

namespace App\Domain\DTO;

use Override;

final readonly class AnimalIncidentDTO implements DomainTransferObject
{
    public function __construct(
        public int $id,
        public int $animal_id,
        public string $incident_type,
        public string $description,
        public string $severity,
        public ?int $reported_by,
        public ?string $resolved_at,
        public ?int $resolved_by,
        public string $created_at,
        public string $status,
        public ?string $animal_name = null,
        public ?string $species = null,
        public ?string $resolution = null,
    ) {
    }

    #[Override]
    public function toViewArray(): array
    {
        return [
            'id' => $this->id,
            'animal_id' => $this->animal_id,
            'incident_type' => $this->incident_type,
            'description' => $this->description,
            'severity' => $this->severity,
            'reported_by' => $this->reported_by,
            'resolved_at' => $this->resolved_at,
            'resolved_by' => $this->resolved_by,
            'created_at' => $this->created_at,
            'status' => $this->status,
            'animal_name' => $this->animal_name,
            'species' => $this->species,
            'resolution' => $this->resolution,
        ];
    }
}
