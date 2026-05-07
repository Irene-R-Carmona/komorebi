<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Core\Http\ResponseFactory;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Http\Controllers\Api\AbstractApiController;
use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Random\RandomException;

/**
 * API REST: Gestión de roles y permisos RBAC (Admin)
 *
 * Rutas:
 * - POST   /api/v1/admin/roles               → createRole()
 * - PUT    /api/v1/admin/roles/{id}          → updateRole()
 * - DELETE /api/v1/admin/roles/{id}          → deleteRole()
 * - POST   /api/v1/admin/roles/{id}/permissions/{pid}/grant  → grantPermission()
 * - POST   /api/v1/admin/roles/{id}/permissions/{pid}/revoke → revokePermission()
 */
final class RoleApiController extends AbstractApiController
{
    public function __construct(
        ResponseFactory $response,
        private readonly Role $roleModel,
        private readonly Permission $permissionModel,
    ) {
        parent::__construct($response);
    }

    /**
     * POST /api/v1/admin/roles → 201
     *
     * @throws JsonException
     * @throws RandomException
     * @throws ValidationException
     */
    public function createRole(ServerRequestInterface $request): ResponseInterface
    {
        $data = (array) ($request->getParsedBody() ?? []);

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

        if (!\preg_match('/^[a-z_]+$/', (string) $data['code'])) {
            throw ValidationException::withMessage('El código solo puede contener letras minúsculas y guiones bajos', 422);
        }

        if ($this->roleModel->findByKey((string) $data['code'])) {
            throw ValidationException::withMessage('Ya existe un rol con ese código', 422);
        }

        $roleId = $this->roleModel->create(
            (string) $data['code'],
            (string) $data['name'],
            isset($data['description']) ? (string) $data['description'] : null,
        );

        AuditLog::log('create_role', 'role', $roleId, null, ['code' => $data['code'], 'name' => $data['name']]);

        return $this->created([
            'message' => 'Rol creado correctamente',
            'role_id' => $roleId,
        ]);
    }

    /**
     * PUT /api/v1/admin/roles/{id} → 200
     *
     * @throws JsonException
     * @throws NotFoundException
     * @throws RandomException
     * @throws ValidationException
     */
    public function updateRole(ServerRequestInterface $request, int $id): ResponseInterface
    {
        $data = (array) ($request->getParsedBody() ?? []);

        $role = $this->roleModel->findById($id);
        if (!$role) {
            throw NotFoundException::forResource('Rol', $id);
        }

        if (\in_array($role['code'], ['admin', 'user'], true)) {
            throw ValidationException::withMessage('No se pueden editar roles del sistema', 403);
        }

        $this->roleModel->update(
            $id,
            isset($data['name']) ? (string) $data['name'] : null,
            isset($data['description']) ? (string) $data['description'] : null,
        );

        AuditLog::log('update_role', 'role', $id, null, ['name' => $data['name'] ?? null, 'description' => $data['description'] ?? null]);

        return $this->success(['message' => 'Rol actualizado correctamente']);
    }

    /**
     * DELETE /api/v1/admin/roles/{id} → 200
     *
     * @throws JsonException
     * @throws NotFoundException
     * @throws RandomException
     * @throws ValidationException
     */
    public function deleteRole(int $id): ResponseInterface
    {
        $role = $this->roleModel->findById($id);
        if (!$role) {
            throw NotFoundException::forResource('Rol', $id);
        }

        if (\in_array($role['code'], ['admin', 'user'], true)) {
            throw ValidationException::withMessage('No se pueden eliminar roles del sistema', 403);
        }

        if ($this->roleModel->countUsers($id) > 0) {
            throw ValidationException::withMessage('No se puede eliminar un rol que tiene usuarios asignados', 422);
        }

        $this->roleModel->delete($id);

        AuditLog::log('delete_role', 'role', $id, ['code' => $role['code'], 'name' => $role['name']], null);

        return $this->success(['message' => 'Rol eliminado correctamente']);
    }

    /**
     * POST /api/v1/admin/roles/{id}/permissions/{permissionId}/grant → 200
     *
     * @throws JsonException
     * @throws NotFoundException
     * @throws RandomException
     * @throws ValidationException
     */
    public function grantPermission(int $id, int $permissionId): ResponseInterface
    {
        $role = $this->roleModel->findById($id);
        $permission = $this->permissionModel->findById($permissionId);

        if (!$role || !$permission) {
            throw NotFoundException::forResource('Rol o permiso', $id . '/' . $permissionId);
        }

        $this->roleModel->grantPermission($id, $permissionId);

        AuditLog::log('grant_permission', 'role', $id, null, ['permission_id' => $permissionId, 'permission_code' => $permission['code']]);

        return $this->success(['message' => 'Permiso asignado correctamente']);
    }

    /**
     * POST /api/v1/admin/roles/{id}/permissions/{permissionId}/revoke → 200
     *
     * @throws JsonException
     * @throws NotFoundException
     * @throws RandomException
     * @throws ValidationException
     */
    public function revokePermission(int $id, int $permissionId): ResponseInterface
    {
        $role = $this->roleModel->findById($id);
        $permission = $this->permissionModel->findById($permissionId);

        if (!$role || !$permission) {
            throw NotFoundException::forResource('Rol o permiso', $id . '/' . $permissionId);
        }

        $this->roleModel->revokePermission($id, $permissionId);

        AuditLog::log('revoke_permission', 'role', $id, ['permission_id' => $permissionId, 'permission_code' => $permission['code']], null);

        return $this->success(['message' => 'Permiso revocado correctamente']);
    }
}
