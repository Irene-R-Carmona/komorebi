<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Core\Http\ResponseFactory;
use App\Exceptions\ValidationException;
use App\Http\Controllers\Api\AbstractApiController;
use App\Models\AuditLog;
use App\Services\Contracts\UserManagementServiceInterface;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Random\RandomException;

/**
 * API REST: Gestión de usuarios (Admin)
 *
 * Rutas:
 * - GET    /api/v1/admin/users          → list()
 * - POST   /api/v1/admin/users          → create()
 * - PUT    /api/v1/admin/users/{id}     → update()
 * - DELETE /api/v1/admin/users/{id}     → delete()
 * - PATCH  /api/v1/admin/users/{id}/status → toggleActive()
 */
final class UserApiController extends AbstractApiController
{
    public function __construct(
        ResponseFactory $response,
        private readonly UserManagementServiceInterface $userManagementService,
    ) {
        parent::__construct($response);
    }

    /**
     * GET /api/v1/admin/users → 200
     *
     * @throws JsonException
     */
    public function list(): ResponseInterface
    {
        $users = $this->userManagementService->getUsersWithRoles();

        return $this->success(['users' => $users]);
    }

    /**
     * POST /api/v1/admin/users → 201
     *
     * @throws JsonException
     * @throws RandomException
     * @throws ValidationException
     */
    public function create(ServerRequestInterface $request): ResponseInterface
    {
        $body = (array) ($request->getParsedBody() ?? []);
        $data = [
            'name' => isset($body['name']) ? \trim($body['name']) : '',
            'email' => isset($body['email']) ? \trim($body['email']) : '',
            'password' => $body['password'] ?? '',
            'role_id' => isset($body['role_id']) ? (int) $body['role_id'] : 2,
        ];

        $result = $this->userManagementService->createUser($data);

        if ($result->ok) {
            AuditLog::log(
                'create_user',
                'user',
                \is_array($result->data) ? ($result->data['id'] ?? null) : null,
                null,
                ['name' => $data['name'], 'email' => $data['email'], 'role_id' => $data['role_id']]
            );

            return $this->created([
                'message' => 'Usuario creado exitosamente',
                'user_id' => \is_array($result->data) ? ($result->data['id'] ?? null) : null,
            ]);
        }

        $errors = \is_array($result->data) ? $result->data : [];
        if ($errors !== []) {
            throw ValidationException::fromArray($errors);
        }

        return $this->unprocessable($result->error ?? 'Error al crear usuario');
    }

    /**
     * PUT /api/v1/admin/users/{id} → 200
     *
     * @throws JsonException
     * @throws RandomException
     * @throws ValidationException
     */
    public function update(ServerRequestInterface $request, int $id): ResponseInterface
    {
        $body = (array) ($request->getParsedBody() ?? []);
        $data = [
            'name' => isset($body['name']) ? \trim($body['name']) : '',
            'email' => isset($body['email']) ? \trim($body['email']) : '',
            'role_id' => isset($body['role_id']) ? (int) $body['role_id'] : null,
        ];

        if (!empty($body['password'])) {
            $data['password'] = $body['password'];
        }

        $result = $this->userManagementService->updateUser($id, $data);

        if ($result->ok) {
            AuditLog::log(
                'update_user',
                'user',
                $id,
                null,
                \array_filter($data, static fn ($v) => $v !== null)
            );

            return $this->success(['message' => 'Usuario actualizado exitosamente']);
        }

        $errors = \is_array($result->data) ? $result->data : [];
        if ($errors !== []) {
            throw ValidationException::fromArray($errors);
        }

        return $this->unprocessable($result->error ?? 'Error al actualizar usuario');
    }

    /**
     * DELETE /api/v1/admin/users/{id} → 200
     *
     * @throws JsonException
     * @throws RandomException
     */
    public function delete(int $id): ResponseInterface
    {
        $result = $this->userManagementService->deactivateUser($id);

        if ($result->ok) {
            AuditLog::log('delete_user', 'user', $id);

            return $this->success(['message' => 'Usuario eliminado exitosamente']);
        }

        return $this->unprocessable($result->error ?? 'Error al eliminar usuario');
    }

    /**
     * PATCH /api/v1/admin/users/{id}/status → 200
     *
     * @throws JsonException
     * @throws RandomException
     */
    public function toggleActive(int $id): ResponseInterface
    {
        $result = $this->userManagementService->toggleUserStatus($id);

        if ($result->ok) {
            AuditLog::log('toggle_user_active', 'user', $id);

            return $this->success($result->data);
        }

        return $this->unprocessable($result->error ?? 'Error al cambiar estado');
    }
}
