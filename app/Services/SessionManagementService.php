<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Services\Contracts\SessionManagementServiceInterface;
use PDO;

/**
 * Servicio de Gestión de Sesiones
 */
final class SessionManagementService implements SessionManagementServiceInterface
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    /**
     * Crear una nueva sesión activa
     *
     * @param integer     $userId
     * @param string      $sessionId
     * @param string      $ipAddress
     * @param string|null $userAgent
     * @param string|null $deviceName
     * @param integer     $expiresInSeconds TTL de la sesión (default 86400 = 24h)
     *
     * @return boolean
     */
    #[\Override]
    public function createSession(
        int $userId,
        string $sessionId,
        string $ipAddress,
        ?string $userAgent = null,
        ?string $deviceName = null,
        int $expiresInSeconds = 86400
    ): bool {
        $now = \date('Y-m-d H:i:s');
        $expiresAt = \date('Y-m-d H:i:s', \time() + $expiresInSeconds);

        // Usar ON DUPLICATE KEY UPDATE para evitar fatal por session_id duplicado.
        // Si ya existe, actualizamos last_activity y expires_at.
        $stmt = $this->db->prepare(
            'INSERT INTO active_sessions
            (user_id, session_id, ip_address, user_agent, device_name, last_activity, created_at, expires_at)
            VALUES (:user_id, :session_id, :ip, :ua, :device, :last, :created, :expires)
            ON DUPLICATE KEY UPDATE
                last_activity = VALUES(last_activity),
                ip_address = VALUES(ip_address),
                user_agent = VALUES(user_agent),
                device_name = VALUES(device_name),
                expires_at = VALUES(expires_at)'
        );

        return $stmt->execute([
            'user_id' => $userId,
            'session_id' => $sessionId,
            'ip' => $ipAddress,
            'ua' => $userAgent,
            'device' => $deviceName,
            'last' => $now,
            'created' => $now,
            'expires' => $expiresAt,
        ]);
    }

    /**
     * Obtener todas las sesiones activas de un usuario
     *
     * @param integer $userId
     *
     * @return array[]
     */
    #[\Override]
    public function getActiveSessions(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, session_id, ip_address, user_agent, device_name, last_activity, created_at, expires_at
             FROM active_sessions
             WHERE user_id = :user_id AND revoked_at IS NULL AND expires_at > NOW()
             ORDER BY last_activity DESC'
        );
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll() ?: [];
    }

    /**
     * Obtener sesión por ID
     *
     * @param integer $sessionRecordId
     *
     * @return array|null
     */
    public function getSessionById(int $sessionRecordId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, user_id, session_id, ip_address, user_agent, device_name,
                    last_activity, created_at, expires_at, revoked_at
             FROM active_sessions WHERE id = :id'
        );
        $stmt->execute(['id' => $sessionRecordId]);

        return $stmt->fetch() ?: null;
    }

    /**
     * Actualizar última actividad de una sesión
     *
     * @param string $sessionId
     *
     * @return boolean
     */
    public function updateSessionActivity(string $sessionId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE active_sessions SET last_activity = NOW()
             WHERE session_id = :session_id AND revoked_at IS NULL'
        );

        return $stmt->execute(['session_id' => $sessionId]);
    }

    /**
     * Revocar una sesión específica
     *
     * @param integer $sessionRecordId
     * @param integer $revokedBy       ID del usuario que revoca (puede ser el mismo)
     * @param string  $reason          Motivo de revocación
     *
     * @return boolean
     */
    public function revokeSession(int $sessionRecordId, int $revokedBy, string $reason = 'user_requested'): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE active_sessions
             SET revoked_at = NOW(), revoke_reason = :reason, revoked_by = :by
             WHERE id = :id'
        );

        return $stmt->execute(['reason' => $reason, 'by' => $revokedBy, 'id' => $sessionRecordId]);
    }

    /**
     * Revocar una sesión verificando que pertenece al usuario indicado.
     *
     * Incluye comprobación de propiedad: la sesión debe pertenecer a $userId.
     *
     * @param integer $userId           ID del usuario propietario
     * @param integer $sessionRecordId  ID de registro de sesión a revocar
     * @param string  $reason           Motivo de revocación
     *
     * @return boolean
     */
    #[\Override]
    public function revokeSessionForUser(int $userId, int $sessionRecordId, string $reason = 'user_requested'): bool
    {
        $session = $this->getSessionById($sessionRecordId);

        if (!$session || (int) $session['user_id'] !== $userId) {
            return false;
        }

        return $this->revokeSession($sessionRecordId, $userId, $reason);
    }

    /**
     * Revocar todas las sesiones de un usuario (excepto la actual)
     *
     * @param integer $userId
     * @param string  $currentSessionId Session ID a mantener
     * @param integer $revokedBy        ID del usuario que revoca
     *
     * @return integer Número de sesiones revocadas
     */
    #[\Override]
    public function revokeAllOtherSessions(int $userId, string $currentSessionId, int $revokedBy): int
    {
        $stmt = $this->db->prepare(
            "UPDATE active_sessions
             SET revoked_at = NOW(), revoke_reason = 'security_logout_all', revoked_by = :by
             WHERE user_id = :user_id AND session_id != :current AND revoked_at IS NULL"
        );
        $stmt->execute(['by' => $revokedBy, 'user_id' => $userId, 'current' => $currentSessionId]);

        return $stmt->rowCount();
    }

    /**
     * Limpiar sesiones expiradas (limpieza periódica)
     *
     * @return integer Número de sesiones limpiadas
     */
    public function cleanupExpiredSessions(): int
    {
        return $this->db->query('DELETE FROM active_sessions WHERE expires_at < NOW()')->rowCount();
    }

    /**
     * Registrar evento de autenticación en auditoría
     *
     * @param integer|null $userId
     * @param string       $eventType  login|logout|failed_login|password_reset|email_verified|session_revoked|lockout
     * @param string       $ipAddress
     * @param string|null  $userAgent
     * @param string|null  $deviceName
     * @param boolean      $success
     * @param string|null  $reason
     *
     * @return boolean
     */
    #[\Override]
    public function logAuthEvent(
        ?int $userId,
        string $eventType,
        string $ipAddress,
        ?string $userAgent = null,
        ?string $deviceName = null,
        bool $success = true,
        ?string $reason = null
    ): bool {
        $stmt = $this->db->prepare(
            'INSERT INTO auth_audit_logs
             (user_id, event_type, ip_address, user_agent, device_name, success, reason)
             VALUES (:user_id, :event, :ip, :ua, :device, :success, :reason)'
        );

        return $stmt->execute([
            'user_id' => $userId,
            'event' => $eventType,
            'ip' => $ipAddress,
            'ua' => $userAgent,
            'device' => $deviceName,
            'success' => $success ? 1 : 0,
            'reason' => $reason,
        ]);
    }

    /**
     * Obtener últimos eventos de autenticación de un usuario
     *
     * @param integer $userId
     * @param integer $limit
     *
     * @return array[]
     */
    #[\Override]
    public function getAuthHistory(int $userId, int $limit = 20): array
    {
        $stmt = $this->db->prepare(
            'SELECT event_type, ip_address, user_agent, device_name, success, reason, created_at
             FROM auth_audit_logs
             WHERE user_id = :user_id
             ORDER BY created_at DESC
             LIMIT :limit'
        );
        $stmt->bindValue('user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }
}
