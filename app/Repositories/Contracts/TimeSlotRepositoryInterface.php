<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

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
     * @return array|null Array con datos del slot o null si no existe
     *                    Campos: id, cafe_id, slot_date, slot_time, total_capacity,
     *                            available_spots, is_blocked, etc.
     */
    public function findById(int $id): ?array;

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
}
