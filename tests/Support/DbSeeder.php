<?php

declare(strict_types=1);

namespace Tests\Support;

use PDO;
use Throwable;

final class DbSeeder
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function ensureCafe(): int
    {
        $row = $this->db->query("SELECT id FROM cafes LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if ($row && isset($row['id'])) {
            return (int) $row['id'];
        }

        $slug = 'test-cafe-' . bin2hex(random_bytes(4));

        // Intentar insertar con varias combinaciones según esquema
        try {
            $sql = sprintf("INSERT INTO cafes (name, slug, is_active, has_reservations) VALUES ('%s', '%s', 1, 1)", 'Test Cafe', $this->db->quote($slug));
            $this->db->exec($sql);
        } catch (Throwable $e) {
            try {
                $sql = sprintf("INSERT INTO cafes (name, slug) VALUES ('%s', %s)", 'Test Cafe', $this->db->quote($slug));
                $this->db->exec($sql);
            } catch (Throwable $e2) {
                // Última opción: insertar usando prepared mínimo
                $stmt = $this->db->prepare('INSERT INTO cafes (name, slug) VALUES (?, ?)');
                $stmt->execute(['Test Cafe', $slug]);
            }
        }

        return (int) $this->db->lastInsertId();
    }

    public function ensureTimeSlot(int $availableSpots, ?int $cafeId = null): int
    {
        $stmt = $this->db->prepare('SELECT id FROM time_slots WHERE available_spots = ? AND slot_date >= CURDATE() LIMIT 1');
        $stmt->execute([$availableSpots]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && isset($row['id'])) {
            return (int) $row['id'];
        }

        $cafeId = $cafeId ?? $this->ensureCafe();

        $slotDate = date('Y-m-d');
        $slotTime = ($availableSpots > 0) ? '15:00:00' : '14:00:00';

        $sql = 'INSERT INTO time_slots (cafe_id, slot_date, slot_time, total_capacity, available_spots, reserved_spots, duration_minutes) VALUES (?, ?, ?, ?, ?, ?, ?)';
        $this->db->prepare($sql)->execute([$cafeId, $slotDate, $slotTime, 20, $availableSpots, max(0, 20 - $availableSpots), 60]);

        return (int) $this->db->lastInsertId();
    }

    public function ensureUser(?string $emailSuffix = null): int
    {
        $suffix = $emailSuffix ?? bin2hex(random_bytes(3));
        $email = sprintf('test-user+%s@example.com', $suffix);

        // Si existe un usuario con ese email, devolverlo
        $stmt = $this->db->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && isset($row['id'])) {
            return (int) $row['id'];
        }

        // Construir insert respetando columnas NOT NULL
        $cols = $this->db->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_ASSOC);
        $insertCols = [];
        $placeholders = [];
        $values = [];

        $cafeRow = $this->db->query('SELECT id FROM cafes LIMIT 1')->fetch(PDO::FETCH_ASSOC);
        $cafeId = $cafeRow['id'] ?? null;

        foreach ($cols as $col) {
            $name = $col['Field'];
            if (stripos($col['Extra'] ?? '', 'auto_increment') !== false) {
                continue;
            }
            $insertCols[] = "`$name`";
            $placeholders[] = '?';
            $type = $col['Type'];

            if ($name === 'name') {
                $values[] = 'Test User';
            } elseif ($name === 'email') {
                $values[] = $email;
            } elseif ($name === 'uuid') {
                $values[] = bin2hex(random_bytes(16));
            } elseif ($name === 'cafe_id' && $cafeId !== null) {
                $values[] = (int) $cafeId;
            } elseif (stripos($type, 'int') !== false) {
                $values[] = 0;
            } elseif (stripos($type, 'json') !== false) {
                $values[] = '{}';
            } elseif (stripos($type, 'enum') !== false) {
                if (preg_match('/^enum\((.*)\)$/i', $type, $m)) {
                    $opts = array_map(fn($s) => trim($s, "'\""), explode(',', $m[1]));
                    $values[] = $opts[0] ?? '';
                } else {
                    $values[] = '';
                }
            } elseif (stripos($type, 'date') !== false || stripos($type, 'time') !== false) {
                $values[] = date('Y-m-d H:i:s');
            } else {
                $values[] = '';
            }
        }

        $sql = 'INSERT INTO users (' . implode(',', $insertCols) . ') VALUES (' . implode(',', $placeholders) . ')';
        $this->db->prepare($sql)->execute($values);

        return (int) $this->db->lastInsertId();
    }

    public function ensureWaitingEntry(int $slotId, int $userId): int
    {
        // Verificar entrada existente en espera
        $stmt = $this->db->prepare('SELECT id FROM waitlist WHERE time_slot_id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$slotId, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && isset($row['id'])) {
            return (int) $row['id'];
        }

        $token = bin2hex(random_bytes(16));
        $expires = date('Y-m-d H:i:s', time() + 3600);

        $sql = 'INSERT INTO waitlist (time_slot_id, user_id, position, token, expires_at, status, contact_email, guest_count) VALUES (?, ?, ?, ?, ?, ?, ?, ?)';
        $this->db->prepare($sql)->execute([$slotId, $userId, 1, $token, $expires, 'waiting', 'test-user@example.com', 1]);

        return (int) $this->db->lastInsertId();
    }

    public function findUserNotInWaitlistForSlot(int $slotId): ?int
    {
        $stmt = $this->db->prepare('SELECT id FROM users WHERE id NOT IN (SELECT user_id FROM waitlist WHERE time_slot_id = ?) LIMIT 1');
        $stmt->execute([$slotId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && isset($row['id'])) {
            return (int) $row['id'];
        }

        return null;
    }
}
