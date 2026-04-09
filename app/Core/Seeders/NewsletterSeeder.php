<?php

declare(strict_types=1);

namespace App\Core\Seeders;

use App\Core\Database;
use App\Core\Logger;
use PDO;
use PDOException;
use Random\RandomException;

/**
 * NewsletterSeeder
 *
 * Crea suscriptores de newsletter de prueba
 */
final class NewsletterSeeder
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
        Logger::info('NewsletterSeeder: starting');

        $subscribers = [
            ['email' => 'maria.lopez@gmail.com', 'status' => 'confirmed'],
            ['email' => 'carlos.garcia@hotmail.es', 'status' => 'confirmed'],
            ['email' => 'ana.martinez@yahoo.es', 'status' => 'confirmed'],
            ['email' => 'luis.rodriguez@outlook.com', 'status' => 'confirmed'],
            ['email' => 'laura.fernandez@gmail.com', 'status' => 'confirmed'],
            ['email' => 'pedro.sanchez@hotmail.com', 'status' => 'confirmed'],
            ['email' => 'sofia.gomez@gmail.com', 'status' => 'pending'],
            ['email' => 'javier.diaz@yahoo.es', 'status' => 'confirmed'],
            ['email' => 'elena.ruiz@outlook.com', 'status' => 'unsubscribed'],
            ['email' => 'miguel.torres@gmail.com', 'status' => 'confirmed'],
            ['email' => 'patricia.navarro@hotmail.es', 'status' => 'confirmed'],
            ['email' => 'raul.moreno@gmail.com', 'status' => 'pending'],
            ['email' => 'cristina.jimenez@yahoo.es', 'status' => 'confirmed'],
            ['email' => 'sergio.ortega@gmail.com', 'status' => 'confirmed'],
            ['email' => 'isabel.castro@outlook.com', 'status' => 'confirmed'],
        ];

        $stmt = $this->db->prepare('
            INSERT INTO newsletter_subscriptions (email, token, confirmed_at, unsubscribed_at)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                confirmed_at = VALUES(confirmed_at),
                unsubscribed_at = VALUES(unsubscribed_at)
        ');

        $count = 0;
        foreach ($subscribers as $sub) {
            $token = \bin2hex(\random_bytes(32));

            $confirmedAt = ($sub['status'] === 'confirmed') ? \date('Y-m-d H:i:s') : null;
            $unsubscribedAt = ($sub['status'] === 'unsubscribed') ? \date('Y-m-d H:i:s') : null;

            try {
                $stmt->execute([
                    $sub['email'],
                    $token,
                    $confirmedAt,
                    $unsubscribedAt,
                ]);
                $count++;
            } catch (PDOException $e) {
                Logger::error('NewsletterSeeder: insert failed', ['email' => $sub['email'], 'exception' => $e->getMessage()]);
                // Skip duplicados
                continue;
            }
        }

        echo "[NewsletterSeeder] $count suscriptores creados\n";
        Logger::info('NewsletterSeeder: completed', ['created' => $count]);
    }
}
