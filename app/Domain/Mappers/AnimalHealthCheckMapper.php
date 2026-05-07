<?php

declare(strict_types=1);

namespace App\Domain\Mappers;

use App\Domain\DTO\AnimalHealthCheckDTO;
use Override;

final readonly class AnimalHealthCheckMapper implements MapperInterface
{
    #[Override]
    public function toDTO(array $row): AnimalHealthCheckDTO
    {
        return new AnimalHealthCheckDTO(
            id: (int) $row['id'],
            animal_id: (int) $row['animal_id'],
            checked_by: (int) $row['checked_by'],
            check_date: (string) $row['check_date'],
            created_at: (string) $row['created_at'],
            weight_kg: isset($row['weight_kg']) ? (float) $row['weight_kg'] : null,
            temperature_c: isset($row['temperature_c']) ? (float) $row['temperature_c'] : null,
            appetite: (string) $row['appetite'],
            energy_level: (string) $row['energy_level'],
            coat_condition: (string) $row['coat_condition'],
            eyes_clear: (bool) $row['eyes_clear'],
            breathing_normal: (bool) $row['breathing_normal'],
            mobility_normal: (bool) $row['mobility_normal'],
            notes: isset($row['notes']) ? (string) $row['notes'] : null,
            alerts: isset($row['alerts']) ? (string) $row['alerts'] : null,
            animal_name: isset($row['animal_name']) ? (string) $row['animal_name'] : null,
            species_type: isset($row['species_type']) ? (string) $row['species_type'] : null,
            current_status: isset($row['current_status']) ? (string) $row['current_status'] : null,
            keeper_name: isset($row['keeper_name']) ? (string) $row['keeper_name'] : null,
        );
    }
}
