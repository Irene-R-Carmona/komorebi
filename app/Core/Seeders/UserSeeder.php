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
        Logger::info('UserSeeder: starting');

        $defaultPass = \password_hash('komorebi2024', PASSWORD_ARGON2ID);

        // Obtener rol 'user'
        $roleStmt = $this->db->prepare('SELECT id FROM roles WHERE code = :code');
        $roleStmt->execute(['code' => 'user']);
        $roleRow = $roleStmt->fetch(PDO::FETCH_ASSOC);

        if (!$roleRow) {
            Logger::warning("UserSeeder: role 'user' not found");

            return;
        }

        $userRoleId = (int) $roleRow['id'];

        // 60 usuarios con nombres españoles y proveedores variados
        $users = [
            // Bloque 1: 20 usuarios originales
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
            // Bloque 2: 20 usuarios adicionales
            ['name' => 'Elena Castillo', 'email' => 'elena.castillo@gmail.com', 'newsletter' => true],
            ['name' => 'Pablo Ortega', 'email' => 'pablo.ortega@hotmail.es', 'newsletter' => true],
            ['name' => 'Silvia Ramos', 'email' => 'silvia.ramos@yahoo.es', 'newsletter' => false],
            ['name' => 'Fernando Molina', 'email' => 'fernando.molina@gmail.com', 'newsletter' => true],
            ['name' => 'Amparo Herrera', 'email' => 'amparo.herrera@outlook.com', 'newsletter' => true],
            ['name' => 'Óscar Aguilar', 'email' => 'oscar.aguilar@gmail.com', 'newsletter' => false],
            ['name' => 'Nuria Domínguez', 'email' => 'nuria.dominguez@hotmail.es', 'newsletter' => true],
            ['name' => 'Ignacio Rubio', 'email' => 'ignacio.rubio@gmail.com', 'newsletter' => false],
            ['name' => 'Mónica Pardo', 'email' => 'monica.pardo@yahoo.es', 'newsletter' => true],
            ['name' => 'Adrián Santos', 'email' => 'adrian.santos@gmail.com', 'newsletter' => true],
            ['name' => 'Natalia Cano', 'email' => 'natalia.cano@outlook.com', 'newsletter' => false],
            ['name' => 'Jorge Vargas', 'email' => 'jorge.vargas@gmail.com', 'newsletter' => true],
            ['name' => 'Paloma Iglesias', 'email' => 'paloma.iglesias@hotmail.com', 'newsletter' => true],
            ['name' => 'Víctor Serrano', 'email' => 'victor.serrano@gmail.com', 'newsletter' => false],
            ['name' => 'Laura Medina', 'email' => 'laura.medina@yahoo.es', 'newsletter' => true],
            ['name' => 'Roberto Guerrero', 'email' => 'roberto.guerrero@gmail.com', 'newsletter' => true],
            ['name' => 'Susana Blanco', 'email' => 'susana.blanco@outlook.com', 'newsletter' => false],
            ['name' => 'Alejandro Campos', 'email' => 'alex.campos@gmail.com', 'newsletter' => true],
            ['name' => 'Inmaculada Reyes', 'email' => 'inma.reyes@hotmail.es', 'newsletter' => true],
            ['name' => 'Tomás Fuentes', 'email' => 'tomas.fuentes@gmail.com', 'newsletter' => false],
            // Bloque 3: 20 usuarios para demostrar tiers de fidelización
            ['name' => 'Valentina Cruz', 'email' => 'valentina.cruz@gmail.com', 'newsletter' => true],
            ['name' => 'Rodrigo Peña', 'email' => 'rodrigo.pena@yahoo.es', 'newsletter' => true],
            ['name' => 'Rocío Caballero', 'email' => 'rocio.caballero@gmail.com', 'newsletter' => false],
            ['name' => 'Enrique Prieto', 'email' => 'enrique.prieto@hotmail.es', 'newsletter' => true],
            ['name' => 'Gemma Vidal', 'email' => 'gemma.vidal@gmail.com', 'newsletter' => true],
            ['name' => 'Marcos León', 'email' => 'marcos.leon@outlook.com', 'newsletter' => false],
            ['name' => 'Patricia Gallego', 'email' => 'patricia.gallego@gmail.com', 'newsletter' => true],
            ['name' => 'Héctor Mora', 'email' => 'hector.mora@yahoo.es', 'newsletter' => true],
            ['name' => 'Alicia Ibáñez', 'email' => 'alicia.ibanez@gmail.com', 'newsletter' => false],
            ['name' => 'Gonzalo Soto', 'email' => 'gonzalo.soto@hotmail.com', 'newsletter' => true],
            ['name' => 'Claudia Bermejo', 'email' => 'claudia.bermejo@gmail.com', 'newsletter' => true],
            ['name' => 'Ángel Miranda', 'email' => 'angel.miranda@outlook.com', 'newsletter' => false],
            ['name' => 'Noelia Carrasco', 'email' => 'noelia.carrasco@gmail.com', 'newsletter' => true],
            ['name' => 'Rafael Lozano', 'email' => 'rafael.lozano@hotmail.es', 'newsletter' => true],
            ['name' => 'Diana Cortés', 'email' => 'diana.cortes@yahoo.es', 'newsletter' => false],
            ['name' => 'Emilio Pascual', 'email' => 'emilio.pascual@gmail.com', 'newsletter' => true],
            ['name' => 'Verónica Calvo', 'email' => 'veronica.calvo@outlook.com', 'newsletter' => true],
            ['name' => 'César Montero', 'email' => 'cesar.montero@gmail.com', 'newsletter' => false],
            ['name' => 'Lorena Velasco', 'email' => 'lorena.velasco@hotmail.es', 'newsletter' => true],
            ['name' => 'Nicolás Bravo', 'email' => 'nicolas.bravo@gmail.com', 'newsletter' => true],
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
                Logger::error('UserSeeder: error creating user', ['email' => $user['email'], 'exception' => $e->getMessage()]);
            }
        }

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
            'uuid' => $uuid,
            'name' => $name,
            'email' => $email,
            'password' => $hashedPass,
            'avatar' => $avatar,
            'prefs' => $preferences,
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
