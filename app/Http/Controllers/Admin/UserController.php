<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Http\ResponseFactory;
use App\Core\Result;
use App\Core\View;
use App\Exceptions\ValidationException;
use App\Models\AuditLog;
use App\Models\Role;
use App\Repositories\UserRepository;
use App\Services\UserManagementService;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Random\RandomException;

/**
 * Controlador de Gestión de Usuarios
 *
 * Responsabilidad única: CRUD completo de usuarios del sistema
 *
 * Métodos:
 * - index() - Lista de usuarios
 * - getUsersList() - API para dropdowns
 * - createUser() - Crear nuevo usuario
 * - updateUser() - Actualizar usuario
 * - deleteUser() - Eliminar usuario
 * - toggleUserActive() - Activar/desactivar
 */
final class UserController
{
    private Role $roleModel;
    private UserManagementService $userManagementService;
    private UserRepository $userRepo;
    private ResponseFactory $response;

    public function __construct(?UserManagementService $userManagementService = null, ?UserRepository $userRepo = null, ?ResponseFactory $response = null)
    {
        $this->roleModel = new Role();
        $this->userManagementService = $userManagementService ?? new UserManagementService();
        $this->userRepo = $userRepo ?? new UserRepository();
        $this->response = $response ?? new ResponseFactory();
    }

    /**
     * GET /admin/usuarios
     * Lista de usuarios con sus roles
     * @throws RandomException
     */
    public function index(): void
    {
        // Obtener usuarios con roles desde el servicio
        $users = $this->userManagementService->getUsersWithRoles();
        $roles = $this->roleModel->all();

        // Calcular estadísticas para las stat-cards
        $stats = [
            'total_users' => \count($users),
            'active_users' => \count(\array_filter($users, static fn($u) => !empty($u['is_active']))),
            'admin_users' => \count(\array_filter($users, static fn($u) => \stripos($u['roles'] ?? '', 'admin') !== false)),
            'inactive_users' => \count(\array_filter($users, static fn($u) => empty($u['is_active']))),
        ];

        View::render('admin/users/index', [
            'titulo' => 'Gestión de Usuarios',
            'users' => $users,
            'roles' => $roles,
            'stats' => $stats,
            'csrf_token' => Csrf::token(),
            // Pasar extraJs para que el layout lo incluya
            'extraJs' => ['admin/admin-users.js'],
        ], ['admin/admin-users.css'], 'backoffice');
    }

    /**
     * GET /admin/usuarios/list
     * Lista simplificada de usuarios para dropdowns/filtros
     * @throws JsonException
     */
    public function getUsersList(): ResponseInterface
    {
        $users = $this->userRepo->getActiveUsersList();
        return $this->response->json(['ok' => true, 'data' => ['users' => $users]]);
    }

    /**
     * POST /admin/usuarios/create
     * Crear nuevo usuario
     * @throws JsonException
     * @throws RandomException
     */
    public function create(): ResponseInterface
    {
        if (!Csrf::validate()) {
            throw ValidationException::withMessage('Token de seguridad inválido', 419);
        }

        // Preparar datos para el servicio
        $data = [
            'name' => isset($_POST['name']) ? \trim($_POST['name']) : '',
            'email' => isset($_POST['email']) ? \trim($_POST['email']) : '',
            'password' => $_POST['password'] ?? '',
            'role_id' => isset($_POST['role_id']) ? (int) $_POST['role_id'] : 2,
        ];

        // Delegar al servicio
        $result = $this->userManagementService->createUser($data);

        if ($result->ok) {
            AuditLog::log(
                'create_user',
                'user',
                \is_array($result->data) ? ($result->data['id'] ?? null) : null,
                null,
                ['name' => $data['name'], 'email' => $data['email'], 'role_id' => $data['role_id']]
            );

            return $this->response->json(['ok' => true, 'data' => [
                'message' => 'Usuario creado exitosamente',
                'user_id' => \is_array($result->data) ? ($result->data['id'] ?? null) : null,
            ]], 201);
        }

        $errors = \is_array($result->data) ? $result->data : [];
        if (!empty($errors)) {
            throw ValidationException::fromArray($errors);
        }
        return $this->response->problem(Result::fail($result->getMessage('Error al crear usuario'), 'validation'), 422);
    }

    /**
     * POST /admin/usuarios/{userId}/edit
     * Actualizar usuario existente
     * @param integer $userId
     * @throws JsonException
     * @throws RandomException
     */
    public function update(int $userId): ResponseInterface
    {
        if (!Csrf::validate()) {
            throw ValidationException::withMessage('Token de seguridad inválido', 419);
        }

        // Preparar datos para el servicio
        $data = [
            'name' => isset($_POST['name']) ? \trim($_POST['name']) : '',
            'email' => isset($_POST['email']) ? \trim($_POST['email']) : '',
            'role_id' => isset($_POST['role_id']) ? (int) $_POST['role_id'] : null,
        ];

        // Añadir password solo si se proporciona
        if (!empty($_POST['password'])) {
            $data['password'] = $_POST['password'];
        }

        // Delegar al servicio
        $result = $this->userManagementService->updateUser($userId, $data);

        if ($result->ok) {
            AuditLog::log(
                'update_user',
                'user',
                $userId,
                null,
                \array_filter($data, static fn($v) => $v !== null)
            );

            return $this->response->json(['ok' => true, 'data' => ['message' => 'Usuario actualizado exitosamente']]);
        }

        $errors = \is_array($result->data) ? $result->data : [];
        if (!empty($errors)) {
            throw ValidationException::fromArray($errors);
        }
        return $this->response->problem(Result::fail($result->getMessage('Error al actualizar usuario'), 'validation'), 422);
    }

    /**
     * POST /admin/usuarios/{userId}/delete
     * Eliminar (desactivar) usuario
     * @param integer $userId
     * @throws JsonException
     * @throws RandomException
     */
    public function delete(int $userId): ResponseInterface
    {
        if (!Csrf::validate()) {
            throw ValidationException::withMessage('Token de seguridad inválido', 419);
        }

        // Delegar al servicio
        $result = $this->userManagementService->deactivateUser($userId);

        if ($result->ok) {
            AuditLog::log(
                'delete_user',
                'user',
                $userId
            );

            return $this->response->json(['ok' => true, 'data' => ['message' => 'Usuario eliminado exitosamente']]);
        }

        return $this->response->problem(Result::fail($result->getMessage('Error al eliminar usuario'), 'validation'), 422);
    }

    /**
     * POST /admin/usuarios/{userId}/toggle-active
     * Activar/desactivar usuario
     * @param integer $userId
     * @throws JsonException
     * @throws RandomException
     */
    public function toggleActive(int $userId): ResponseInterface
    {
        if (!Csrf::validate()) {
            throw ValidationException::withMessage('Token de seguridad inválido', 419);
        }

        // Delegar al servicio
        $result = $this->userManagementService->toggleUserStatus($userId);

        if ($result->ok) {
            AuditLog::log(
                'toggle_user_active',
                'user',
                $userId
            );

            return $this->response->json(['ok' => true, 'data' => $result->data]);
        }

        return $this->response->problem(Result::fail($result->getMessage('Error al cambiar estado'), 'validation'), 422);
    }
}
