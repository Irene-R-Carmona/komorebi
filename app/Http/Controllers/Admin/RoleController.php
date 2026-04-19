<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Http\ResponseFactory;
use App\Core\View;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use App\Repositories\Contracts\RoleRepositoryInterface;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Random\RandomException;

/**
 * Controlador de Gestión de Roles y Permisos (RBAC)
 */
final class RoleController
{
    private RoleRepositoryInterface $roleRepo;
    private AuditLogRepositoryInterface $auditLogRepo;
    private ResponseFactory $response;

    public function __construct(
        ?RoleRepositoryInterface $roleRepo = null,
        ?AuditLogRepositoryInterface $auditLogRepo = null,
        ?ResponseFactory $response = null
    ) {
        $this->roleRepo     = $roleRepo     ?? Container::make(RoleRepositoryInterface::class);
        $this->auditLogRepo = $auditLogRepo ?? Container::make(AuditLogRepositoryInterface::class);
        $this->response     = $response     ?? new ResponseFactory();
    }

    /**
     * GET /admin/roles
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

        $roles          = $this->roleRepo->findAllWithCounts();
        $rolesWithPerms = $this->roleRepo->getAllWithPermissions();
        $rolePermissions = [];
        foreach ($rolesWithPerms as $r) {
            $rolePermissions[$r['id']] = \array_column($r['permissions'], 'id');
        }

        View::render('admin/roles/index', [
            'titulo'          => 'Gestión de Roles y Permisos',
            'roles'           => $roles,
            'permissions'     => $this->roleRepo->findAllPermissions(),
            'rolePermissions' => $rolePermissions,
            'csrf_token'      => Csrf::token(),
            'extraJs'         => ['admin/admin-roles.js'],
        ], ['admin/admin-roles.css'], 'backoffice');

        return null;
    }

    /**
     * GET /admin/roles (AJAX)
     * @throws JsonException
     */
    private function getRolesData(): ResponseInterface
    {
        $rolesWithPerms  = $this->roleRepo->getAllWithPermissions();
        $rolePermissions = [];
        foreach ($rolesWithPerms as $r) {
            $rolePermissions[$r['id']] = \array_column($r['permissions'], 'id');
        }

        return $this->response->json(['ok' => true, 'data' => [
            'roles'           => $this->roleRepo->findAllWithCounts(),
            'rolePermissions' => $rolePermissions,
        ]]);
    }

    /**
     * GET /admin/permissions
     * @throws JsonException
     */
    public function getPermissions(): ResponseInterface
    {
        return $this->response->json([
            'ok'   => true,
            'data' => ['permissions' => $this->roleRepo->findAllPermissions()],
        ]);
    }

    /**
     * GET /admin/roles/stats
     * @throws JsonException
     */
    public function rolesStats(): ResponseInterface
    {
        return $this->response->json([
            'ok'   => true,
            'data' => ['stats' => $this->roleRepo->getStats()],
        ]);
    }

    /**
     * POST /admin/roles/create
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

        if ($this->roleRepo->findByCode($data['code'])) {
            throw ValidationException::withMessage('Ya existe un rol con ese código', 422);
        }

        $roleId = $this->roleRepo->create($data['code'], $data['name'], $data['description'] ?? null);

        $this->auditLogRepo->log('create_role', 'role', $roleId, null, ['code' => $data['code'], 'name' => $data['name']]);

        return $this->response->json(['ok' => true, 'data' => [
            'message' => 'Rol creado correctamente',
            'role_id' => $roleId,
        ]], 201);
    }

    /**
     * POST /admin/roles/{roleId}/edit
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
        $role = $this->roleRepo->findById($roleId);

        if (!$role) {
            throw NotFoundException::forResource('Rol', $roleId);
        }

        if (\in_array($role['code'], ['admin', 'user'], true)) {
            throw ValidationException::withMessage('No se pueden editar roles del sistema', 403);
        }

        $this->roleRepo->update($roleId, $data['name'] ?? null, $data['description'] ?? null);

        $this->auditLogRepo->log('update_role', 'role', $roleId, null, ['name' => $data['name'], 'description' => $data['description']]);

        return $this->response->json(['ok' => true, 'data' => ['message' => 'Rol actualizado correctamente']]);
    }

    /**
     * POST /admin/roles/{roleId}/delete
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

        $role = $this->roleRepo->findById($roleId);
        if (!$role) {
            throw NotFoundException::forResource('Rol', $roleId);
        }

        if (\in_array($role['code'], ['admin', 'user'], true)) {
            throw ValidationException::withMessage('No se pueden eliminar roles del sistema', 403);
        }

        if ($this->roleRepo->countUsers($roleId) > 0) {
            throw ValidationException::withMessage('No se puede eliminar un rol que tiene usuarios asignados', 422);
        }

        $this->roleRepo->delete($roleId);

        $this->auditLogRepo->log('delete_role', 'role', $roleId, ['code' => $role['code'], 'name' => $role['name']], null);

        return $this->response->json(['ok' => true, 'data' => ['message' => 'Rol eliminado correctamente']]);
    }

    /**
     * POST /admin/roles/{roleId}/permissions/{permissionId}/grant
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

        $role       = $this->roleRepo->findById($roleId);
        $permission = $this->roleRepo->findPermissionById($permissionId);

        if (!$role || !$permission) {
            throw NotFoundException::forResource('Rol o permiso', $roleId . '/' . $permissionId);
        }

        $this->roleRepo->grantPermission($roleId, $permissionId);

        $this->auditLogRepo->log('grant_permission', 'role', $roleId, null, ['permission_id' => $permissionId, 'permission_code' => $permission['code']]);

        return $this->response->json(['ok' => true, 'data' => ['message' => 'Permiso asignado correctamente']]);
    }

    /**
     * POST /admin/roles/{roleId}/permissions/{permissionId}/revoke
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

        $role       = $this->roleRepo->findById($roleId);
        $permission = $this->roleRepo->findPermissionById($permissionId);

        if (!$role || !$permission) {
            throw NotFoundException::forResource('Rol o permiso', $roleId . '/' . $permissionId);
        }

        $this->roleRepo->revokePermission($roleId, $permissionId);

        $this->auditLogRepo->log('revoke_permission', 'role', $roleId, ['permission_id' => $permissionId, 'permission_code' => $permission['code']], null);

        return $this->response->json(['ok' => true, 'data' => ['message' => 'Permiso revocado correctamente']]);
    }
}
