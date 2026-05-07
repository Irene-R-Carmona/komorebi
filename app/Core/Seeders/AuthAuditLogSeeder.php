<?php

declare(strict_types=1);

namespace App\Core\Seeders;

use App\Core\Database;
use App\Core\Logger;
use PDO;
use PDOStatement;

/**
 * AuthAuditLogSeeder
 *
 * Genera ~500 registros de auth_audit_logs distribuidos en 90 días.
 * Incluye actividad normal, intentos fallidos y patrones sospechosos
 * para que los widgets de análisis del backoffice tengan datos reales.
 */
final class AuthAuditLogSeeder
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function run(): void
    {
        Logger::info('[AuthAuditLogSeeder] starting');

        // Limpiar datos previos de seeder
        $existing = (int) $this->db->query('SELECT COUNT(*) FROM auth_audit_logs')->fetchColumn();
        if ($existing > 0) {
            $this->db->exec('DELETE FROM auth_audit_logs');
            Logger::debug('[AuthAuditLogSeeder] cleared existing records', ['deleted' => $existing]);
        }

        // Obtener user_ids existentes
        $stmt = $this->db->query('SELECT id FROM users ORDER BY id LIMIT 10');
        $userIds = \array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id');

        if (\count($userIds) === 0) {
            Logger::warning('[AuthAuditLogSeeder] no users found, skipping');

            return;
        }

        $userAgents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/124.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 Safari/605.1.15',
            'Mozilla/5.0 (X11; Linux x86_64) Firefox/125.0',
            'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Mobile/15E148',
            'Mozilla/5.0 (Android 13; Mobile) Firefox/124.0',
            'curl/8.6.0',
        ];

        $deviceNames = [
            'Chrome en Windows',
            'Safari en macOS',
            'Firefox en Linux',
            'Safari en iPhone',
            'Firefox en Android',
            null,
        ];

        // IPs normales (la mayoría del tráfico legítimo)
        $normalIps = [
            '192.168.1.10', '192.168.1.11', '192.168.1.15',
            '10.0.0.5', '10.0.0.8', '10.0.0.12',
        ];

        // IPs sospechosas (pocas, pero repetidas muchas veces para simular fuerza bruta)
        $suspiciousIps = ['85.43.21.99', '203.0.113.42', '198.51.100.7'];

        $insertStmt = $this->db->prepare(
            'INSERT INTO auth_audit_logs
             (user_id, event_type, ip_address, user_agent, device_name, success, reason, created_at)
             VALUES
             (:user_id, :event_type, :ip, :ua, :device, :success, :reason,
              NOW() - INTERVAL :days DAY - INTERVAL :hours HOUR - INTERVAL :mins MINUTE)'
        );

        $inserted = 0;

        // --- Bloque 1: Logins normales exitosos (~200 registros) ---
        for ($i = 0; $i < 200; $i++) {
            $userId = $userIds[\array_rand($userIds)];
            $uaIdx = \rand(0, 4); // excluir curl
            $this->insert($insertStmt, [
                'user_id' => $userId,
                'event_type' => 'login',
                'ip' => $normalIps[\array_rand($normalIps)],
                'ua' => $userAgents[$uaIdx],
                'device' => $deviceNames[$uaIdx],
                'success' => 1,
                'reason' => null,
                'days' => $this->weightedDays(90),
                'hours' => \rand(7, 22), // horario de uso típico
                'mins' => \rand(0, 59),
            ]);
            $inserted++;
        }

        // --- Bloque 2: Logouts (~120 registros) ---
        for ($i = 0; $i < 120; $i++) {
            $userId = $userIds[\array_rand($userIds)];
            $uaIdx = \rand(0, 4);
            $this->insert($insertStmt, [
                'user_id' => $userId,
                'event_type' => 'logout',
                'ip' => $normalIps[\array_rand($normalIps)],
                'ua' => $userAgents[$uaIdx],
                'device' => $deviceNames[$uaIdx],
                'success' => 1,
                'reason' => null,
                'days' => $this->weightedDays(90),
                'hours' => \rand(8, 23),
                'mins' => \rand(0, 59),
            ]);
            $inserted++;
        }

        // --- Bloque 3: Intentos fallidos de login (~100 registros) ---
        // Mezcla de IPs normales y sospechosas para simular patrones reales
        $failReasons = [
            'Contraseña incorrecta',
            'Usuario no encontrado',
            'Cuenta no verificada',
            'Demasiados intentos fallidos',
        ];
        for ($i = 0; $i < 100; $i++) {
            // 60% desde IPs sospechosas, 40% normales
            $ip = \rand(1, 100) <= 60
                ? $suspiciousIps[\array_rand($suspiciousIps)]
                : $normalIps[\array_rand($normalIps)];

            // Algunos con user_id nulo (usuario desconocido)
            $userId = \rand(1, 100) <= 40 ? $userIds[\array_rand($userIds)] : null;

            $this->insert($insertStmt, [
                'user_id' => $userId,
                'event_type' => 'failed_login',
                'ip' => $ip,
                'ua' => $userAgents[\array_rand($userAgents)],
                'device' => null,
                'success' => 0,
                'reason' => $failReasons[\array_rand($failReasons)],
                'days' => $this->weightedDays(90),
                'hours' => \rand(0, 23),
                'mins' => \rand(0, 59),
            ]);
            $inserted++;
        }

        // --- Bloque 4: Password resets (~25 registros) ---
        for ($i = 0; $i < 25; $i++) {
            $userId = $userIds[\array_rand($userIds)];
            $success = \rand(0, 1);
            $this->insert($insertStmt, [
                'user_id' => $userId,
                'event_type' => 'password_reset',
                'ip' => $normalIps[\array_rand($normalIps)],
                'ua' => $userAgents[\rand(0, 4)],
                'device' => null,
                'success' => $success,
                'reason' => $success ? null : 'Token expirado',
                'days' => \rand(0, 90),
                'hours' => \rand(0, 23),
                'mins' => \rand(0, 59),
            ]);
            $inserted++;
        }

        // --- Bloque 5: Email verified (~20 registros) ---
        for ($i = 0; $i < 20; $i++) {
            $userId = $userIds[\array_rand($userIds)];
            $this->insert($insertStmt, [
                'user_id' => $userId,
                'event_type' => 'email_verified',
                'ip' => $normalIps[\array_rand($normalIps)],
                'ua' => $userAgents[\rand(0, 4)],
                'device' => null,
                'success' => 1,
                'reason' => null,
                'days' => \rand(30, 90),  // verificaciones más antiguas
                'hours' => \rand(0, 23),
                'mins' => \rand(0, 59),
            ]);
            $inserted++;
        }

        // --- Bloque 6: Session revoked (~15 registros) ---
        for ($i = 0; $i < 15; $i++) {
            $userId = $userIds[\array_rand($userIds)];
            $this->insert($insertStmt, [
                'user_id' => $userId,
                'event_type' => 'session_revoked',
                'ip' => $normalIps[\array_rand($normalIps)],
                'ua' => $userAgents[\rand(0, 4)],
                'device' => null,
                'success' => 1,
                'reason' => ['Cierre de sesión forzado', 'Sesión caducada', 'Administrador revocó acceso'][\rand(0, 2)],
                'days' => \rand(0, 60),
                'hours' => \rand(0, 23),
                'mins' => \rand(0, 59),
            ]);
            $inserted++;
        }

        // --- Bloque 7: Account lockout (~20 registros) ---
        // Siempre desde IPs sospechosas — clave para el widget "actividad sospechosa"
        for ($i = 0; $i < 20; $i++) {
            $userId = $userIds[\array_rand($userIds)];
            $this->insert($insertStmt, [
                'user_id' => $userId,
                'event_type' => 'lockout',
                'ip' => $suspiciousIps[\array_rand($suspiciousIps)],
                'ua' => $userAgents[\array_rand($userAgents)],
                'device' => null,
                'success' => 0,
                'reason' => 'Cuenta bloqueada por exceso de intentos fallidos',
                'days' => $this->weightedDays(30), // más recientes
                'hours' => \rand(0, 23),
                'mins' => \rand(0, 59),
            ]);
            $inserted++;
        }

        Logger::info('[AuthAuditLogSeeder] done', ['inserted' => $inserted]);
    }

    /**
     * Ejecuta el prepared statement con los parámetros dados.
     *
     * @param array<string, mixed> $params
     */
    private function insert(PDOStatement $stmt, array $params): void
    {
        $stmt->execute($params);
    }

    /**
     * Devuelve un número de días con distribución no uniforme:
     * más probabilidad de días recientes.
     */
    private function weightedDays(int $max): int
    {
        $r = \rand(1, 100);

        if ($r <= 40) {
            return \rand(0, 14);
        }

        if ($r <= 70) {
            return \rand(15, 30);
        }

        return \rand(31, $max);
    }
}
