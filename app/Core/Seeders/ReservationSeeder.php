<?php

declare(strict_types=1);

namespace App\Core\Seeders;

use App\Core\Database;
use App\Core\Logger;
use DateTimeImmutable;
use PDO;
use PDOException;
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
        Logger::info('[ReservationSeeder] starting');

        $existingCount = (int) $this->db->query('SELECT COUNT(*) FROM reservations')->fetchColumn();
        if ($existingCount > 0) {
            Logger::info('[ReservationSeeder] reservations already seeded — skipping');

            return;
        }

        // Obtener usuarios y cafés
        $userIds     = $this->db->query('SELECT id FROM users WHERE id > 1 ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
        $cafeIds     = $this->db->query('SELECT id FROM cafes ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
        $passProduct = $this->db->query("SELECT id, name, price, duration_minutes FROM products WHERE product_type = 'pass' LIMIT 1")->fetch(PDO::FETCH_ASSOC);

        if (empty($userIds) || empty($cafeIds) || empty($passProduct)) {
            Logger::warning('[ReservationSeeder] missing base data — run UserSeeder, CafeSeeder and MenuSeeder first');

            return;
        }

        // --- Sección histórica (1 ene – ayer): ~300 reservas con picos en períodos especiales ---
        $historicalCreated = $this->seedHistorical($userIds, $cafeIds, $passProduct);

        // --- Sección futura (hoy + 16 días): ~150–180 reservas con picos en fines de semana ---
        $timeSlots = $this->db->query('
            SELECT id, cafe_id, slot_date, slot_time, available_spots
            FROM time_slots
            WHERE slot_date >= CURDATE()
            AND available_spots > 0
            ORDER BY slot_date, slot_time
        ')->fetchAll(PDO::FETCH_ASSOC);

        if (empty($timeSlots)) {
            Logger::warning('[ReservationSeeder] no time_slots available');
            Logger::info('[ReservationSeeder] completed', ['historical' => $historicalCreated, 'future' => 0]);

            return;
        }

        Logger::info('[ReservationSeeder] time_slots found', ['count' => \count($timeSlots)]);

        $futureCreated = $this->seedFuture($userIds, $passProduct, $timeSlots);

        Logger::info('[ReservationSeeder] completed', [
            'historical' => $historicalCreated,
            'future'     => $futureCreated,
            'total'      => $historicalCreated + $futureCreated,
        ]);
    }

    /**
     * Genera ~300 reservas históricas (1 ene – ayer) con picos en períodos especiales.
     *
     * @throws RandomException
     */
    private function seedHistorical(array $userIds, array $cafeIds, array $passProduct): int
    {
        $created   = 0;
        $maxTarget = 300;
        $today     = new DateTimeImmutable('today');
        $d         = new DateTimeImmutable('2026-01-01');

        while ($d < $today && $created < $maxTarget) {
            $dateStr   = $d->format('Y-m-d');
            $dow       = (int) $d->format('N'); // 1=lun, 7=dom
            $isWeekend = $dow >= 6;

            // Reservas base del día
            $dayTarget = $isWeekend ? \random_int(2, 5) : \random_int(1, 3);

            // Bonus por períodos especiales con alta demanda
            if ($dateStr >= '2026-02-13' && $dateStr <= '2026-02-16') {
                $dayTarget += \random_int(2, 4); // San Valentín
            } elseif ($dateStr >= '2026-04-01' && $dateStr <= '2026-04-12') {
                $dayTarget += \random_int(1, 3); // Semana Santa
            } elseif ($dateStr >= '2026-04-30' && $dateStr <= '2026-05-03') {
                $dayTarget += \random_int(1, 3); // Puente de Mayo
            }

            // No exceder el objetivo total
            $dayTarget = \min($dayTarget, $maxTarget - $created);

            for ($i = 0; $i < $dayTarget; $i++) {
                $userId    = $userIds[\array_rand($userIds)];
                $cafeId    = $cafeIds[\array_rand($cafeIds)];
                $hour      = \random_int(10, 19);
                $minute    = [0, 15, 30, 45][\random_int(0, 3)];
                $time      = \sprintf('%02d:%02d:00', $hour, $minute);
                $guests    = \random_int(1, 4);
                $status    = \random_int(1, 10) > 2 ? 'completed' : 'no_show';

                $checkInAt     = null;
                $checkOutAt    = null;
                $paymentStatus = 'pending';
                $finalAmount   = null;

                if ($status === 'completed') {
                    $checkInAt       = $dateStr . ' ' . $time;
                    $durationMinutes = (int) ($passProduct['duration_minutes'] ?? 60);
                    $checkOutAt      = \date('Y-m-d H:i:s', \strtotime($checkInAt) + $durationMinutes * 60);
                    $paymentStatus   = 'paid';
                    $finalAmount     = (int) $passProduct['price'] * $guests;
                }

                $this->insertReservation(
                    $userId,
                    $cafeId,
                    $passProduct,
                    $dateStr,
                    $time,
                    $guests,
                    $status,
                    null,
                    $checkInAt,
                    $checkOutAt,
                    $paymentStatus,
                    $finalAmount
                );
                $created++;
            }

            $d = $d->modify('+1 day');
        }

        Logger::info('[ReservationSeeder] historical created', ['count' => $created]);

        return $created;
    }

    /**
     * Genera ~150-180 reservas futuras con picos en fines de semana.
     *
     * @throws RandomException
     */
    private function seedFuture(array $userIds, array $passProduct, array $timeSlots): int
    {
        // Agrupar slots por fecha
        $slotsByDate = [];
        foreach ($timeSlots as $slot) {
            $slotsByDate[$slot['slot_date']][] = $slot;
        }

        $created   = 0;
        $usedSlots = [];

        foreach ($slotsByDate as $date => $daySlots) {
            $dow       = (int) (new DateTimeImmutable($date))->format('N');
            $isWeekend = $dow >= 6;

            // Fin de semana: más reservas (pico de demanda)
            $dayTarget = $isWeekend ? \random_int(15, 25) : \random_int(6, 12);

            // Mezclar para selección aleatoria dentro del día
            \shuffle($daySlots);
            $dayCreated = 0;

            foreach ($daySlots as $slot) {
                if ($dayCreated >= $dayTarget) {
                    break;
                }

                if (isset($usedSlots[$slot['id']])) {
                    continue;
                }

                $userId     = $userIds[\array_rand($userIds)];
                $guestCount = \min(\random_int(1, 3), (int) $slot['available_spots']);
                $status     = \random_int(1, 10) > 3 ? 'confirmed' : 'pending';

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

                $this->db->prepare('
                    UPDATE time_slots
                    SET available_spots = available_spots - :guests_sub,
                        reserved_spots  = reserved_spots  + :guests_add
                    WHERE id = :slot_id
                ')->execute([
                    'guests_sub' => $guestCount,
                    'guests_add' => $guestCount,
                    'slot_id'    => (int) $slot['id'],
                ]);

                $created++;
                $dayCreated++;
            }
        }

        Logger::info('[ReservationSeeder] future created', ['count' => $created]);

        return $created;
    }

    private function insertReservation(
        int $userId,
        int $cafeId,
        array $passProduct,
        string $reservationDate,
        string $reservationTime,
        int $guestCount,
        string $status,
        ?int $timeSlotId,
        ?string $checkInAt = null,
        ?string $checkOutAt = null,
        ?string $paymentStatus = null,
        ?int $finalAmount = null
    ): void {
        $columns = [
            'user_id',
            'cafe_id',
            'pass_product_id',
            'pass_name',
            'pass_unit_price',
            'pass_duration_minutes',
            'reservation_date',
            'reservation_time',
            'guest_count',
            'status',
            'created_at',
            'updated_at',
        ];
        $placeholders = [
            ':user_id',
            ':cafe_id',
            ':pass_product_id',
            ':pass_name',
            ':pass_unit_price',
            ':pass_duration_minutes',
            ':reservation_date',
            ':reservation_time',
            ':guest_count',
            ':status',
            'NOW()',
            'NOW()',
        ];

        if ($timeSlotId !== null) {
            $columns[] = 'time_slot_id';
            $placeholders[] = ':time_slot_id';
        }

        if ($checkInAt !== null) {
            $columns[] = 'check_in_at';
            $placeholders[] = ':check_in_at';
        }

        if ($checkOutAt !== null) {
            $columns[] = 'check_out_at';
            $placeholders[] = ':check_out_at';
        }

        if ($paymentStatus !== null) {
            $columns[] = 'payment_status';
            $placeholders[] = ':payment_status';
        }

        if ($finalAmount !== null) {
            $columns[] = 'final_amount';
            $placeholders[] = ':final_amount';
        }

        $stmt = $this->db->prepare(
            'INSERT INTO reservations (' . \implode(', ', $columns) . ') ' .
                'VALUES (' . \implode(', ', $placeholders) . ')'
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

            if ($checkInAt !== null) {
                $params['check_in_at'] = $checkInAt;
            }

            if ($checkOutAt !== null) {
                $params['check_out_at'] = $checkOutAt;
            }

            if ($paymentStatus !== null) {
                $params['payment_status'] = $paymentStatus;
            }

            if ($finalAmount !== null) {
                $params['final_amount'] = $finalAmount;
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
        } catch (PDOException $e) {
            Logger::error('ReservationSeeder: insert failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
