<?php

declare(strict_types=1);

namespace App\Core\Seeders;

use App\Core\Database;
use App\Core\Logger;
use PDO;
use Random\RandomException;

/**
 * Seeder de Reservas actualizado
 * Genera reservas realistas sincronizadas con time_slots
 */
final class ReservationSeeder
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * @throws RandomException
     */
    public function run(): void
    {
        echo "Generando reservas realistas...\n";
        Logger::info('ReservationSeeder: starting');

        // Obtener usuarios y cafés
        $userIds = $this->db->query('SELECT id FROM users WHERE id > 1 ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
        $cafeIds = $this->db->query('SELECT id FROM cafes ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
        $passProduct = $this->db->query("SELECT id, name, price, duration_minutes FROM products WHERE product_type = 'pass' LIMIT 1")->fetch(PDO::FETCH_ASSOC);

        if (empty($userIds) || empty($cafeIds) || empty($passProduct)) {
            echo "⚠️  Faltan datos base. Ejecuta UserSeeder, CafeSeeder y MenuSeeder primero.\n";

            return;
        }

        // Obtener time_slots disponibles
        $timeSlots = $this->db->query('
            SELECT id, cafe_id, slot_date, slot_time, available_spots
            FROM time_slots
            WHERE slot_date >= CURDATE()
            AND available_spots > 0
            ORDER BY slot_date, slot_time
        ')->fetchAll(PDO::FETCH_ASSOC);

        if (empty($timeSlots)) {
            echo "⚠️  No hay time_slots disponibles. Ejecuta migración 011.\n";
            Logger::warning('ReservationSeeder: no time_slots available');

            return;
        }

        echo '  → ' . \count($timeSlots) . " time_slots disponibles\n";

        $reservationsCreated = 0;

        // Generar reservas pasadas (últimos 15 días)
        echo "  → Creando reservas pasadas...\n";
        for ($i = 0; $i < 25; $i++) {
            $userId = $userIds[\array_rand($userIds)];
            $cafeId = $cafeIds[\array_rand($cafeIds)];

            $daysAgo = \random_int(1, 15);
            $hour = \random_int(10, 19);
            $minute = [0, 15, 30, 45][\random_int(0, 3)];

            $reservationDate = \date('Y-m-d', \strtotime("-$daysAgo days"));
            $reservationTime = \sprintf('%02d:%02d:00', $hour, $minute);
            $guestCount = \random_int(1, 4);

            // Las reservas pasadas están completadas o fueron no-show
            $status = \random_int(1, 10) > 2 ? 'completed' : 'no_show';

            $this->insertReservation(
                $userId,
                $cafeId,
                $passProduct,
                $reservationDate,
                $reservationTime,
                $guestCount,
                $status,
                null
            );

            $reservationsCreated++;
        }

        // Generar reservas futuras con time_slots
        echo "  → Creando reservas futuras con time_slots...\n";
        $usedSlots = [];
        foreach ($timeSlots as $slot) {
            // Evitar duplicados y limitar
            if (isset($usedSlots[$slot['id']]) || $reservationsCreated >= 60) {
                continue;
            }

            // Solo algunos slots tienen reserva (más realista)
            if (\random_int(1, 10) > 6) {
                continue;
            }

            $userId = $userIds[\array_rand($userIds)];
            $guestCount = \min(\random_int(1, 3), (int) $slot['available_spots']);

            $status = \random_int(1, 10) > 3 ? 'confirmed' : 'pending';

            $this->insertReservation(
                $userId,
                (int) $slot['cafe_id'],
                $passProduct,
                $slot['slot_date'],
                $slot['slot_time'],
                $guestCount,
                $status,
                (int) $slot['id']
            );

            $usedSlots[$slot['id']] = true;
            $reservationsCreated++;

            // Decrementar spots disponibles
            $updateStmt = $this->db->prepare('
                UPDATE time_slots
                SET available_spots = available_spots - :guests_sub,
                    reserved_spots = reserved_spots + :guests_add
                WHERE id = :slot_id
            ');
            $updateStmt->execute([
                'guests_sub' => $guestCount,
                'guests_add' => $guestCount,
                'slot_id' => (int) $slot['id'],
            ]);
        }

        echo "✅ $reservationsCreated reservas creadas (" . \count($usedSlots) . " con time_slot)\n";
        Logger::info('ReservationSeeder: completed', ['created' => $reservationsCreated]);
    }

    private function insertReservation(
        int $userId,
        int $cafeId,
        array $passProduct,
        string $reservationDate,
        string $reservationTime,
        int $guestCount,
        string $status,
        ?int $timeSlotId
    ): void {
        $columns = [
            'user_id', 'cafe_id', 'pass_product_id', 'pass_name', 'pass_unit_price',
            'pass_duration_minutes', 'reservation_date', 'reservation_time',
            'guest_count', 'status', 'created_at', 'updated_at'
        ];
        $placeholders = [
            ':user_id', ':cafe_id', ':pass_product_id', ':pass_name', ':pass_unit_price',
            ':pass_duration_minutes', ':reservation_date', ':reservation_time',
            ':guest_count', ':status', 'NOW()', 'NOW()'
        ];

        if ($timeSlotId !== null) {
            $columns[] = 'time_slot_id';
            $placeholders[] = ':time_slot_id';
        }

        $stmt = $this->db->prepare(
            'INSERT INTO reservations (' . implode(', ', $columns) . ') ' .
            'VALUES (' . implode(', ', $placeholders) . ')'
        );

        try {
            $params = [
                'user_id' => $userId,
                'cafe_id' => $cafeId,
                'pass_product_id' => $passProduct['id'],
                'pass_name' => $passProduct['name'],
                'pass_unit_price' => $passProduct['price'],
                'pass_duration_minutes' => $passProduct['duration_minutes'] ?? 60,
                'reservation_date' => $reservationDate,
                'reservation_time' => $reservationTime,
                'guest_count' => $guestCount,
                'status' => $status,
            ];

            if ($timeSlotId !== null) {
                $params['time_slot_id'] = $timeSlotId;
            }

            $result = $stmt->execute($params);

            if (!$result) {
                $errorInfo = $stmt->errorInfo();
                Logger::error('ReservationSeeder: execute failed', [
                    'sqlstate' => $errorInfo[0],
                    'driver_code' => $errorInfo[1],
                    'message' => $errorInfo[2],
                ]);
            }
        } catch (\PDOException $e) {
            Logger::error('ReservationSeeder: insert failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
