<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Domain\DTO\TimeSlotDTO;

/**
 * Interfaz del repositorio de time slots
 *
 * Define operaciones de acceso a datos de slots de tiempo siguiendo
 * el principio de Inversión de Dependencias (SOLID).
 */
interface TimeSlotRepositoryInterface
{
    /**
     * Buscar un time slot por su ID
     *
     * @param integer $id ID del time slot
     * @return TimeSlotDTO|null DTO con datos del slot o null si no existe
     */
    public function findById(int $id): ?TimeSlotDTO;

    /**
     * Obtener la capacidad disponible de un time slot específico
     *
     * @param integer $timeSlotId ID del time slot
     * @return integer Número de plazas disponibles (0 = lleno)
     */
    public function getAvailableCapacity(int $timeSlotId): int;

    /**
     * Verificar si un time slot está lleno
     *
     * @param integer $timeSlotId ID del time slot
     * @return boolean True si no quedan plazas disponibles
     */
    public function isFull(int $timeSlotId): bool;

    /**
     * Verificar si un time slot está bloqueado
     *
     * @param integer $timeSlotId ID del time slot
     * @return boolean True si el slot está bloqueado administrativamente
     */
    public function isBlocked(int $timeSlotId): bool;

    /**
     * Reservar plazas en un time slot (operación atómica)
     *
     * @param integer $timeSlotId ID del time slot
     * @param integer $spots      Número de plazas a reservar
     * @return boolean True si se pudo reservar, false si no hay capacidad
     */
    public function reserveSpots(int $timeSlotId, int $spots): bool;

    /**
     * Liberar plazas de un time slot (operación atómica)
     *
     * @param integer $timeSlotId ID del time slot
     * @param integer $spots      Número de plazas a liberar
     * @return boolean True si se pudo liberar
     */
    public function releaseSpots(int $timeSlotId, int $spots): bool;

    /**
     * Buscar slots disponibles para un café en una fecha
     *
     * @param integer $cafeId ID del café
     * @param string  $date   Fecha (Y-m-d)
     * @return array Lista de slots disponibles con su capacidad
     */
    public function findAvailableSlots(int $cafeId, string $date): array;

    /**
     * Slots disponibles en un rango de fechas con mínimo de plazas.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findAvailableRange(int $cafeId, string $startDate, string $endDate, int $minSpots = 1): array;

    /**
     * Estadísticas de ocupación de un café en un rango de fechas.
     *
     * @return array<string, mixed>
     */
    public function getOccupancyStats(int $cafeId, string $startDate, string $endDate): array;

    /**
     * Buscar slots disponibles para una fecha con filtros opcionales.
     *
     * @param string   $date   Fecha en formato Y-m-d
     * @param int|null $cafeId Filtrar por café (opcional)
     * @param int|null $guests Filtrar por plazas mínimas disponibles (opcional)
     * @return array<int, array<string, mixed>>
     */
    public function findAvailableByDateFiltered(string $date, ?int $cafeId = null, ?int $guests = null): array;
}
