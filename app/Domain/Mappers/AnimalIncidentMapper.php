<?php

declare(strict_types=1);

namespace App\Domain\Mappers;

use App\Domain\DTO\AnimalIncidentDTO;
use Override;

final readonly class AnimalIncidentMapper implements MapperInterface
{
    #[Override]
    public function toDTO(array $row): AnimalIncidentDTO
    {
        return new AnimalIncidentDTO(
            id: (int) $row['id'],
            animal_id: (int) $row['animal_id'],
            incident_type: (string) $row['incident_type'],
            description: (string) $row['description'],
            severity: (string) $row['severity'],
            reported_by: isset($row['reported_by']) ? (int) $row['reported_by'] : null,
            resolved_at: isset($row['resolved_at']) ? (string) $row['resolved_at'] : null,
            resolved_by: isset($row['resolved_by']) ? (int) $row['resolved_by'] : null,
            created_at: (string) $row['created_at'],
            status: (string) ($row['status'] ?? 'open'),
            animal_name: isset($row['animal_name']) ? (string) $row['animal_name'] : null,
            species: isset($row['species']) ? (string) $row['species'] : null,
            resolution: isset($row['resolution']) ? (string) $row['resolution'] : null,
        );
    }
}
