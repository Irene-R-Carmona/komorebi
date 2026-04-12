<?php

declare(strict_types=1);

namespace App\Services\Contracts;

/**
 * Contrato para la gestión de sesiones activas e historial de autenticación.
 */
interface SessionManagementServiceInterface
{
    /**
     * Obtener todas las sesiones activas del usuario.
     *
     * @return array<int,array<string,mixed>>
     */
    public function getActiveSessions(int $userId): array;

    /**
     * Revocar una sesión específica verificando que pertenece al usuario.
     *
     * Incluye comprobación de propiedad: la sesión debe pertenecer a $userId.
     */
    public function revokeSessionForUser(int $userId, int $sessionRecordId, string $reason = 'user_requested'): bool;

    /**
     * Revocar todas las demás sesiones del usuario (excepto la actual).
     *
     * @return int Número de sesiones revocadas
     */
    public function revokeAllOtherSessions(int $userId, string $currentSessionId, int $revokedBy): int;

    /**
     * Obtener historial de eventos de autenticación del usuario.
     *
     * @return array<int,array<string,mixed>>
     */
    public function getAuthHistory(int $userId, int $limit = 20): array;
}
