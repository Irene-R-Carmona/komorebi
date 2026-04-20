<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Http\ResponseFactory;
use App\Core\View;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use JsonException;
use PDO;
use Psr\Http\Message\ResponseInterface;
use Random\RandomException;

/**
 * Controlador de Gestión de Roles y Permisos (RBAC)
 *
 * Responsabilidad única: Gestión completa del sistema RBAC
 */
final class RoleController
{
    private ResponseFactory $response;
    private Role $roleModel;
    private Permission $permissionModel;

    public function __construct(?ResponseFactory $response = null, ?Role $roleModel = null, ?Permission $permissionModel = null)
    {
        $this->response = $response ?? new ResponseFactory();
        $this->roleModel = $roleModel ?? new Role(Container::make(PDO::class));
        $this->permissionModel = $permissionModel ?? new Permission(Container::make(PDO::class));
    }

    /**
     * GET /admin/roles
     * Vista principal de gestión de roles
     * @throws JsonException
     * @throws RandomException
     */
    public function index(): ?ResponseInterface
    {
        $acceptHeader = $_SERVER['HTTP_ACCEPT'] ?? '';
        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && \strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || \str_contains($acceptHeader, 'application/json');

        if ($isAjax) {
            return $this->getRolesData();
        }

        // Obtener datos para renderizar vista
        $roles = $this->roleModel->findAllWithCounts();

        // Cargar permisos de todos los roles en una única consulta (evita N+1)
        $rolesWithPerms = $this->roleModel->getAllWithPermissions();
        $rolePermissions = [];
        foreach ($rolesWithPerms as $r) {
            $rolePermissions[$r['id']] = \array_column($r['permissions'], 'id');
        }

        $allPermissions = $this->permissionModel->all();

        View::render('admin/roles/index', [
            'titulo' => 'Gestión de Roles y Permisos',
            'roles' => $roles,
            'permissions' => $allPermissions,
            'rolePermissions' => $rolePermissions,
            'csrf_token' => Csrf::token(),
            'extraJs' => ['admin/admin-roles.js'],
        ], ['admin/admin-roles.css'], 'backoffice');

        return null;
    }

    /**
     * GET /admin/roles (AJAX)
     * Obtener datos de roles con permisos
     * @throws JsonException
     */
    private function getRolesData(): ResponseInterface
    {
        $roles = $this->roleModel->findAllWithCounts();

        // Cargar permisos de todos los roles en una única consulta (evita N+1)
        $rolesWithPerms = $this->roleModel->getAllWithPermissions();
        $rolePermissions = [];
        foreach ($rolesWithPerms as $r) {
            $rolePermissions[$r['id']] = \array_column($r['permissions'], 'id');
        }

        return $this->response->json(['ok' => true, 'data' => [
            'roles' => $roles,
            'rolePermissions' => $rolePermissions,
        ]]);
    }

    /**
     * GET /admin/permissions
     * Obtener lista de todos los permisos
     * @throws JsonException
     */
    public function getPermissions(): ResponseInterface
    {
        $permissions = $this->permissionModel->all();

        return $this->response->json(['ok' => true, 'data' => ['permissions' => $permissions]]);
    }

    /**
     * GET /admin/roles/stats
     * Obtener estadísticas de roles
     * @throws JsonException
     */
    public function rolesStats(): ResponseInterface
    {
        $stats = $this->roleModel->getStats();

        return $this->response->json(['ok' => true, 'data' => ['stats' => $stats]]);
    }

    /**
     * POST /admin/roles/create
     * Crear nuevo rol
     * @throws JsonException
     * @throws RandomException
     * @throws ValidationException
     */
    public function createRole(): ResponseInterface
    {
        if (!Csrf::validate()) {
            throw ValidationException::withMessage('Token de seguridad inválido', 419);
        }

        $data = \json_decode((string) \file_get_contents('php://input'), true);

        $errors = [];
        if (empty($data['code'])) {
            $errors['code'] = 'Código es requerido';
        }
        if (empty($data['name'])) {
            $errors['name'] = 'Nombre es requerido';
        }

        if (!empty($errors)) {
            throw ValidationException::fromArray($errors);
        }

        if (!\preg_match('/^[a-z_]+$/', $data['code'])) {
            throw ValidationException::withMessage('El código solo puede contener letras minúsculas y guiones bajos', 422);
        }

        if ($this->roleModel->findByKey($data['code'])) {
            throw ValidationException::withMessage('Ya existe un rol con ese código', 422);
        }

        $roleId = $this->roleModel->create(
            $data['code'],
            $data['name'],
            $data['description'] ?? null
        );

        AuditLog::log('create_role', 'role', $roleId, null, ['code' => $data['code'], 'name' => $data['name']]);

        return $this->response->json(['ok' => true, 'data' => [
            'message' => 'Rol creado correctamente',
            'role_id' => $roleId,
        ]], 201);
    }

    /**
     * POST /admin/roles/{roleId}/edit
     * Actualizar rol existente
     * @param integer $roleId
     * @throws JsonException
     * @throws NotFoundException
     * @throws RandomException
     * @throws ValidationException
     */
    public function updateRole(int $roleId): ResponseInterface
    {
        if (!Csrf::validate()) {
            throw ValidationException::withMessage('Token de seguridad inválido', 419);
        }

        $data = \json_decode((string) \file_get_contents('php://input'), true);

        $role = $this->roleModel->findById($roleId);
        if (!$role) {
            throw NotFoundException::forResource('Rol', $roleId);
        }

        if (\in_array($role['code'], ['admin', 'user'], true)) {
            throw ValidationException::withMessage('No se pueden editar roles del sistema', 403);
        }

        $this->roleModel->update($roleId, $data['name'] ?? null, $data['description'] ?? null);

        AuditLog::log('update_role', 'role', $roleId, null, ['name' => $data['name'], 'description' => $data['description']]);

        return $this->response->json(['ok' => true, 'data' => ['message' => 'Rol actualizado correctamente']]);
    }

    /**
     * POST /admin/roles/{roleId}/delete
     * Eliminar rol
     * @param integer $roleId
     * @throws JsonException
     * @throws RandomException
     * @throws ValidationException
     * @throws NotFoundException
     */
    public function deleteRole(int $roleId): ResponseInterface
    {
        if (!Csrf::validate()) {
            throw ValidationException::withMessage('Token de seguridad inválido', 419);
        }

        $role = $this->roleModel->findById($roleId);
        if (!$role) {
            throw NotFoundException::forResource('Rol', $roleId);
        }

        if (\in_array($role['code'], ['admin', 'user'], true)) {
            throw ValidationException::withMessage('No se pueden eliminar roles del sistema', 403);
        }

        if ($this->roleModel->countUsers($roleId) > 0) {
            throw ValidationException::withMessage('No se puede eliminar un rol que tiene usuarios asignados', 422);
        }

        $this->roleModel->delete($roleId);

        AuditLog::log('delete_role', 'role', $roleId, ['code' => $role['code'], 'name' => $role['name']], null);

        return $this->response->json(['ok' => true, 'data' => ['message' => 'Rol eliminado correctamente']]);
    }

    /**
     * POST /admin/roles/{roleId}/permissions/{permissionId}/grant
     * Asignar permiso a rol
     * @param integer $roleId
     * @param integer $permissionId
     * @throws JsonException
     * @throws NotFoundException
     * @throws RandomException
     * @throws ValidationException
     */
    public function grantPermission(int $roleId, int $permissionId): ResponseInterface
    {
        if (!Csrf::validate()) {
            throw ValidationException::withMessage('Token de seguridad inválido', 419);
        }

        $role = $this->roleModel->findById($roleId);
        $permission = $this->permissionModel->findById($permissionId);

        if (!$role || !$permission) {
            throw NotFoundException::forResource('Rol o permiso', $roleId . '/' . $permissionId);
        }

        $this->roleModel->grantPermission($roleId, $permissionId);

        AuditLog::log('grant_permission', 'role', $roleId, null, ['permission_id' => $permissionId, 'permission_code' => $permission['code']]);

        return $this->response->json(['ok' => true, 'data' => ['message' => 'Permiso asignado correctamente']]);
    }

    /**
     * POST /admin/roles/{roleId}/permissions/{permissionId}/revoke
     * Revocar permiso de rol
     * @param integer $roleId
     * @param integer $permissionId
     * @throws JsonException
     * @throws NotFoundException
     * @throws RandomException
     * @throws ValidationException
     */
    public function revokePermission(int $roleId, int $permissionId): ResponseInterface
    {
        if (!Csrf::validate()) {
            throw ValidationException::withMessage('Token de seguridad inválido', 419);
        }

        $role = $this->roleModel->findById($roleId);
        $permission = $this->permissionModel->findById($permissionId);

        if (!$role || !$permission) {
            throw NotFoundException::forResource('Rol o permiso', $roleId . '/' . $permissionId);
        }

        $this->roleModel->revokePermission($roleId, $permissionId);

        AuditLog::log('revoke_permission', 'role', $roleId, ['permission_id' => $permissionId, 'permission_code' => $permission['code']], null);

        return $this->response->json(['ok' => true, 'data' => ['message' => 'Permiso revocado correctamente']]);
    }
}
