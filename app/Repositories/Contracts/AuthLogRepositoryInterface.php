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
}
