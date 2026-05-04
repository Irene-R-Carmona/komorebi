<?php

declare(strict_types=1);

namespace App\Core\Seeders;

use App\Core\Database;
use App\Core\Logger;
use PDO;

/**
 * AuditLogSeeder
 *
 * Genera ~200 registros de auditoría distribuidos en 90 días.
 * Simula actividad real de admins y managers sobre recursos del sistema.
 */
final class AuditLogSeeder
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function run(): void
    {
        Logger::info('[AuditLogSeeder] starting');

        // Limpiar datos previos de seeder
        $existing = (int) $this->db->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn();
        if ($existing > 0) {
            $this->db->exec('DELETE FROM audit_logs');
            Logger::debug('[AuditLogSeeder] cleared existing records', ['deleted' => $existing]);
        }

        // Obtener user_ids existentes
        $stmt = $this->db->query('SELECT id FROM users ORDER BY id LIMIT 10');
        $userIds = \array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id');

        if (\count($userIds) === 0) {
            Logger::warning('[AuditLogSeeder] no users found, skipping');

            return;
        }

        $actions = [
            'create' => ['user', 'cafe', 'animal', 'product', 'reservation', 'role'],
            'update' => ['user', 'cafe', 'animal', 'product', 'reservation', 'review', 'setting'],
            'delete' => ['user', 'animal', 'product', 'reservation', 'review'],
            'view' => ['user', 'cafe', 'animal', 'reservation', 'review', 'setting'],
            'export' => ['reservation', 'review', 'user'],
            'approve' => ['review', 'reservation'],
            'reject' => ['review', 'reservation'],
            'login' => [null],
            'logout' => [null],
            'block' => ['user'],
        ];

        $ips = [
            '192.168.1.10', '192.168.1.11', '10.0.0.5',
            '85.43.21.99', '203.0.113.42', '198.51.100.7',
        ];

        $userAgents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/124.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 Safari/605.1.15',
            'Mozilla/5.0 (X11; Linux x86_64) Firefox/125.0',
            'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148',
        ];

        $stmt = $this->db->prepare(
            'INSERT INTO audit_logs
             (user_id, action, resource_type, resource_id, old_values, new_values, ip_address, user_agent, created_at)
             VALUES
             (:user_id, :action, :resource_type, :resource_id, :old_values, :new_values, :ip, :ua,
              NOW() - INTERVAL :days DAY - INTERVAL :hours HOUR - INTERVAL :mins MINUTE)'
        );

        $inserted = 0;
        $actionKeys = \array_keys($actions);

        // ~200 registros en 90 días: densidad variable (más recientes = más registros)
        for ($i = 0; $i < 210; $i++) {
            // Distribución no uniforme: más actividad reciente
            $days = $this->weightedDays(90);
            $hours = \rand(0, 23);
            $mins = \rand(0, 59);

            $action = $actionKeys[\array_rand($actionKeys)];
            $resourceTypes = $actions[$action];
            $resourceType = $resourceTypes[\array_rand($resourceTypes)];

            $userId = $userIds[\array_rand($userIds)];
            $resourceId = $resourceType !== null ? \rand(1, 50) : null;
            $ip = $ips[\array_rand($ips)];
            $ua = $userAgents[\array_rand($userAgents)];

            [$oldValues, $newValues] = $this->generateValues($action, $resourceType);

            $stmt->execute([
                'user_id' => $userId,
                'action' => $action,
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'ip' => $ip,
                'ua' => $ua,
                'days' => $days,
                'hours' => $hours,
                'mins' => $mins,
            ]);

            $inserted++;
        }

        Logger::info('[AuditLogSeeder] done', ['inserted' => $inserted]);
    }

    /**
     * Devuelve un número de días con distribución no uniforme:
     * más probabilidad de días recientes (últimas 2 semanas = ~40% del total).
     */
    private function weightedDays(int $max): int
    {
        $r = \rand(1, 100);

        if ($r <= 40) {
            return \rand(0, 14);      // 40 %: últimas 2 semanas
        }

        if ($r <= 70) {
            return \rand(15, 30);     // 30 %: semanas 3-4
        }

        return \rand(31, $max);       // 30 %: meses 2-3
    }

    /**
     * Genera old_values / new_values JSON según acción y recurso.
     *
     * @return array{0: string|null, 1: string|null}
     */
    private function generateValues(string $action, ?string $resourceType): array
    {
        if ($action === 'login' || $action === 'logout' || $action === 'view' || $action === 'export') {
            return [null, null];
        }

        $fields = match ($resourceType) {
            'user' => ['name', 'email', 'is_active'],
            'cafe' => ['name', 'capacity', 'is_active'],
            'animal' => ['name', 'species', 'status'],
            'product' => ['name', 'price', 'is_active'],
            'reservation' => ['status', 'guest_count'],
            'review' => ['status', 'rating'],
            'setting' => ['value'],
            'role' => ['name', 'permissions'],
            default => ['value'],
        };

        if ($action === 'create') {
            $new = [];
            foreach ($fields as $f) {
                $new[$f] = $this->fakeValue($f);
            }

            return [null, \json_encode($new)];
        }

        if ($action === 'delete') {
            $old = [];
            foreach ($fields as $f) {
                $old[$f] = $this->fakeValue($f);
            }

            return [\json_encode($old), null];
        }

        // update / approve / reject / block
        $field = $fields[\array_rand($fields)];
        $oldData = [$field => $this->fakeValue($field)];
        $newData = [$field => $this->fakeValue($field)];

        return [\json_encode($oldData), \json_encode($newData)];
    }

    private function fakeValue(string $field): string|int|bool
    {
        return match ($field) {
            'name' => 'Elemento ' . \rand(1, 99),
            'email' => 'user' . \rand(1, 99) . '@example.com',
            'is_active' => (bool) \rand(0, 1),
            'capacity' => \rand(5, 30),
            'status' => ['active', 'inactive', 'pending', 'completed'][\rand(0, 3)],
            'species' => ['cat', 'dog', 'rabbit', 'bird'][\rand(0, 3)],
            'price' => \rand(500, 3000),
            'guest_count' => \rand(1, 6),
            'rating' => \rand(1, 5),
            'value' => 'valor-' . \rand(1, 99),
            'permissions' => 'read,write',
            default => 'dato-' . \rand(1, 99),
        };
    }
}
