<?php

declare(strict_types=1);

namespace App\Services\Contracts;

interface ClimaContextoServiceInterface
{
    public function obtenerClimaActual(): array;

    public function obtenerConfiguracionEfectos(string $condicion): array;
}
