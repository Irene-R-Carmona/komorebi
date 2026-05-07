<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use DateMalformedStringException;
use DateTimeInterface;

interface FestivosJaponesesServiceInterface
{
    /**
     * @throws DateMalformedStringException
     */
    public function obtenerFestivo(DateTimeInterface|string|null $fecha = null): ?array;

    /**
     * @throws DateMalformedStringException
     */
    public function esFestivo(DateTimeInterface|string|null $fecha = null): bool;

    /**
     * @throws DateMalformedStringException
     */
    public function obtenerFestivosDelAnio(?int $anio = null): array;

    /**
     * @throws DateMalformedStringException
     */
    public function obtenerMensajeContextual(DateTimeInterface|string|null $fecha = null): string;

    /**
     * @throws DateMalformedStringException
     */
    public function permiteReservas(DateTimeInterface|string|null $fecha = null): bool;
}
