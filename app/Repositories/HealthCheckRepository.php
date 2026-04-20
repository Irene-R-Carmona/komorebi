<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Repositories\Contracts\HealthCheckRepositoryInterface;
use PDO;

/**
 * Repositorio para gestionar chequeos de salud animal.
 * Implementa operaciones CRUD y consultas optimizadas usando vistas.
 *
 * @package App\Repositories
 */
final class HealthCheckRepository implements HealthCheckRepositoryInterface
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('
            SELECT hc.*,
                   a.name AS animal_name,
                   a.species_type,
                   a.current_status,
                   u.name AS keeper_name
            FROM animal_health_checks hc
            INNER JOIN animals a ON hc.animal_id = a.id
            INNER JOIN users u ON hc.checked_by = u.id
            WHERE hc.id = :id
        ');
        $stmt->execute(['id' => $id]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result !== false ? $result : null;
    }

    public function findByAnimalAndDate(int $animalId, ?string $date = null): ?array
    {
        $date ??= \date('Y-m-d');

        $stmt = $this->db->prepare('
            SELECT hc.*,
                   a.name AS animal_name,
                   a.species_type,
                   u.name AS keeper_name
            FROM animal_health_checks hc
            INNER JOIN animals a ON hc.animal_id = a.id
            INNER JOIN users u ON hc.checked_by = u.id
            WHERE hc.animal_id = :animal_id
              AND hc.check_date = :date
        ');
        $stmt->execute([
            'animal_id' => $animalId,
            'date' => $date,
        ]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result !== false ? $result : null;
    }

    public function findTodayByAnimalId(int $animalId): ?array
    {
        return $this->findByAnimalAndDate($animalId, \date('Y-m-d'));
    }

    public function getCheckHistory(int $animalId, int $limit = 30): array
    {
        $stmt = $this->db->prepare('
            SELECT hc.*,
                   u.name AS keeper_name
            FROM animal_health_checks hc
            INNER JOIN users u ON hc.checked_by = u.id
            WHERE hc.animal_id = :animal_id
            ORDER BY hc.check_date DESC, hc.created_at DESC
            LIMIT :limit
        ');
        $stmt->bindValue('animal_id', $animalId, PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTodayChecks(): array
    {
        // Usa vista optimizada health_checks_today
        $stmt = $this->db->query('
            SELECT *
            FROM health_checks_today
            ORDER BY created_at DESC
        ');

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPendingAnimals(?int $cafeId = null): array
    {
        // Usa vista optimizada animals_pending_check_today
        if ($cafeId !== null) {
            $stmt = $this->db->prepare('
                SELECT *
                FROM animals_pending_check_today
                WHERE cafe_id = :cafe_id
            ');
            $stmt->execute(['cafe_id' => $cafeId]);
        } else {
            $stmt = $this->db->query('
                SELECT *
                FROM animals_pending_check_today
            ');
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCheckswithAlerts(int $days = 7): array
    {
        $stmt = $this->db->prepare('
            SELECT hc.*,
                   a.name AS animal_name,
                   a.species_type,
                   a.current_status,
                   u.name AS keeper_name
            FROM animal_health_checks hc
            INNER JOIN animals a ON hc.animal_id = a.id
            INNER JOIN users u ON hc.checked_by = u.id
            WHERE hc.alerts IS NOT NULL
              AND JSON_LENGTH(hc.alerts) > 0
              AND hc.check_date >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
            ORDER BY hc.check_date DESC, hc.created_at DESC
        ');
        $stmt->execute(['days' => $days]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare('
            INSERT INTO animal_health_checks (
                animal_id,
                checked_by,
                check_date,
                weight_kg,
                temperature_c,
                appetite,
                energy_level,
                coat_condition,
                eyes_clear,
                breathing_normal,
                mobility_normal,
                notes,
                alerts
            ) VALUES (
                :animal_id,
                :checked_by,
                :check_date,
                :weight_kg,
                :temperature_c,
                :appetite,
                :energy_level,
                :coat_condition,
                :eyes_clear,
                :breathing_normal,
                :mobility_normal,
                :notes,
                :alerts
            )
        ');

        $stmt->execute([
            'animal_id' => $data['animal_id'],
            'checked_by' => $data['checked_by'],
            'check_date' => $data['check_date'] ?? \date('Y-m-d'),
            'weight_kg' => $data['weight_kg'] ?? null,
            'temperature_c' => $data['temperature_c'] ?? null,
            'appetite' => $data['appetite'] ?? 'normal',
            'energy_level' => $data['energy_level'] ?? 'normal',
            'coat_condition' => $data['coat_condition'] ?? 'good',
            'eyes_clear' => $data['eyes_clear'] ?? true,
            'breathing_normal' => $data['breathing_normal'] ?? true,
            'mobility_normal' => $data['mobility_normal'] ?? true,
            'notes' => $data['notes'] ?? null,
            'alerts' => isset($data['alerts']) ? \json_encode($data['alerts']) : null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function exists(int $animalId, string $date): bool
    {
        $stmt = $this->db->prepare('
            SELECT COUNT(*) as count
            FROM animal_health_checks
            WHERE animal_id = :animal_id
              AND check_date = :date
        ');
        $stmt->execute([
            'animal_id' => $animalId,
            'date' => $date,
        ]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result !== false && (int) $result['count'] > 0;
    }

    public function countByKeeperInPeriod(int $keeperId, ?string $startDate = null, ?string $endDate = null): int
    {
        $startDate ??= \date('Y-m-01'); // Primer día del mes actual
        $endDate ??= \date('Y-m-d'); // Hoy

        $stmt = $this->db->prepare('
            SELECT COUNT(*) as count
            FROM animal_health_checks
            WHERE checked_by = :keeper_id
              AND check_date BETWEEN :start_date AND :end_date
        ');
        $stmt->execute([
            'keeper_id' => $keeperId,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result !== false ? (int) $result['count'] : 0;
    }

    public function getRecentLogs(int $limit = 20): array
    {
        $stmt = $this->db->prepare('
            SELECT hc.*, a.name AS animal_name, a.species_type AS species, u.name AS keeper_name
            FROM animal_health_checks hc
            JOIN animals a ON hc.animal_id = a.id
            LEFT JOIN users u ON hc.checked_by = u.id
            WHERE hc.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ORDER BY hc.created_at DESC
            LIMIT :limit
        ');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createCareLog(array $data): int
    {
        $stmt = $this->db->prepare('
            INSERT INTO animal_health_checks (animal_id, checked_by, check_date, notes, created_at)
            VALUES (:animal_id, :checked_by, CURDATE(), :notes, NOW())
            ON DUPLICATE KEY UPDATE notes = CONCAT(notes, "\n---\n", :notes_upd), created_at = NOW()
        ');
        $stmt->execute([
            'animal_id' => $data['animal_id'],
            'checked_by' => $data['logged_by_user_id'] ?? 1,
            'notes' => $data['notes'],
            'notes_upd' => $data['notes'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function getAlertStatistics(int $days = 7): array
    {
        $stmt = $this->db->prepare("
            SELECT
                DATE(check_date) as alert_date,
                COUNT(*) as total_checks_with_alerts,
                SUM(JSON_CONTAINS(alerts, '\"Fiebre detectada\"', '$')) as fever_count,
                SUM(JSON_CONTAINS(alerts, '\"Sin apetito\"', '$')) as appetite_count,
                SUM(JSON_CONTAINS(alerts, '\"Letargo\"', '$')) as lethargy_count,
                SUM(JSON_CONTAINS(alerts, '\"Síntomas respiratorios\"', '$')) as respiratory_count
            FROM animal_health_checks
            WHERE alerts IS NOT NULL
              AND JSON_LENGTH(alerts) > 0
              AND check_date >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
            GROUP BY DATE(check_date)
            ORDER BY alert_date DESC
        ");
        $stmt->execute(['days' => $days]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
