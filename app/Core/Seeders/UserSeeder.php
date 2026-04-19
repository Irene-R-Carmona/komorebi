<?php

declare(strict_types=1);

namespace App\Core\Seeders;

use App\Core\Database;
use App\Core\Logger;
use Exception;
use PDO;
use Random\RandomException;

/**
 * UserSeeder
 *
 * Crea usuarios de prueba normales (rol 'user' via RBAC).
 * Estos usuarios son usados para demo: crear reservas, reseñas, etc.
 */
final class UserSeeder
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function run(): void
    {
        echo "Creando usuarios de prueba...\n";
        Logger::info('UserSeeder: starting');

        $defaultPass = \password_hash('komorebi2024', PASSWORD_ARGON2ID);

        // Obtener rol 'user'
        $roleStmt = $this->db->prepare('SELECT id FROM roles WHERE code = :code');
        $roleStmt->execute(['code' => 'user']);
        $roleRow = $roleStmt->fetch(PDO::FETCH_ASSOC);

        if (!$roleRow) {
            echo "Rol 'user' no encontrado. Abortando.\n";
            Logger::warning("UserSeeder: role 'user' not found");

            return;
        }

        $userRoleId = (int) $roleRow['id'];

        // Usuarios más realistas con nombres comunes en España y emails variados
        $users = [
            ['name' => 'Carmen Ruiz', 'email' => 'carmen.ruiz@gmail.com', 'newsletter' => true],
            ['name' => 'Antonio García', 'email' => 'antonio.garcia@hotmail.es', 'newsletter' => false],
            ['name' => 'Isabel López', 'email' => 'isabel.lopez@yahoo.es', 'newsletter' => true],
            ['name' => 'Francisco Martín', 'email' => 'paco.martin@outlook.com', 'newsletter' => true],
            ['name' => 'Pilar Jiménez', 'email' => 'pilar.jimenez@gmail.com', 'newsletter' => false],
            ['name' => 'José Luis Pérez', 'email' => 'joseluis.perez@gmail.com', 'newsletter' => true],
            ['name' => 'Dolores Sánchez', 'email' => 'lola.sanchez@hotmail.com', 'newsletter' => true],
            ['name' => 'Manuel Fernández', 'email' => 'manolo.fernandez@gmail.com', 'newsletter' => false],
            ['name' => 'Rosa María Torres', 'email' => 'rosamaria.torres@yahoo.es', 'newsletter' => true],
            ['name' => 'Javier González', 'email' => 'javi.gonzalez@outlook.com', 'newsletter' => false],
            ['name' => 'Ana Belén Romero', 'email' => 'anabelen.romero@gmail.com', 'newsletter' => true],
            ['name' => 'Miguel Ángel Díaz', 'email' => 'miguelangel.diaz@hotmail.es', 'newsletter' => true],
            ['name' => 'Lucía Moreno', 'email' => 'lucia.moreno@gmail.com', 'newsletter' => false],
            ['name' => 'David Muñoz', 'email' => 'david.munoz@gmail.com', 'newsletter' => true],
            ['name' => 'Cristina Álvarez', 'email' => 'cristina.alvarez@outlook.com', 'newsletter' => false],
            ['name' => 'Sergio Romero', 'email' => 'sergio.romero@yahoo.es', 'newsletter' => true],
            ['name' => 'Marta Navarro', 'email' => 'marta.navarro@gmail.com', 'newsletter' => true],
            ['name' => 'Raúl Hernández', 'email' => 'raul.hernandez@hotmail.es', 'newsletter' => false],
            ['name' => 'Beatriz Gil', 'email' => 'bea.gil@gmail.com', 'newsletter' => true],
            ['name' => 'Alberto Vázquez', 'email' => 'alberto.vazquez@outlook.com', 'newsletter' => false],
        ];

        $count = 0;
        foreach ($users as $user) {
            try {
                // Avatar and simple prefs
                $safeEmail = \preg_replace('/[^a-z0-9.\-@]/i', '_', $user['email']);
                $avatar = '/images/users/' . $safeEmail . '.jpg';
                $preferences = \json_encode([
                    'locale' => 'es-ES',
                    'newsletter' => $user['newsletter'],
                ], JSON_UNESCAPED_UNICODE);

                $this->createUser($user['name'], $user['email'], $defaultPass, $userRoleId, $avatar, $preferences);
                $count++;
            } catch (Exception $e) {
                echo "Error creando {$user['email']}: " . $e->getMessage() . "\n";
                Logger::error('UserSeeder: error creating user', ['email' => $user['email'], 'exception' => $e->getMessage()]);
            }
        }

        echo "$count usuarios de prueba creados.\n";
        Logger::info('UserSeeder: completed', ['created' => $count]);
    }

    /**
     * Crea un usuario y le asigna el rol 'user'.
     *
     * @throws RandomException
     */
    private function createUser(string $name, string $email, string $hashedPass, int $userRoleId, ?string $avatar = null, ?string $preferences = null): void
    {
        // Verificar si ya existe
        $check = $this->db->prepare('SELECT id FROM users WHERE email = :email');
        $check->execute(['email' => $email]);

        if ($check->fetch(PDO::FETCH_ASSOC)) {
            Logger::debug('UserSeeder: user already exists', ['email' => $email]);

            return; // Usuario ya existe
        }

        // Crear usuario con avatar y preferences cuando se provean
        $uuid = $this->generateUuid();
        $stmt = $this->db->prepare(
            'INSERT INTO users (uuid, name, email, password, is_active, avatar, preferences, email_verified_at, created_at)
             VALUES (:uuid, :name, :email, :password, 1, :avatar, :prefs, NOW(), NOW())'
        );

        $stmt->execute([
            'uuid'     => $uuid,
            'name'     => $name,
            'email'    => $email,
            'password' => $hashedPass,
            'avatar'   => $avatar,
            'prefs'    => $preferences,
        ]);

        $userId = (int) $this->db->lastInsertId();

        // Asignar rol 'user'
        try {
            $roleStmt = $this->db->prepare('
                INSERT INTO user_roles (user_id, role_id, assigned_at)
                VALUES (:user_id, :role_id, NOW())
            ');
            $roleStmt->execute(['user_id' => $userId, 'role_id' => $userRoleId]);
        } catch (Exception $e) {
            Logger::error("UserSeeder: Error assigning role to $email", ['exception' => $e->getMessage()]);
        }
    }

    /**
     * Genera un UUID v4.
     *
     * @throws RandomException
     */
    private function generateUuid(): string
    {
        $data = \random_bytes(16);
        $data[6] = \chr(\ord($data[6]) & 0x0f | 0x40);
        $data[8] = \chr(\ord($data[8]) & 0x3f | 0x80);

        return \vsprintf('%s%s-%s-%s-%s-%s%s%s', \str_split(\bin2hex($data), 4));
    }
}
