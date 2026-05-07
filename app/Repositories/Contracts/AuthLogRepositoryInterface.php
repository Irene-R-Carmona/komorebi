<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Repositories\RepositoryInterface;

/**
 * Interfaz del repositorio de logs de autenticación.
 *
 * Define operaciones de consulta y análisis sobre auth_audit_logs.
 */
interface AuthLogRepositoryInterface extends RepositoryInterface
{
    /**
     * Obtener IPs con actividad sospechosa (múltiples fallos recientes).
     *
     * @param int $minutesBack Ventana de tiempo en minutos hacia atrás
     * @param int $threshold   Número mínimo de fallos para considerar sospechoso
     * @return array<int, array{ip_address: string, failed_attempts: int, last_attempt: string}>
     */
    public function findSuspiciousActivity(int $minutesBack = 15, int $threshold = 5): array;

    /**
     * Registrar evento de autenticación (login, logout, failed_login, etc.)
     */
    public function logEvent(
        ?int $userId,
        string $eventType,
        string $ipAddress,
        ?string $userAgent,
        ?string $deviceName,
        bool $success,
        ?string $reason
    ): bool;

    /**
     * Obtener historial de eventos de un usuario.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getHistory(int $userId, int $limit = 20): array;

    /**
     * Listar eventos con filtros y paginación.
     *
     * @param array<string, mixed> $filters
     * @return array{data: array<int, array<string, mixed>>, total: int}
     */
    public function findFiltered(array $filters = [], int $limit = 50, int $offset = 0): array;

    /**
     * Estadísticas de autenticación (totales, por tipo, top IPs).
     *
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function getStats(array $filters = []): array;
}
