<?php

declare(strict_types=1);

namespace App\Core\Seeders;

use App\Core\Database;
use App\Core\Logger;
use PDO;
use PDOStatement;

/**
 * StaffShiftSeeder
 *
 * Genera 4 semanas de turnos de staff (3 pasadas + 1 futura).
 * Cada café tiene staff asignado con turnos de mañana y tarde.
 *
 * Depende de: StaffSeeder, CafeSeeder
 */
final class StaffShiftSeeder
{
    /** @var array<int, array{start: string, end: string}> */
    private const SHIFTS = [
        ['start' => '09:00:00', 'end' => '17:00:00'],
        ['start' => '14:00:00', 'end' => '22:00:00'],
    ];

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function run(): void
    {
        Logger::info('[StaffShiftSeeder] starting');

        $existingCount = (int) $this->db->query('SELECT COUNT(*) FROM staff_shifts')->fetchColumn();
        if ($existingCount > 0) {
            Logger::info('[StaffShiftSeeder] shifts already seeded — skipping');

            return;
        }

        $staffByCafe = $this->getStaffByCafe();
        $managers = $this->getManagers();

        if (empty($staffByCafe)) {
            Logger::warning('[StaffShiftSeeder] no staff found — skipping');

            return;
        }

        if (empty($managers)) {
            Logger::warning('[StaffShiftSeeder] no managers found — shifts will have null created_by');
        }

        Logger::info('[StaffShiftSeeder] staff loaded', ['cafes' => \count($staffByCafe)]);

        $stmt = $this->db->prepare(
            'INSERT INTO staff_shifts
                (user_id, cafe_id, shift_date, shift_start, shift_end, notes, created_by)
             VALUES
                (:user_id, :cafe_id, :shift_date, :shift_start, :shift_end, :notes, :created_by)'
        );

        $today = \strtotime('today');
        // 3 semanas pasadas + esta semana + 1 semana futura = 5 semanas, lunes a domingo
        $startMonday = $today - ((\date('N', $today) - 1) * 86400) - (21 * 86400); // lunes de hace 3 semanas

        $total = 0;

        for ($week = 0; $week < 5; $week++) {
            for ($day = 0; $day < 7; $day++) {
                $dayTs = $startMonday + ($week * 7 * 86400) + ($day * 86400);
                $dayStr = \date('Y-m-d', $dayTs);
                $isWeekend = $day >= 5;

                foreach ($staffByCafe as $cafeId => $staffList) {
                    $total += $this->insertDayShifts($stmt, $cafeId, $staffList, $managers, $dayStr, $isWeekend, $week);
                }
            }
        }

        Logger::info('[StaffShiftSeeder] done', ['shifts_created' => $total]);
    }

    /**
     * @param array<array{id: string}> $staffList
     * @param array<array{id: string}> $managers
     */
    private function insertDayShifts(
        PDOStatement $stmt,
        int $cafeId,
        array $staffList,
        array $managers,
        string $dayStr,
        bool $isWeekend,
        int $week
    ): int {
        $managerId = !empty($managers) ? $managers[\array_rand($managers)]['id'] : null;
        $count = 0;

        foreach ($staffList as $member) {
            if ($isWeekend && \random_int(1, 10) > 4) {
                continue;
            }

            $shiftIndex = ($week + (int) $member['id']) % 2;
            $shift = self::SHIFTS[$shiftIndex];

            $stmt->execute([
                'user_id' => (int) $member['id'],
                'cafe_id' => $cafeId,
                'shift_date' => $dayStr,
                'shift_start' => $shift['start'],
                'shift_end' => $shift['end'],
                'notes' => null,
                'created_by' => $managerId !== null ? (int) $managerId : null,
            ]);
            $count++;
        }

        return $count;
    }

    /**
     * Devuelve staff agrupado por café (roles: reception, kitchen, supervisor).
     *
     * @return array<int, array<int, array{id: string}>>
     */
    private function getStaffByCafe(): array
    {
        $stmt = $this->db->query(
            "SELECT u.id, u.cafe_id
             FROM users u
             INNER JOIN user_roles ur ON ur.user_id = u.id
             INNER JOIN roles r ON r.id = ur.role_id
             WHERE r.code IN ('reception', 'kitchen', 'supervisor')
               AND u.is_active = 1
               AND u.cafe_id IS NOT NULL
             ORDER BY u.cafe_id, u.id"
        );
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $byCafe = [];
        foreach ($rows as $row) {
            $byCafe[(int) $row['cafe_id']][] = $row;
        }

        return $byCafe;
    }

    /**
     * @return array<int, array{id: string}>
     */
    private function getManagers(): array
    {
        $stmt = $this->db->query(
            "SELECT u.id
             FROM users u
             INNER JOIN user_roles ur ON ur.user_id = u.id
             INNER JOIN roles r ON r.id = ur.role_id
             WHERE r.code = 'manager' AND u.is_active = 1"
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
