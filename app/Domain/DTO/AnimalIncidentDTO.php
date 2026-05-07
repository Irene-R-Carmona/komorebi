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

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) ($data['id'] ?? 0),
            animal_id: (int) ($data['animal_id'] ?? 0),
            incident_type: (string) ($data['incident_type'] ?? ''),
            description: (string) ($data['description'] ?? ''),
            severity: (string) ($data['severity'] ?? ''),
            reported_by: isset($data['reported_by']) ? (int) $data['reported_by'] : null,
            resolved_at: isset($data['resolved_at']) ? (string) $data['resolved_at'] : null,
            resolved_by: isset($data['resolved_by']) ? (int) $data['resolved_by'] : null,
            created_at: (string) ($data['created_at'] ?? ''),
            status: (string) ($data['status'] ?? ''),
            animal_name: isset($data['animal_name']) ? (string) $data['animal_name'] : null,
            species: isset($data['species']) ? (string) $data['species'] : null,
            resolution: isset($data['resolution']) ? (string) $data['resolution'] : null,
        );
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
