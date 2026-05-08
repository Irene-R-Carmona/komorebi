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

        // Obtener algunos time slots con capacidad completa
        $fullSlots = $this->db->query('
            SELECT id, cafe_id, slot_date, slot_time, available_spots
            FROM time_slots
            WHERE available_spots = 0
            ORDER BY slot_date DESC, slot_time DESC
            LIMIT 5
        ')->fetchAll(PDO::FETCH_ASSOC);

        if (empty($fullSlots)) {
            Logger::warning('[WaitlistSeeder] no full slots, marking some as full');
            // Marcar algunos slots como completos
            $this->db->exec('
                UPDATE time_slots
                SET available_spots = 0
                WHERE slot_date >= CURDATE()
                ORDER BY slot_date, slot_time
                LIMIT 5
            ');

            $fullSlots = $this->db->query('
                SELECT id, cafe_id, slot_date, slot_time, available_spots
                FROM time_slots
                WHERE available_spots = 0
                ORDER BY slot_date DESC, slot_time DESC
                LIMIT 5
            ')->fetchAll(PDO::FETCH_ASSOC);
        }

        // Obtener usuarios de prueba (clientes, no staff)
        $users = $this->db->query("
            SELECT DISTINCT u.id, u.name, u.email
            FROM users u
            INNER JOIN user_roles ur ON u.id = ur.user_id
            INNER JOIN roles r ON ur.role_id = r.id
            WHERE r.code = 'user'
            ORDER BY RAND()
            LIMIT 15
        ")->fetchAll(PDO::FETCH_ASSOC);

        if (empty($users)) {
            Logger::warning('[WaitlistSeeder] no users available');

            return;
        }

        $inserted = 0;
        $position = 1;

        foreach ($fullSlots as $slot) {
            // Añadir 2-4 personas en waitlist por slot
            $peopleInWaitlist = \rand(2, 4);

            for ($i = 0; $i < $peopleInWaitlist && $inserted < \count($users); $i++) {
                $user = $users[$inserted % \count($users)];

                $status = 'waiting';
                $notifiedAt = null;
                $expiresAt = \date('Y-m-d H:i:s', \time() + 86400); // seed: expira en 24h
                $token = \bin2hex(\random_bytes(16));

                // Algunos en estado 'notified' (10%)
                if ($i === 0 && \rand(1, 10) === 1) {
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

            // Resetear posición para el siguiente slot
            $position = 1;
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
