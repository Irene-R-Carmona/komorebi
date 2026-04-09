<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Repositories\RepositoryInterface;

/**
 * Interfaz del repositorio de asignaciones de supervisores.
 *
 * Define operaciones de acceso a datos específicas de supervisor_assignments.
 */
interface SupervisorAssignmentRepositoryInterface extends RepositoryInterface
{
    /**
     * Obtener todas las asignaciones activas de un supervisor.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findBySupervisor(int $supervisorId): array;

    /**
     * Obtener todas las asignaciones activas de un café.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findActiveByCafe(int $cafeId): array;

    /**
     * Crear una nueva asignación de supervisor.
     *
     * @param array<string, mixed> $data
     * @return int ID de la asignación creada
     */
    public function createAssignment(array $data): int;

    /**
     * Desactivar (soft-disable) una asignación existente.
     */
    public function deactivate(int $id): bool;
}
