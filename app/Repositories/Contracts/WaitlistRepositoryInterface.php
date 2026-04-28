<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Domain\DTO\WaitlistEntryDTO;

/**
 * Contrato para WaitlistRepository
 *
 * Maneja operaciones de lista de espera para reservas.
 */
interface WaitlistRepositoryInterface
{
    /**
     * Buscar entrada de waitlist por ID
     *
     * @param int $id
     * @return WaitlistEntryDTO|null
     */
    public function findById(int $id): ?WaitlistEntryDTO;

    /**
     * Buscar entrada por token de confirmación
     *
     * @param string $token
     * @return WaitlistEntryDTO|null
     */
    public function findByToken(string $token): ?WaitlistEntryDTO;

    /**
     * Obtener waitlists activas de un usuario
     *
     * @param int $userId
     * @return array<int, array<string, mixed>>
     */
    public function findActiveByUserId(int $userId): array;

    /**
     * Obtener posición en la lista de espera
     *
     * @param int $timeSlotId
     * @param int $userId
     * @return int|null Posición (1-based) o null si no está en lista
     */
    public function getPosition(int $timeSlotId, int $userId): ?int;

    /**
     * Obtener siguiente persona en la lista de espera
     *
     * @param int $timeSlotId
     * @return array<string, mixed>|null
     */
    public function getNextInLine(int $timeSlotId): ?array;

    /**
     * Crear nueva entrada en waitlist
     *
     * @param array<string, mixed> $data
     * @return int ID de la entrada creada
     */
    public function create(array $data): int;

    /**
     * Actualizar estado de waitlist
     *
     * @param int $id
     * @param string $status
     * @return bool
     */
    public function updateStatus(int $id, string $status): bool;

    /**
     * Actualizar estado de waitlist con datos adicionales
     *
     * @param int $id
     * @param string $status
     * @param array<string, mixed> $additionalData
     * @return bool
     */
    public function updateStatusWithData(int $id, string $status, array $additionalData = []): bool;

    /**
     * Actualizar token y expiración
     *
     * @param int $id
     * @param string $token
     * @param string $expiresAt
     * @return bool
     */
    public function updateToken(int $id, string $token, string $expiresAt): bool;

    /**
     * Reordenar posiciones después de promoción/cancelación
     *
     * @param int $timeSlotId
     * @param int $fromPosition
     * @return bool
     */
    public function reorderPositions(int $timeSlotId, int $fromPosition): bool;

    /**
     * Cancelar waitlist
     *
     * @param int $id
     * @param int $userId
     * @return bool
     */
    public function cancel(int $id, int $userId): bool;

    /**
     * Expirar tokens vencidos
     *
     * @return int Cantidad de tokens expirados
     */
    public function expireTokens(): int;

    /**
     * Obtener historial de waitlists de un usuario
     *
     * @param int $userId
     * @param int $limit
     * @return array<int, array<string, mixed>>
     */
    public function getUserHistory(int $userId, int $limit = 10): array;

    /**
     * Verificar si usuario ya está en waitlist de un time slot
     *
     * @param int $userId
     * @param int $timeSlotId
     * @return bool
     */
    public function userInWaitlist(int $userId, int $timeSlotId): bool;

    /**
     * Obtener waitlists con detalle de slot, café y usuario.
     *
     * @param array<string, mixed> $filters  Claves: cafe_id, status, date
     * @return array<int, array<string, mixed>>
     */
    public function getAllWithDetails(array $filters = []): array;

    /**
     * Resumen de entradas agrupadas por estado.
     *
     * @return array<string, int>  status => count
     */
    public function getSummaryByStatus(): array;

    /**
     * Cancelar una entrada de waitlist por ID (acción admin).
     */
    public function cancelById(int $id): bool;

    /**
     * Buscar una entrada de waitlist por ID y usuario (validación de ownership).
     *
     * @return array<string, mixed>|null
     */
    public function findByIdAndUser(int $id, int $userId): ?array;

    /**
     * Contar entradas de waitlist de un time slot por estado.
     */
    public function countByTimeSlotAndStatus(int $timeSlotId, string $status): int;
}
