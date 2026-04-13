<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Repositories\Contracts\SupervisorAssignmentRepositoryInterface;
use PDO;

/**
 * Repositorio para la tabla supervisor_assignments.
 *
 * Gestiona las asignaciones de mesas a reservas realizadas por supervisores.
 */
final class SupervisorAssignmentRepository extends AbstractRepository implements SupervisorAssignmentRepositoryInterface
{
    #[\Override]
    protected function getTable(): string
    {
        return 'supervisor_assignments';
    }

    #[\Override]
    protected function getSelectFields(): array
    {
        return [
            'id',
            'supervisor_id',
            'reservation_id',
            'table_code',
            'cafe_id',
            'is_active',
            'assigned_at',
            'created_at',
        ];
    }

    /**
     * Busca todas las asignaciones de un supervisor (activas e inactivas).
     *
     * @return array<int, array<string, mixed>>
     */
    public function findBySupervisor(int $supervisorId): array
    {
        $fields = implode(', ', $this->getSelectFields());
        $stmt = $this->getDb()->prepare(
            "SELECT {$fields} FROM supervisor_assignments
             WHERE supervisor_id = :supervisor_id
             ORDER BY assigned_at DESC"
        );
        $stmt->execute(['supervisor_id' => $supervisorId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca las asignaciones activas de un café.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findActiveByCafe(int $cafeId): array
    {
        $fields = implode(', ', $this->getSelectFields());
        $stmt = $this->getDb()->prepare(
            "SELECT {$fields} FROM supervisor_assignments
             WHERE cafe_id = :cafe_id AND is_active = 1
             ORDER BY assigned_at DESC"
        );
        $stmt->execute(['cafe_id' => $cafeId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Persiste una nueva asignación y retorna el ID generado.
     *
     * @param array<string, mixed> $data
     */
    public function createAssignment(array $data): int
    {
        return $this->create($data);
    }

    /**
     * Desactiva lógicamente una asignación (is_active = 0).
     */
    public function deactivate(int $id): bool
    {
        $stmt = $this->getDb()->prepare(
            'UPDATE supervisor_assignments SET is_active = 0 WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }
}
