<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Container;
use App\Repositories\Contracts\TimeSlotRepositoryInterface;
use App\Services\Contracts\TimeSlotServiceInterface;
use Override;

/**
 * Servicio de consulta de slots de tiempo disponibles.
 *
 * Proporciona disponibilidad horaria desde la tabla `time_slots`
 * para el endpoint público de reservas.
 */
final class TimeSlotService implements TimeSlotServiceInterface
{
    private TimeSlotRepositoryInterface $timeSlotRepo;

    public function __construct(?TimeSlotRepositoryInterface $timeSlotRepo = null)
    {
        $this->timeSlotRepo = $timeSlotRepo ?? Container::make(TimeSlotRepositoryInterface::class);
    }

    /**
     * Retorna los slots de tiempo disponibles para una fecha dada.
     *
     * @param string   $date    Fecha en formato YYYY-MM-DD
     * @param int|null $cafeId  Filtrar por café (opcional)
     * @param int|null $guests  Filtrar por plazas mínimas necesarias (opcional)
     * @return array<int, array<string, mixed>>
     */
    #[Override]
    public function getAvailableSlots(string $date, ?int $cafeId = null, ?int $guests = null): array
    {
        return $this->timeSlotRepo->findAvailableByDateFiltered($date, $cafeId, $guests);
    }
}
