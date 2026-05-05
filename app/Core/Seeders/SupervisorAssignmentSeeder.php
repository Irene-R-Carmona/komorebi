<?php

declare(strict_types=1);

namespace App\Core\Seeders;

use App\Core\Database;
use App\Core\Logger;
use PDO;

/**
 * SupervisorAssignmentSeeder
 *
 * Asigna supervisores a ~50% de las reservas completadas.
 * Genera un table_code único por asignación.
 *
 * Depende de: StaffSeeder (rol supervisor), ReservationSeeder
 */
final class SupervisorAssignmentSeeder
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function run(): void
    {
        Logger::info('[SupervisorAssignmentSeeder] starting');

        $supervisors = $this->getSupervisors();
        $reservations = $this->getCompletedReservations();

        if (empty($supervisors)) {
            Logger::warning('[SupervisorAssignmentSeeder] no supervisors found — skipping');
            return;
        }

        if (\count($reservations) === 0) {
            Logger::warning('[SupervisorAssignmentSeeder] no completed reservations — skipping');
            return;
        }

        Logger::info('[SupervisorAssignmentSeeder] data loaded', [
            'supervisors'  => \count($supervisors),
            'reservations' => \count($reservations),
        ]);

        $stmt = $this->db->prepare(
            'INSERT INTO supervisor_assignments
                (supervisor_id, reservation_id, table_code, cafe_id, is_active, assigned_at)
             VALUES
                (:supervisor_id, :reservation_id, :table_code, :cafe_id, :is_active, :assigned_at)'
        );

        $total = 0;

        foreach ($reservations as $reservation) {
            // Asignar ~50% de las reservas completadas
            if (\random_int(1, 2) === 1) {
                continue;
            }

            $supervisor   = $supervisors[\array_rand($supervisors)];
            $cafeId       = (int) $reservation['cafe_id'];
            $tableCode    = \sprintf('TABLE-%d-%03d', $cafeId, \random_int(1, 20));
            $assignedAt   = $reservation['check_in_at'] ?? $reservation['created_at'];

            $stmt->execute([
                'supervisor_id'  => (int) $supervisor['id'],
                'reservation_id' => (int) $reservation['id'],
                'table_code'     => $tableCode,
                'cafe_id'        => $cafeId,
                'is_active'      => 0, // completadas → ya no activas
                'assigned_at'    => $assignedAt,
            ]);
            $total++;
        }

        Logger::info('[SupervisorAssignmentSeeder] done', ['assignments_created' => $total]);
    }

    /** @return array<int, array{id: string}> */
    private function getSupervisors(): array
    {
        $stmt = $this->db->query(
            "SELECT DISTINCT u.id
             FROM users u
             INNER JOIN user_roles ur ON ur.user_id = u.id
             INNER JOIN roles r ON r.id = ur.role_id
             WHERE r.code = 'supervisor' AND u.is_active = 1"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, array{id: string, cafe_id: string, check_in_at: string|null, created_at: string}>
     */
    private function getCompletedReservations(): array
    {
        $stmt = $this->db->query(
            "SELECT id, cafe_id, check_in_at, created_at
             FROM reservations
             WHERE status = 'completed'
             ORDER BY id"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
