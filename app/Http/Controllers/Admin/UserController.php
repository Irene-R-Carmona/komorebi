<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Http\ResponseFactory;
use App\Core\Result;
use App\Core\View;
use App\Exceptions\ValidationException;
use App\Http\Transformers\UserTransformer;
use App\Models\AuditLog;
use App\Models\Role;
use App\Repositories\UserRepository;
use App\Services\UserManagementService;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
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
    private UserTransformer $userTransformer;

    private const CSRF_INVALID = 'Token de seguridad inválido';

    public function __construct(?UserManagementService $userManagementService = null, ?UserRepository $userRepo = null, ?ResponseFactory $response = null, ?UserTransformer $userTransformer = null)
    {
        $this->roleModel = new Role();
        $this->userManagementService = $userManagementService ?? new UserManagementService();
        $this->userRepo = $userRepo ?? new UserRepository();
        $this->response = $response ?? new ResponseFactory();
        $this->userTransformer = $userTransformer ?? new UserTransformer();
    }

    /**
     * GET /admin/usuarios
     * Lista de usuarios con sus roles
     * @throws RandomException
     */
    public function index(): ?ResponseInterface
    {
        // Obtener usuarios con roles desde el servicio
        $rawUsers = $this->userManagementService->getUsersWithRoles();
        $roles = $this->roleModel->all();

        // Calcular estadísticas desde datos crudos (antes de transformar)
        $stats = [
            'total_users' => \count($rawUsers),
            'active_users' => \count(\array_filter($rawUsers, static fn($u) => !empty($u['is_active']))),
            'admin_users' => \count(\array_filter($rawUsers, static fn($u) => \stripos($u['roles'] ?? '', 'admin') !== false)),
            'inactive_users' => \count(\array_filter($rawUsers, static fn($u) => empty($u['is_active']))),
        ];

        View::render('admin/users/index', [
            'titulo' => 'Gestión de Usuarios',
            'users' => $this->userTransformer->collection($rawUsers),
            'roles' => $roles,
            'stats' => $stats,
            'csrf_token' => Csrf::token(),
            // Pasar extraJs para que el layout lo incluya
            'extraJs' => ['admin/admin-users.js'],
        ], ['admin/admin-users.css'], 'backoffice');
        return null;
    }

    /**
     * GET /admin/usuarios/list
     * Lista simplificada de usuarios para dropdowns/filtros
     * @throws JsonException
     */
    public function getUsersList(ServerRequestInterface $request): ResponseInterface
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
    public function create(ServerRequestInterface $request): ResponseInterface
    {
        if (!Csrf::validate()) {
            throw ValidationException::withMessage(self::CSRF_INVALID, 419);
        }

        $body = (array) $request->getParsedBody();
        // Preparar datos para el servicio
        $data = [
            'name' => isset($body['name']) ? \trim($body['name']) : '',
            'email' => isset($body['email']) ? \trim($body['email']) : '',
            'password' => $body['password'] ?? '',
            'role_id' => isset($body['role_id']) ? (int) $body['role_id'] : 2,
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
    public function update(ServerRequestInterface $request, int $userId): ResponseInterface
    {
        if (!Csrf::validate()) {
            throw ValidationException::withMessage(self::CSRF_INVALID, 419);
        }

        $body = (array) $request->getParsedBody();
        // Preparar datos para el servicio
        $data = [
            'name' => isset($body['name']) ? \trim($body['name']) : '',
            'email' => isset($body['email']) ? \trim($body['email']) : '',
            'role_id' => isset($body['role_id']) ? (int) $body['role_id'] : null,
        ];

        // Añadir password solo si se proporciona
        if (!empty($body['password'])) {
            $data['password'] = $body['password'];
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
            throw ValidationException::withMessage(self::CSRF_INVALID, 419);
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
            throw ValidationException::withMessage(self::CSRF_INVALID, 419);
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
