<?php

declare(strict_types=1);

namespace App\Core\Seeders;

use App\Core\Database;
use App\Core\Logger;
use PDO;

/**
 * TelegramSeeder
 *
 * Crea usuarios de Telegram de prueba vinculados a usuarios existentes
 */
final class TelegramSeeder
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function run(): void
    {
        Logger::info('TelegramSeeder: starting');

        // Obtener usuarios existentes (excluyendo admin)
        $stmt = $this->db->query("
            SELECT id FROM users
            WHERE email NOT LIKE '%admin%'
            ORDER BY RAND()
            LIMIT 10
        ");
        $users = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($users)) {
            echo "[TelegramSeeder] No hay usuarios disponibles\n";
            Logger::warning('TelegramSeeder: no users found');

            return;
        }

        $stmt = $this->db->prepare('
            INSERT INTO telegram_users (user_id, telegram_id, chat_id, username, first_name, is_active, last_message_at)
            VALUES (?, ?, ?, ?, ?, 1, NOW())
            ON DUPLICATE KEY UPDATE
                is_active = VALUES(is_active),
                last_message_at = VALUES(last_message_at)
        ');

        $count = 0;
        $firstNames = ['Carlos', 'María', 'Javier', 'Laura', 'David', 'Ana', 'Miguel', 'Elena', 'Pablo', 'Sara'];
        $lastNames = ['García', 'Martínez', 'López', 'Sánchez', 'Pérez', 'Gómez', 'Fernández', 'Díaz', 'Ruíz', 'Torres'];

        foreach ($users as $index => $userId) {
            // Generar IDs únicos (simulados)
            $telegramId = 100000000 + $userId * 11111;
            $chatId = 1000000000 + $userId * 123456;

            // Nombres realistas
            $firstName = $firstNames[$index % \count($firstNames)];
            $lastName = $lastNames[$index % \count($lastNames)];
            $username = \strtolower($firstName) . '_' . \strtolower($lastName);

            try {
                $stmt->execute([$userId, $telegramId, $chatId, $username, $firstName]);
                $count++;
            } catch (\PDOException $e) {
                Logger::error('TelegramSeeder: insert failed', ['user_id' => $userId, 'exception' => $e->getMessage()]);
                // Skip duplicados
                continue;
            }
        }

        echo "[TelegramSeeder] $count usuarios Telegram creados\n";
        Logger::info('TelegramSeeder: users created', ['created' => $count]);

        // Crear mensajes de log de ejemplo
        $this->createSampleMessages();
    }

    private function createSampleMessages(): void
    {
        $stmt = $this->db->query('SELECT id FROM telegram_users LIMIT 5');
        $telegramUsers = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($telegramUsers)) {
            return;
        }

        $messages = [
            ['direction' => 'incoming', 'command' => '/start', 'text' => '/start', 'response' => '¡Bienvenido a Komorebi Café! 🐾'],
            ['direction' => 'outgoing', 'command' => null, 'text' => null, 'response' => '¡Bienvenido a Komorebi Café! 🐾'],
            ['direction' => 'incoming', 'command' => '/menu', 'text' => '/menu', 'response' => 'Menú de opciones enviado'],
            ['direction' => 'outgoing', 'command' => null, 'text' => null, 'response' => 'Menú de opciones enviado'],
            ['direction' => 'incoming', 'command' => '/reserva', 'text' => '/reserva', 'response' => 'Sistema de reservas activado'],
            ['direction' => 'outgoing', 'command' => null, 'text' => null, 'response' => 'Sistema de reservas activado'],
        ];

        $stmt = $this->db->prepare('
            INSERT INTO telegram_message_log (telegram_user_id, direction, command, message_text, response_text)
            VALUES (?, ?, ?, ?, ?)
        ');

        $count = 0;
        foreach ($telegramUsers as $telegramUserId) {
            foreach ($messages as $msg) {
                $stmt->execute([
                    $telegramUserId,
                    $msg['direction'],
                    $msg['command'],
                    $msg['text'],
                    $msg['response'],
                ]);
                $count++;
            }
        }

        echo "[TelegramSeeder] $count mensajes de log creados\n";
        Logger::info('TelegramSeeder: sample messages created', ['count' => $count]);
    }
}
