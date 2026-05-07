<?php

declare(strict_types=1);

namespace App\Core\Seeders;

use App\Core\Database;
use App\Core\Logger;
use DateTimeImmutable;
use PDO;

/**
 * TimeSlotSeeder
 *
 * Actualiza los time_slots generados con datos más realistas
 * basados en los cafés reales y sus horarios
 */
final class TimeSlotSeeder
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function run(): void
    {
        Logger::info('[TimeSlotSeeder] starting');

        // Obtener cafés con sus horarios
        $cafes = $this->db->query('
            SELECT id, name, capacity_max, opening_time, closing_time
            FROM cafes
            WHERE is_active = 1
        ')->fetchAll(PDO::FETCH_ASSOC);

        if (empty($cafes)) {
            Logger::warning('[TimeSlotSeeder] no active cafes found');

            return;
        }

        // Verificar si ya existen slots
        $existingSlots = (int) $this->db->query('SELECT COUNT(*) FROM time_slots')->fetchColumn();

        if ($existingSlots === 0) {
            // Crear slots nuevos
            Logger::info('[TimeSlotSeeder] creating slots for next 14 days');
            $this->createTimeSlots($cafes);
        } else {
            // Resetear reserved_spots (las reservas se crearán después)
            $this->db->exec('UPDATE time_slots SET reserved_spots = 0');
            Logger::info('[TimeSlotSeeder] reserved_spots reset');
        }

        $updated = 0;

        foreach ($cafes as $cafe) {
            // Ajustar capacidad según tipo de café (algunos más pequeños, otros más grandes)
            $baseCapacity = (int) $cafe['capacity_max'];

            // Variación realista de capacidad por hora del día
            $capacityVariations = [
                '10:00' => 0.7,  // Mañana tranquila
                '11:00' => 0.8,
                '12:00' => 0.9,  // Pre-almuerzo
                '13:00' => 1.0,  // Hora punta
                '14:00' => 1.0,
                '15:00' => 0.8,  // Post-almuerzo
                '16:00' => 0.7,
                '17:00' => 0.8,  // Merienda
                '18:00' => 0.9,
                '19:00' => 1.0,  // Hora punta tarde
                '20:00' => 0.9,
            ];

            // Obtener time_slots de este café
            $slots = $this->db->prepare('
                SELECT id, slot_time, total_capacity, available_spots
                FROM time_slots
                WHERE cafe_id = :cafe_id
                AND slot_date >= CURDATE()
            ');
            $slots->execute(['cafe_id' => $cafe['id']]);

            foreach ($slots->fetchAll(PDO::FETCH_ASSOC) as $slot) {
                $hour = \substr($slot['slot_time'], 0, 5);
                $variation = $capacityVariations[$hour] ?? 0.8;

                // Nueva capacidad ajustada
                $newCapacity = \max(1, (int) \round($baseCapacity * $variation));
                $currentReserved = (int) $slot['total_capacity'] - (int) $slot['available_spots'];
                $newAvailable = \max(0, $newCapacity - $currentReserved);

                $this->db->prepare('
                    UPDATE time_slots
                    SET total_capacity = :capacity,
                        available_spots = :available
                    WHERE id = :id
                ')->execute([
                    'capacity' => $newCapacity,
                    'available' => $newAvailable,
                    'id' => $slot['id'],
                ]);

                $updated++;
            }
        }

        // Bloquear algunos slots aleatoriamente (mantenimiento, eventos privados)
        $this->blockRandomSlots();

        Logger::info('[TimeSlotSeeder] completed', ['updated' => $updated]);
    }

    private function blockRandomSlots(): void
    {
        // Bloquear ~5% de slots para mantenimiento/eventos
        $futureSlots = $this->db->query('
            SELECT id
            FROM time_slots
            WHERE slot_date >= CURDATE()
            AND is_blocked = 0
            ORDER BY RAND()
            LIMIT 25
        ')->fetchAll(PDO::FETCH_COLUMN);

        $blockReasons = [
            'Mantenimiento programado',
            'Evento privado',
            'Limpieza profunda',
            'Veterinario',
            'Sesión fotográfica',
            'Formación del personal',
        ];

        $blocked = 0;
        foreach ($futureSlots as $slotId) {
            // Solo bloquear algunos, no todos
            if (\random_int(1, 10) > 7) {
                // Seleccionar razón (aunque no se guarda en BD)
                $reason = $blockReasons[\array_rand($blockReasons)];

                $this->db->prepare('
                    UPDATE time_slots
                    SET is_blocked = 1,
                        available_spots = 0
                    WHERE id = :id
                ')->execute(['id' => $slotId]);

                $blocked++;
            }
        }

        Logger::info('[TimeSlotSeeder] slots blocked', ['count' => $blocked]);
    }

    private function createTimeSlots(array $cafes): void
    {
        $created = 0;
        $today = new DateTimeImmutable();

        // Slots cada 30 minutos durante el horario del café
        $slotDuration = 30;

        foreach ($cafes as $cafe) {
            $capacity = (int) $cafe['capacity_max'];

            // Generar slots para los próximos 14 días
            for ($day = 0; $day < 14; $day++) {
                $date = $today->modify("+$day days");
                $dateStr = $date->format('Y-m-d');

                // Parsear horarios del café
                $opening = DateTimeImmutable::createFromFormat('H:i:s', $cafe['opening_time']);
                $closing = DateTimeImmutable::createFromFormat('H:i:s', $cafe['closing_time']);

                if (!$opening || !$closing) {
                    continue;
                }

                // Generar slots cada 30 minutos
                $currentTime = $opening;
                while ($currentTime < $closing) {
                    $timeStr = $currentTime->format('H:i:s');

                    // Variación de capacidad según hora
                    $hour = $currentTime->format('H:i');
                    $slotCapacity = $this->getSlotCapacity($capacity, $hour);

                    // Insertar slot
                    $stmt = $this->db->prepare('
                        INSERT INTO time_slots
                        (cafe_id, slot_date, slot_time, total_capacity, reserved_spots, available_spots, is_blocked)
                        VALUES (:cafe_id, :date, :time, :capacity, 0, :available, 0)
                    ');

                    $stmt->execute([
                        'cafe_id' => $cafe['id'],
                        'date' => $dateStr,
                        'time' => $timeStr,
                        'capacity' => $slotCapacity,
                        'available' => $slotCapacity,
                    ]);

                    $created++;
                    $currentTime = $currentTime->modify("+$slotDuration minutes");
                }
            }
        }

        Logger::info('[TimeSlotSeeder] slots created', ['count' => $created]);
    }

    private function getSlotCapacity(int $baseCapacity, string $hour): int
    {
        // Variación realista de capacidad por hora del día
        $variations = [
            '10:00' => 0.7,  // Mañana tranquila
            '11:00' => 0.8,
            '12:00' => 0.9,  // Pre-almuerzo
            '13:00' => 1.0,  // Hora punta
            '14:00' => 1.0,
            '15:00' => 0.8,  // Post-almuerzo
            '16:00' => 0.7,
            '17:00' => 0.8,  // Merienda
            '18:00' => 0.9,
            '19:00' => 1.0,  // Hora punta tarde
            '20:00' => 0.9,
        ];

        $variation = $variations[$hour] ?? 0.8;

        return \max(1, (int) \round($baseCapacity * $variation));
    }
}
