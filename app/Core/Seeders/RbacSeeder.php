<?php

declare(strict_types=1);

namespace App\Core\Seeders;

use App\Core\Database;
use App\Core\Logger;
use App\Models\Permission;
use App\Models\Role;
use Exception;
use PDO;

/**
 * RbacSeeder
 *
 * Crea roles y permisos del sistema.
 * Se ejecuta una sola vez durante inicialización.
 */
final class RbacSeeder
{
    private PDO $db;

    private Role $roleModel;

    private Permission $permissionModel;

    public function __construct()
    {
        $this->db = Database::getConnection();
        $this->roleModel = new Role($this->db);
        $this->permissionModel = new Permission($this->db);
    }

    /**
     * Ejecuta el seeder.
     */
    public function run(): void
    {
        Logger::info('RbacSeeder: starting');

        // 1. Crear permisos
        $permissions = $this->createPermissions();

        // 2. Crear roles
        $roles = $this->createRoles();

        // 3. Asignar permisos a roles
        $this->assignPermissionsToRoles($roles, $permissions);

        // 4. Asignar roles a usuarios existentes (migración de legacy)
        $this->migrateExistingUsers();

        Logger::info('RbacSeeder: completed', ['roles' => \count($roles), 'permissions' => \count($permissions)]);
        echo "[RbacSeeder] RBAC inicializado correctamente\n";
    }

    /**
     * Crea permisos del sistema.
     *
     * @return array<string, int> Permisos creados
     */
    private function createPermissions(): array
    {
        $permissions = [
            // ────────────────────────────────────────
            // PERMISOS DE USUARIO
            // ────────────────────────────────────────
            'user.profile.view' => ['Ver perfil propio', 'user', 'view'],
            'user.profile.edit' => ['Editar perfil propio', 'user', 'edit'],
            'user.password.change' => ['Cambiar contraseña', 'user', 'change_password'],
            'user.sessions.view' => ['Ver sesiones activas', 'user', 'sessions_view'],
            'user.sessions.manage' => ['Gestionar sesiones', 'user', 'sessions_manage'],
            'user.security.view' => ['Ver historial de seguridad', 'user', 'security_view'],
            'user.email.verify' => ['Verificar email', 'user', 'email_verify'],
            'user.avatar.upload' => ['Subir avatar', 'user', 'avatar'],
            'user.favorites.manage' => ['Gestionar favoritos', 'user', 'favorites'],
            'user.cart.manage' => ['Gestionar carrito', 'user', 'cart'],

            // ────────────────────────────────────────
            // PERMISOS DE RESERVA
            // ────────────────────────────────────────
            'reservation.create' => ['Crear reserva', 'reservation', 'create'],
            'reservation.view_own' => ['Ver propias reservas', 'reservation', 'view_own'],
            'reservation.cancel' => ['Cancelar reserva', 'reservation', 'cancel'],
            'reservation.view_all' => ['Ver todas las reservas', 'reservation', 'view_all'],

            // ────────────────────────────────────────
            // PERMISOS DE RESEÑA
            // ────────────────────────────────────────
            'review.create' => ['Crear reseña', 'review', 'create'],
            'review.edit_own' => ['Editar propia reseña', 'review', 'edit_own'],
            'review.delete_own' => ['Eliminar propia reseña', 'review', 'delete_own'],
            'review.moderate' => ['Moderar reseñas', 'review', 'moderate'],

            // ────────────────────────────────────────
            // PERMISOS DE CAFÉ (Staff)
            // ────────────────────────────────────────
            'cafe.reception.checkin' => ['Hacer check-in de huéspedes', 'cafe', 'reception_checkin'],
            'cafe.reception.checkout' => ['Hacer check-out de huéspedes', 'cafe', 'reception_checkout'],
            'cafe.reception.view' => ['Ver panel de recepción', 'cafe', 'reception_view'],
            'cafe.kitchen.view' => ['Ver KDS (Kitchen Display)', 'cafe', 'kitchen_view'],
            'cafe.kitchen.mark_ready' => ['Marcar plato como listo', 'cafe', 'kitchen_mark_ready'],
            'cafe.animals.view' => ['Ver bienestar de animales', 'cafe', 'animals_view'],

            // ────────────────────────────────────────
            // PERMISOS DE BIENESTAR ANIMAL (Keeper)
            // ────────────────────────────────────────
            'keeper.dashboard.view' => ['Ver dashboard de bienestar', 'keeper', 'dashboard_view'],
            'keeper.log.create' => ['Registrar log de bienestar', 'keeper', 'log_create'],
            'keeper.animal.toggle' => ['Alternar disponibilidad de animal', 'keeper', 'animal_toggle'],
            'keeper.incident.log' => ['Registrar incidente', 'keeper', 'incident_log'],

            // ────────────────────────────────────────
            // PERMISOS DE GESTIÓN (Manager/Supervisor)
            // ────────────────────────────────────────
            'cafe.products.manage' => ['Gestionar productos/menú', 'cafe', 'products_manage'],
            'cafe.products.toggle' => ['Activar/desactivar productos', 'cafe', 'products_toggle'],
            'cafe.staff.view' => ['Ver personal', 'cafe', 'staff_view'],
            'cafe.staff.manage' => ['Gestionar personal', 'cafe', 'staff_manage'],
            'cafe.reports.view' => ['Ver reportes', 'cafe', 'reports_view'],

            // ────────────────────────────────────────
            // PERMISOS DE PRODUCTOS Y MENÚ
            // ────────────────────────────────────────
            'product.view' => ['Ver productos', 'product', 'view'],
            'product.create' => ['Crear producto', 'product', 'create'],
            'product.edit' => ['Editar producto', 'product', 'edit'],
            'product.delete' => ['Eliminar producto', 'product', 'delete'],
            'product.toggle' => ['Activar/desactivar producto', 'product', 'toggle'],

            // ────────────────────────────────────────
            // PERMISOS DE ADMINISTRACIÓN
            // ────────────────────────────────────────
            'admin.users.view' => ['Ver usuarios', 'admin', 'users_view'],
            'admin.users.create' => ['Crear usuarios', 'admin', 'users_create'],
            'admin.users.edit' => ['Editar usuarios', 'admin', 'users_edit'],
            'admin.users.delete' => ['Eliminar usuarios', 'admin', 'users_delete'],
            'admin.users.toggle_active' => ['Activar/desactivar usuarios', 'admin', 'users_toggle_active'],
            'admin.roles.view' => ['Ver roles', 'admin', 'roles_view'],
            'admin.roles.create' => ['Crear roles', 'admin', 'roles_create'],
            'admin.roles.edit' => ['Editar roles', 'admin', 'roles_edit'],
            'admin.roles.delete' => ['Eliminar roles', 'admin', 'roles_delete'],
            'admin.permissions.view' => ['Ver permisos', 'admin', 'permissions_view'],
            'admin.permissions.assign' => ['Asignar permisos', 'admin', 'permissions_assign'],
            'admin.cafes.view' => ['Ver cafés', 'admin', 'cafes_view'],
            'admin.cafes.create' => ['Crear café', 'admin', 'cafes_create'],
            'admin.cafes.edit' => ['Editar café', 'admin', 'cafes_edit'],
            'admin.cafes.delete' => ['Eliminar café', 'admin', 'cafes_delete'],
            'admin.audit.view' => ['Ver auditoría', 'admin', 'audit_view'],
        ];

        $created = [];

        foreach ($permissions as $key => [$name, $resource, $action]) {
            try {
                $existingPerm = $this->permissionModel->findByKey($key);

                if ($existingPerm) {
                    $created[$key] = $existingPerm['id'];
                } else {
                    $id = $this->permissionModel->create($key, $name, null, $resource, $action);
                    $created[$key] = $id;
                }
            } catch (Exception $e) {
                Logger::error('[RbacSeeder] Error creando permiso ' . $key, ['exception' => $e->getMessage()]);
            }
        }

        return $created;
    }

    /**
     * Crea roles canónicos del sistema según requisitos TFC DAW.
     *
     * Roles requeridos:
     * - Admin: Administrador del sistema
     * - Manager: Gerente del café (equivalente al antiguo "Techou")
     * - Supervisor: Supervisor de operaciones (equivalente al antiguo "Encargado")
     * - Reception: Personal de recepción
     * - Kitchen: Personal de cocina
     * - Keeper: Cuidador de animales
     * - User: Usuario regular/cliente
     *
     * @return array<string, int> Roles creados
     */
    private function createRoles(): array
    {
        $roles = [
            'admin' => ['Administrador', 'Control total del sistema y configuración global'],
            'manager' => ['Manager (Gerente)', 'Gestión completa del café: personal, menú, horarios, reservas y moderación'],
            'supervisor' => ['Supervisor', 'Supervisión de operaciones, reservas del día y asignación de mesas'],
            'reception' => ['Staff Recepción', 'Confirmar llegadas, asignar mesas y gestionar cola de espera'],
            'kitchen' => ['Staff Cocina', 'Ver comandas pendientes y marcar como en preparación/listas'],
            'keeper' => ['Keeper', 'Bienestar animal, salud y registro de incidentes'],
            'user' => ['Usuario', 'Cliente regular: reservas, reseñas, perfil y favoritos'],
        ];

        $created = [];

        foreach ($roles as $key => [$name, $description]) {
            try {
                $existingRole = $this->roleModel->findByKey($key);

                if ($existingRole) {
                    $created[$key] = $existingRole['id'];
                } else {
                    $id = $this->roleModel->create($key, $name, $description);
                    $created[$key] = $id;
                }
            } catch (Exception $e) {
                Logger::error('[RbacSeeder] Error creando rol ' . $key, ['exception' => $e->getMessage()]);
            }
        }

        return $created;
    }

    /**
     * Asigna permisos a roles.
     *
     * @param array<string, int> $roles
     * @param array<string, int> $permissions
     */
    private function assignPermissionsToRoles(array $roles, array $permissions): void
    {
        $rolePermissions = [
            'user' => [
                'user.profile.view',
                'user.profile.edit',
                'user.password.change',
                'user.sessions.view',
                'user.sessions.manage',
                'user.security.view',
                'user.email.verify',
                'user.avatar.upload',
                'user.favorites.manage',
                'user.cart.manage',
                'reservation.create',
                'reservation.view_own',
                'reservation.cancel',
                'review.create',
                'review.edit_own',
                'review.delete_own',
            ],
            'reception' => [
                'user.profile.view',
                'user.profile.edit',
                'user.password.change',
                'reservation.view_all',
                'cafe.reception.checkin',
                'cafe.reception.checkout',
                'cafe.reception.view',
                'review.moderate',
            ],
            'kitchen' => [
                'user.profile.view',
                'user.profile.edit',
                'user.password.change',
                'cafe.kitchen.view',
                'cafe.kitchen.mark_ready',
            ],
            'keeper' => [
                'user.profile.view',
                'user.profile.edit',
                'user.password.change',
                'keeper.dashboard.view',
                'keeper.log.create',
                'keeper.animal.toggle',
                'keeper.incident.log',
                'cafe.animals.view',
            ],
            'supervisor' => [
                'user.profile.view',
                'user.profile.edit',
                'user.password.change',
                'user.sessions.view',
                // Permisos de supervisión de reservas
                'reservation.view_all',
                'cafe.reception.checkin',
                'cafe.reception.checkout',
                'cafe.reception.view',
                // Supervisión de operaciones
                'cafe.kitchen.view',
                'cafe.animals.view',
                'cafe.staff.view',
                'cafe.reports.view',
                'keeper.dashboard.view',
                'review.moderate',
                // Productos - solo visualización
                'product.view',
            ],
            'manager' => [
                // Permisos básicos
                'user.profile.view',
                'user.profile.edit',
                'user.password.change',
                'user.sessions.view',
                // Gestión de personal del café
                'admin.users.view',
                'admin.users.edit',
                'cafe.staff.view',
                'cafe.staff.manage',
                // Gestión de menú y productos
                'cafe.products.manage',
                'cafe.products.toggle',
                'product.view',
                'product.create',
                'product.edit',
                'product.toggle',
                // Gestión de reservas
                'reservation.view_all',
                // Moderación de reseñas
                'review.moderate',
                // Dashboard y reportes completos
                'cafe.reports.view',
                'cafe.reception.view',
                'cafe.kitchen.view',
                'keeper.dashboard.view',
            ],
            'admin' => \array_keys($permissions), // Admin tiene todos los permisos
        ];

        foreach ($rolePermissions as $roleKey => $permKeys) {
            $roleId = $roles[$roleKey] ?? null;

            if ($roleId === null) {
                continue;
            }

            foreach ($permKeys as $permKey) {
                $permId = $permissions[$permKey] ?? null;

                if ($permId === null) {
                    continue;
                }

                try {
                    $this->roleModel->grantPermission($roleId, $permId);
                } catch (Exception $e) {
                    Logger::error('[RbacSeeder] Error asignando permiso: ' . $e->getMessage());
                }
            }
        }
    }

    /**
     * Migra usuarios existentes con role legacy a user_roles.
     */
    private function migrateExistingUsers(): void
    {
        // Obtener rol 'user' para asignar por defecto
        $userRole = $this->roleModel->findByKey('user');

        if (!$userRole) {
            Logger::error('[RbacSeeder] No se encontró rol "user"');

            return;
        }

        // Asignar rol 'user' a todos los usuarios sin rol asignado
        $sql = <<<'SQL'
            INSERT IGNORE INTO user_roles (user_id, role_id)
            SELECT id, :role_id FROM users
            WHERE id NOT IN (SELECT user_id FROM user_roles)
            SQL;

        $stmt = $this->db->prepare($sql);

        $stmt->execute(['role_id' => $userRole['id']]);

        $count = $stmt->rowCount();
        Logger::info('[RbacSeeder] Migraron ' . $count . ' usuarios al rol "user"');
    }
}
