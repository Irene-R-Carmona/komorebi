<?php

declare(strict_types=1);

namespace App\Core\Seeders;

use App\Core\Database;
use App\Core\Logger;
use PDO;

/**
 * WaitlistSeeder - Datos de prueba para lista de espera
 *
 * FASE 2.3: Seeder de waitlist
 */
final class WaitlistSeeder
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function run(): void
    {
        Logger::info('[WaitlistSeeder] starting');

        // Obtener usuarios de prueba (clientes, no staff)
        $users = $this->db->query("
            SELECT DISTINCT u.id, u.name, u.email
            FROM users u
            INNER JOIN user_roles ur ON u.id = ur.user_id
            INNER JOIN roles r ON ur.role_id = r.id
            WHERE r.code = 'user'
            ORDER BY RAND()
            LIMIT 25
        ")->fetchAll(PDO::FETCH_ASSOC);

        if (empty($users)) {
            Logger::warning('[WaitlistSeeder] no users available');

            return;
        }

        // Seleccionar slots en horas punta de los próximos fines de semana (fechas pico para demo)
        $peakSlots = $this->db->query("
            SELECT id, cafe_id, slot_date, slot_time, available_spots
            FROM time_slots
            WHERE slot_date IN ('2026-05-16', '2026-05-17', '2026-05-23', '2026-05-24')
            AND slot_time BETWEEN '17:00:00' AND '20:30:00'
            ORDER BY RAND()
            LIMIT 6
        ")->fetchAll(PDO::FETCH_ASSOC);

        // Fallback: usar slots futuros disponibles si las fechas pico no existen aún
        if (empty($peakSlots)) {
            Logger::warning('[WaitlistSeeder] no peak slots found, using available future slots');
            $peakSlots = $this->db->query('
                SELECT id, cafe_id, slot_date, slot_time, available_spots
                FROM time_slots
                WHERE slot_date >= CURDATE()
                AND available_spots > 0
                ORDER BY slot_date ASC
                LIMIT 6
            ')->fetchAll(PDO::FETCH_ASSOC);
        }

        if (empty($peakSlots)) {
            Logger::warning('[WaitlistSeeder] no suitable slots for waitlist');

            return;
        }

        // Marcar los slots pico como completos (justifica la lista de espera)
        foreach ($peakSlots as $slot) {
            $this->db->prepare('
                UPDATE time_slots
                SET available_spots = 0,
                    reserved_spots  = total_capacity
                WHERE id = :id
            ')->execute(['id' => $slot['id']]);
        }

        $inserted = 0;
        $userIndex = 0;
        $userCount = \count($users);

        foreach ($peakSlots as $slot) {
            // 3-5 personas en lista de espera por slot pico
            $perSlot = \rand(3, 5);
            $position = 1;

            for ($i = 0; $i < $perSlot; $i++) {
                $user = $users[$userIndex % $userCount];
                $userIndex++;

                $status = 'waiting';
                $notifiedAt = null;
                $expiresAt = \date('Y-m-d H:i:s', \time() + 86400);
                $token = \bin2hex(\random_bytes(16));

                // El primero de cada slot puede estar en estado 'notified' (40%)
                if ($i === 0 && \rand(1, 5) <= 2) {
                    $status = 'notified';
                    $notifiedAt = \date('Y-m-d H:i:s', \time() - \rand(60, 600));
                    $expiresAt = \date('Y-m-d H:i:s', \time() + \rand(300, 900));
                }

                $this->db->prepare('
                    INSERT IGNORE INTO waitlist (
                        user_id, time_slot_id, position, status,
                        guest_count, contact_email, contact_phone,
                        token, notified_at, expires_at,
                        response_timeout_minutes, special_requests,
                        created_at
                    ) VALUES (
                        :user_id, :time_slot_id, :position, :status,
                        :guest_count, :contact_email, :contact_phone,
                        :token, :notified_at, :expires_at,
                        15, :special_requests, NOW()
                    )
                ')->execute([
                    'user_id' => $user['id'],
                    'time_slot_id' => $slot['id'],
                    'position' => $position++,
                    'status' => $status,
                    'guest_count' => \rand(1, 4),
                    'contact_email' => $user['email'],
                    'contact_phone' => $this->generatePhone(),
                    'token' => $token,
                    'notified_at' => $notifiedAt,
                    'expires_at' => $expiresAt,
                    'special_requests' => $this->getRandomRequest(),
                ]);

                $inserted++;
            }
        }

        Logger::info('[WaitlistSeeder] completed', ['inserted' => $inserted]);
    }

    private function generatePhone(): ?string
    {
        if (\rand(1, 3) === 1) {
            return null;
        }

        return '+34 6' . \str_pad((string) \rand(10000000, 99999999), 8, '0', STR_PAD_LEFT);
    }

    private function getRandomRequest(): ?string
    {
        $requests = [
            null,
            null,
            null, // 60% sin notas
            'Mesa junto a ventana si es posible',
            'Preferimos zona tranquila',
            'Nos gustaría conocer a los gatos más sociables',
            'Primera vez en un cat café, necesitamos orientación',
            'Queremos celebrar un cumpleaños',
        ];

        return $requests[\array_rand($requests)];
    }
}
