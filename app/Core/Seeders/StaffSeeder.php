<?php

declare(strict_types=1);

namespace App\Core\Seeders;

use App\Core\Database;
use App\Core\Logger;
use Exception;
use PDO;
use Random\RandomException;

/**
 * StaffSeeder
 *
 * Crea usuarios de staff con sus respectivos roles via RBAC puro.
 */
final class StaffSeeder
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function run(): void
    {
        echo "Contratando plantilla...\n";
        Logger::info('StaffSeeder: starting');

        $defaultPass = \password_hash('komorebi2024', PASSWORD_ARGON2ID);

        // Obtener IDs de roles primero
        $roles = $this->getRoleIds();
        if (empty($roles)) {
            echo "No se encontraron roles en la BD. Abortando StaffSeeder.\n";
            Logger::warning('StaffSeeder: no roles found');

            return;
        }

        $staffData = [];

        // 1. ADMIN GLOBAL
        $staffData[] = [
            'cafe_id' => null,
            'name' => 'Admin System',
            'email' => 'admin@komorebi.cafe',
            'role_code' => 'admin',
        ];

        // 2. PLANTILLA POR SEDE
        $cafes = $this->db->query('SELECT id, name, slug FROM cafes')->fetchAll();

        foreach ($cafes as $cafe) {
            $cid = $cafe['id'];
            $slug = \str_replace('-', '.', $cafe['slug']);
            $name = $cafe['name'];

            // Manager (Gerente) - Cuenta profesional
            $staffData[] = [
                'cafe_id' => $cid,
                'name' => "Manager - {$name}",
                'email' => "manager.$slug@komorebi.jp",
                'role_code' => 'manager',
            ];

            // Supervisor (Encargado de operaciones)
            $staffData[] = [
                'cafe_id' => $cid,
                'name' => "Supervisor - {$name}",
                'email' => "supervisor.$slug@komorebi.jp",
                'role_code' => 'supervisor',
            ];

            // Staff Recepción
            $staffData[] = [
                'cafe_id' => $cid,
                'name' => "Reception - {$name}",
                'email' => "reception.$slug@komorebi.jp",
                'role_code' => 'reception',
            ];

            // Staff Cocina
            $staffData[] = [
                'cafe_id' => $cid,
                'name' => "Kitchen - {$name}",
                'email' => "kitchen.$slug@komorebi.jp",
                'role_code' => 'kitchen',
            ];

            // Keeper (Cuidador de animales)
            $staffData[] = [
                'cafe_id' => $cid,
                'name' => "Keeper - {$name}",
                'email' => "keeper.$slug@komorebi.jp",
                'role_code' => 'keeper',
            ];
        }

        // 3. USUARIO NORMAL (solo este tiene nombre de persona)
        $staffData[] = [
            'cafe_id' => null,
            'name' => 'Yuki Tanaka',
            'email' => 'yuki.tanaka@gmail.com',
            'role_code' => 'user',
        ];

        // Insertar cada usuario
        $count = 0;
        foreach ($staffData as $staff) {
            try {
                // Validar que el rol exista
                $roleCode = $staff['role_code'];
                if (!isset($roles[$roleCode])) {
                    echo "AVISO: Rol '$roleCode' no encontrado, saltando {$staff['email']}\n";
                    Logger::warning('StaffSeeder: role missing', ['role' => $roleCode, 'email' => $staff['email']]);
                    continue;
                }

                // Generar avatar por email y preferencias básicas
                $safeEmail = \preg_replace('/[^a-z0-9.\-@]/i', '_', $staff['email']);
                $avatar = '/images/staff/' . $safeEmail . '.jpg';
                $preferences = \json_encode([
                    'locale' => 'es-ES',
                    'phone' => null,
                    'preferred_cafe' => $staff['cafe_id'] ?? null,
                ], JSON_UNESCAPED_UNICODE);

                $this->createStaffUser(
                    $staff['cafe_id'],
                    $staff['name'],
                    $staff['email'],
                    $defaultPass,
                    $roles[$roleCode],
                    $avatar,
                    $preferences
                );
                $count++;
            } catch (Exception $e) {
                echo "Error creando {$staff['email']}: " . $e->getMessage() . "\n";
                Logger::error('StaffSeeder: error creating staff', ['email' => $staff['email'], 'exception' => $e->getMessage()]);
            }
        }

        echo "Plantilla generada (~$count usuarios).\n";
        Logger::info('StaffSeeder: completed', ['created' => $count]);
    }

    /**
     * Obtiene los IDs de roles por código.
     */
    private function getRoleIds(): array
    {
        try {
            // Usar códigos canónicos definidos en RbacSeeder
            $stmt = $this->db->query("SELECT id, code FROM roles WHERE code IN ('admin', 'manager', 'supervisor', 'reception', 'kitchen', 'keeper', 'user')");
            $roles = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $roles[$row['code']] = (int) $row['id'];
            }

            return $roles;
        } catch (Exception $e) {
            echo 'Error obteniendo roles: ' . $e->getMessage() . "\n";
            Logger::error('StaffSeeder: error getting roles', ['exception' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Crea un usuario de staff y le asigna un rol.
     * @param integer|null $cafeId
     * @param string       $name
     * @param string       $email
     * @param string       $hashedPass
     * @param integer      $roleId
     * @param string|null  $avatar
     * @param string|null  $preferences JSON
     * @throws RandomException
     */
    private function createStaffUser(?int $cafeId, string $name, string $email, string $hashedPass, int $roleId, ?string $avatar = null, ?string $preferences = null): void
    {
        // Verificar si ya existe
        $checkStmt = $this->db->prepare('SELECT id FROM users WHERE email = :email');
        $checkStmt->execute(['email' => $email]);
        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            // Usuario ya existe, solo asegurar rol
            $this->ensureUserRole((int) $existing['id'], $roleId);

            return;
        }

        // Crear usuario
        $uuid = $this->generateUuid();
        $insertStmt = $this->db->prepare(
            'INSERT INTO users (uuid, cafe_id, name, email, password, is_active, avatar, preferences, created_at) VALUES (:uuid, :cafe_id, :name, :email, :password, 1, :avatar, :prefs, NOW())'
        );

        $insertStmt->execute([
            'uuid' => $uuid,
            'cafe_id' => $cafeId,
            'name' => $name,
            'email' => $email,
            'password' => $hashedPass,
            'avatar' => $avatar,
            'prefs' => $preferences,
        ]);

        $userId = (int) $this->db->lastInsertId();

        // Asignar rol
        $this->ensureUserRole($userId, $roleId);
    }

    /**
     * Asigna un rol a un usuario si no lo tiene ya.
     */
    private function ensureUserRole(int $userId, int $roleId): void
    {
        try {
            // Verificar si ya tiene el rol
            $checkStmt = $this->db->prepare('SELECT 1 FROM user_roles WHERE user_id = :user_id AND role_id = :role_id');
            $checkStmt->execute(['user_id' => $userId, 'role_id' => $roleId]);

            if (!$checkStmt->fetch()) {
                // No existe, insertar
                $assignStmt = $this->db->prepare('
                    INSERT INTO user_roles (user_id, role_id, assigned_at)
                    VALUES (:user_id, :role_id, NOW())
                ');
                $assignStmt->execute(['user_id' => $userId, 'role_id' => $roleId]);
                Logger::debug('StaffSeeder: role assigned', ['user_id' => $userId, 'role_id' => $roleId]);
            }
        } catch (Exception $e) {
            // Si falla, log pero no fallar
            Logger::error("StaffSeeder: Error assigning role $roleId to user $userId", ['exception' => $e->getMessage()]);
        }
    }

    /**
     * Genera un UUID v4.
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
