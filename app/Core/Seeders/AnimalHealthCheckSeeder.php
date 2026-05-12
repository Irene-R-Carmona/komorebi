<?php

declare(strict_types=1);

namespace App\Core\Seeders;

use App\Core\Database;
use App\Core\Logger;
use PDO;
use PDOStatement;

/**
 * AnimalHealthCheckSeeder
 *
 * Genera chequeos diarios de salud para los últimos 14 días.
 * Un chequeo por animal por día (UNIQUE KEY uk_animal_check_date).
 * Incluye algunos registros con alertas para demostrar el módulo keeper.
 *
 * Depende de: AnimalSeeder, StaffSeeder (rol keeper)
 */
final class AnimalHealthCheckSeeder
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function run(): void
    {
        Logger::info('[AnimalHealthCheckSeeder] starting');

        $animals = $this->getActiveAnimals();
        $keepers = $this->getKeepers();

        if (empty($animals)) {
            Logger::warning('[AnimalHealthCheckSeeder] no active animals — skipping');

            return;
        }

        if (empty($keepers)) {
            Logger::warning('[AnimalHealthCheckSeeder] no keepers found — skipping');

            return;
        }

        Logger::info('[AnimalHealthCheckSeeder] data loaded', [
            'animals' => \count($animals),
            'keepers' => \count($keepers),
        ]);

        $stmt = $this->db->prepare(
            'INSERT IGNORE INTO animal_health_checks
                (animal_id, checked_by, check_date, weight_kg, temperature_c,
                 appetite, energy_level, coat_condition, eyes_clear,
                 breathing_normal, mobility_normal, notes, alerts)
             VALUES
                (:animal_id, :checked_by, :check_date, :weight_kg, :temperature_c,
                 :appetite, :energy_level, :coat_condition, :eyes_clear,
                 :breathing_normal, :mobility_normal, :notes, :alerts)'
        );

        $today = \strtotime('today');
        $days = 90; // 14 días diarios + 76 días semanales = ~90 días de histórico
        $total = 0;

        for ($d = $days - 1; $d >= 0; $d--) {
            // Para días anteriores a las últimas 2 semanas: solo chequeos semanales
            if ($d >= 14 && ($d % 7 !== 0)) {
                continue;
            }

            $dayTs = $today - ($d * 86400);
            $dayStr = \date('Y-m-d', $dayTs);
            $keeperId = $keepers[\array_rand($keepers)]['id'];

            foreach ($animals as $animal) {
                $this->insertCheck($stmt, $animal, $dayStr, (int) $keeperId);
                $total++;
            }
        }

        Logger::info('[AnimalHealthCheckSeeder] done', ['health_checks_created' => $total]);
    }

    /**
     * @param array{id: string, species_type: string} $animal
     */
    private function insertCheck(PDOStatement $stmt, array $animal, string $dayStr, int $keeperId): void
    {
        $weightBase = $this->getWeightBase($animal['species_type']);
        $weight = \round($weightBase + (\random_int(-20, 20) / 100), 2);

        [$temp, $alerts] = $this->buildTempAndAlerts();

        $hasIssue = \random_int(1, 10) === 1;

        $stmt->execute([
            'animal_id' => (int) $animal['id'],
            'checked_by' => $keeperId,
            'check_date' => $dayStr,
            'weight_kg' => $weight,
            'temperature_c' => $temp,
            'appetite' => $hasIssue ? 'reduced' : 'normal',
            'energy_level' => $hasIssue ? 'low' : 'normal',
            'coat_condition' => $hasIssue ? 'fair' : 'good',
            'eyes_clear' => $hasIssue ? 0 : 1,
            'breathing_normal' => 1,
            'mobility_normal' => 1,
            'notes' => $hasIssue ? 'Leve decaimiento, en observación.' : null,
            'alerts' => $alerts,
        ]);
    }

    /** @return array{float, string|null} */
    private function buildTempAndAlerts(): array
    {
        $rand = \random_int(1, 100);
        if ($rand <= 80) {
            return [\round(37.5 + (\random_int(-100, 100) / 100), 2), null];
        }
        if ($rand <= 95) {
            return [\round(39.0 + (\random_int(0, 50) / 100), 2), \json_encode(['temperature_high'])];
        }

        return [\round(40.0 + (\random_int(0, 100) / 100), 2), \json_encode(['temperature_critical', 'veterinary_required'])];
    }

    /** @return array<int, array{id: string, species_type: string}> */
    private function getActiveAnimals(): array
    {
        $stmt = $this->db->query(
            "SELECT id, species_type FROM animals WHERE current_status = 'active' ORDER BY id"
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<int, array{id: string}> */
    private function getKeepers(): array
    {
        $stmt = $this->db->query(
            "SELECT DISTINCT u.id
             FROM users u
             INNER JOIN user_roles ur ON ur.user_id = u.id
             INNER JOIN roles r ON r.id = ur.role_id
             WHERE r.code = 'keeper' AND u.is_active = 1"
        );
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Si no hay keepers, usar cualquier staff (reception/kitchen/supervisor)
        if (empty($rows)) {
            $stmt = $this->db->query(
                "SELECT DISTINCT u.id
                 FROM users u
                 INNER JOIN user_roles ur ON ur.user_id = u.id
                 INNER JOIN roles r ON r.id = ur.role_id
                 WHERE r.code IN ('reception', 'kitchen', 'supervisor') AND u.is_active = 1
                 LIMIT 5"
            );
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        return $rows;
    }

    private function getWeightBase(string $speciesType): float
    {
        return match ($speciesType) {
            'gato' => 4.2,
            'perro' => 8.5,
            'conejo' => 2.1,
            'chinchilla' => 0.48,
            'ardilla' => 0.35,
            'loro' => 0.35,
            'capybara' => 55.0,
            'alpaca' => 70.0,
            'cerdito' => 12.0,
            'pato' => 1.8,
            'cobaya' => 0.9,
            'perrito_pradera' => 1.2,
            'caballo' => 90.0,
            'tortuga' => 5.5,
            default => 2.0,
        };
    }
}
