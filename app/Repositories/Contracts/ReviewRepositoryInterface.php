<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

/**
 * Contrato para ReviewRepository
 *
 * Maneja operaciones CRUD de reseñas de cafés.
 */
interface ReviewRepositoryInterface
{
    /**
     * Buscar reseña por ID
     *
     * @param int $id
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array;

    /**
     * Obtener reseñas de un usuario
     *
     * @param int $userId
     * @return array<int, array<string, mixed>>
     */
    public function findByUserId(int $userId): array;

    /**
     * Obtener reseñas de un café
     *
     * @param int $cafeId
     * @param string $status Status filter ('approved', 'pending', 'rejected')
     * @return array<int, array<string, mixed>>
     */
    public function findByCafeId(int $cafeId, string $status = 'approved'): array;

    /**
     * Obtener reseñas aprobadas con paginación
     *
     * @param int $cafeId
     * @param int $page
     * @param int $perPage
     * @return array<int, array<string, mixed>>
     */
    public function findApprovedPaginated(int $cafeId, int $page = 1, int $perPage = 10): array;

    /**
     * Obtener reseñas pendientes de moderación
     *
     * @param int $page
     * @param int $perPage
     * @return array<int, array<string, mixed>>
     */
    public function findPendingPaginated(int $page = 1, int $perPage = 20): array;

    /**
     * Crear nueva reseña
     *
     * @param array<string, mixed> $data
     * @return int ID de la reseña creada
     */
    public function create(array $data): int;

    /**
     * Actualizar reseña
     *
     * @param int $id
     * @param array<string, mixed> $data
     * @return bool
     */
    public function update(int $id, array $data): bool;

    /**
     * Actualizar estado de reseña
     *
     * @param int $id
     * @param string $status
     * @return bool
     */
    public function updateStatus(int $id, string $status): bool;

    /**
     * Soft delete de reseña
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool;

    /**
     * Calcular rating promedio de un café
     *
     * @param int $cafeId
     * @return float
     */
    public function calculateAverageRating(int $cafeId): float;

    /**
     * Verificar si usuario ya tiene reseña en un café
     *
     * @param int $userId
     * @param int $cafeId
     * @return bool
     */
    public function userHasReview(int $userId, int $cafeId): bool;

    /**
     * Obtener estadísticas de rating de un café
     *
     * @param int $cafeId
     * @return array<string, mixed>
     */
    public function getRatingStats(int $cafeId): array;
}
