<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\View;
use App\Domain\DTO\PaginationParams;
use App\Http\Transformers\UserTransformer;
use App\Models\Role;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\UserRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Random\RandomException;

/**
 * Controlador de Gestión de Usuarios
 *
 * Responsabilidad única: Vista SSR de lista de usuarios
 */
final class UserController
{
    private Role $roleModel;
    private UserRepositoryInterface $userRepo;
    private UserTransformer $userTransformer;

    public function __construct(?UserRepositoryInterface $userRepo = null, ?UserTransformer $userTransformer = null)
    {
        $this->roleModel = new Role();
        $this->userRepo = $userRepo ?? Container::make(UserRepositoryInterface::class);
        $this->userTransformer = $userTransformer ?? new UserTransformer();
    }

    /**
     * GET /admin/users
     * Lista paginada de usuarios con filtros server-side (HDA pattern).
     * @throws RandomException
     */
    public function index(ServerRequestInterface $request): ?ResponseInterface
    {
        $params = PaginationParams::fromRequest($request);
        $q      = $request->getQueryParams();
        $status = \trim((string) ($q['status'] ?? ''));
        $role   = \trim((string) ($q['role']   ?? ''));

        /** @var UserRepository $repo */
        $repo       = $this->userRepo;
        $pagination = $params->toPagination(20);
        $rawUsers   = $repo->findPaginatedWithRoles($pagination, $params->search, $status, $role, $params->sort, $params->dir);
        $hasNext    = $pagination->hasNextPage(\count($rawUsers));
        $users      = $this->userTransformer->collection(\array_slice($rawUsers, 0, $pagination->limit));
        $meta       = $pagination->toMeta($hasNext);

        $currentParams = $params->toQueryArray(['status' => $status, 'role' => $role]);

        View::render('admin/users/index', [
            'titulo'        => 'Gestión de Usuarios',
            'users'         => $users,
            'roles'         => $this->roleModel->all(),
            'stats'         => $repo->getUserStats(),
            'meta'          => $meta,
            'currentParams' => $currentParams,
            'csrf_token'    => Csrf::token(),
            'extraJs'       => ['admin/admin-users.js'],
        ], ['admin/admin-users.css'], 'backoffice');

        return null;
    }
}
