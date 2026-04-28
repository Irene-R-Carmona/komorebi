<?php

declare(strict_types=1);

namespace App\Domain\DTO;

use Override;

final readonly class AnimalHealthCheckDTO implements DomainTransferObject
{
    public function __construct(
        public int $id,
        public int $animal_id,
        public int $checked_by,
        public string $check_date,
        public string $created_at,
        public ?float $weight_kg,
        public ?float $temperature_c,
        public string $appetite,
        public string $energy_level,
        public string $coat_condition,
        public bool $eyes_clear,
        public bool $breathing_normal,
        public bool $mobility_normal,
        public ?string $notes,
        public ?string $alerts,
        public ?string $animal_name = null,
        public ?string $species_type = null,
        public ?string $current_status = null,
        public ?string $keeper_name = null,
    ) {}

    #[Override]
    public function toViewArray(): array
    {
        return [
            'id'               => $this->id,
            'animal_id'        => $this->animal_id,
            'checked_by'       => $this->checked_by,
            'check_date'       => $this->check_date,
            'created_at'       => $this->created_at,
            'weight_kg'        => $this->weight_kg,
            'temperature_c'    => $this->temperature_c,
            'appetite'         => $this->appetite,
            'energy_level'     => $this->energy_level,
            'coat_condition'   => $this->coat_condition,
            'eyes_clear'       => $this->eyes_clear,
            'breathing_normal' => $this->breathing_normal,
            'mobility_normal'  => $this->mobility_normal,
            'notes'            => $this->notes,
            'alerts'           => $this->alerts,
            'animal_name'      => $this->animal_name,
            'species_type'     => $this->species_type,
            'current_status'   => $this->current_status,
            'keeper_name'      => $this->keeper_name,
        ];
    }
}
