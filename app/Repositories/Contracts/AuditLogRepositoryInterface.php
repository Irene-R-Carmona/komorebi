<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Domain\DTO\AuditLogDTO;

interface AuditLogRepositoryInterface
{
    public function findById(int $id): ?AuditLogDTO;

    /**
     * Logs paginados con filtros opcionales.
     *
     * @param  array{user_id?: int, action?: string, resource_type?: string,
     *                date_from?: string, date_to?: string, ip_address?: string} $filters
     * @return array{data: array<int, array<string, mixed>>, total: int}
     */
    public function findFiltered(array $filters = [], int $limit = 50, int $offset = 0): array;

    /**
     * Estadísticas de auditoría (totals, top_actions, top_resources).
     *
     * @param  array{date_from?: string, date_to?: string} $filters
     * @return array<string, mixed>
     */
    public function getStats(array $filters = []): array;

    /**
     * Historial de cambios de un recurso específico.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getResourceHistory(string $resourceType, int $resourceId): array;

    /** Elimina logs más antiguos que $daysToKeep días. Devuelve filas eliminadas. */
    public function cleanup(int $daysToKeep = 365): int;

    /** Registra una entrada de auditoría. Devuelve el ID insertado. */
    public function log(
        string $action,
        ?string $resourceType = null,
        ?int $resourceId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?int $userId = null
    ): int;
}
