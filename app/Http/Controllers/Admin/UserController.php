<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Http\ResponseFactory;
use App\Core\View;
use App\Domain\DTO\PaginationParams;
use App\Http\Transformers\UserTransformer;
use App\Repositories\Contracts\RoleRepositoryInterface;
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
    private RoleRepositoryInterface $roleRepo;
    private UserRepositoryInterface $userRepo;
    private UserTransformer $userTransformer;
    private ResponseFactory $response;

    public function __construct(
        ?UserRepositoryInterface $userRepo = null,
        ?UserTransformer $userTransformer = null,
        ?RoleRepositoryInterface $roleRepo = null,
        ?ResponseFactory $response = null,
    ) {
        $this->roleRepo = $roleRepo ?? Container::make(RoleRepositoryInterface::class);
        $this->userRepo = $userRepo ?? Container::make(UserRepositoryInterface::class);
        $this->userTransformer = $userTransformer ?? new UserTransformer();
        $this->response = $response ?? Container::make(ResponseFactory::class);
    }

    /**
     * GET /admin/users
     * Lista paginada de usuarios con filtros server-side (HDA pattern).
     * @throws RandomException
     */
    public function index(ServerRequestInterface $request): ?ResponseInterface
    {
        $params = PaginationParams::fromRequest($request);
        $q = $request->getQueryParams();
        $status = \trim((string) ($q['status'] ?? ''));
        $role = \trim((string) ($q['role'] ?? ''));

        /** @var UserRepository $repo */
        $repo = $this->userRepo;
        $pagination = $params->toPagination(20);
        $rawUsers = $repo->findPaginatedWithRoles($pagination, $params->search, $status, $role, $params->sort, $params->dir);
        $hasNext = $pagination->hasNextPage(\count($rawUsers));
        $users = $this->userTransformer->collection(\array_slice($rawUsers, 0, $pagination->limit));
        $meta = $pagination->toMeta($hasNext);

        $currentParams = $params->toQueryArray(['status' => $status, 'role' => $role]);

        View::render('admin/users/index', [
            'titulo' => 'Gestión de Usuarios',
            'users' => $users,
            'roles' => $this->roleRepo->findAllWithCounts(),
            'stats' => $repo->getUserStats(),
            'meta' => $meta,
            'currentParams' => $currentParams,
            'csrf_token' => Csrf::token(),
            'extraJs' => ['admin/admin-users.js'],
        ], ['admin/admin-users.css'], 'backoffice');

        return null;
    }

    /**
     * GET /admin/users/create
     * Formulario de creación de usuario.
     * @throws RandomException
     */
    public function create(): ?ResponseInterface
    {
        View::render('admin/users/create', [
            'titulo' => 'Nuevo Usuario | Komorebi Admin',
            'roles' => $this->roleRepo->findAllWithCounts(),
            'csrf_token' => Csrf::token(),
        ], [], 'backoffice');

        return null;
    }

    /**
     * GET /admin/users/{id}/edit
     * Formulario de edición de usuario.
     * @throws RandomException
     */
    public function edit(ServerRequestInterface $request): ?ResponseInterface
    {
        $id = (int) $request->getAttribute('id');
        if ($id <= 0) {
            return $this->response->redirect('/admin/users');
        }

        $user = $this->userRepo->findById($id);
        if ($user === null) {
            return $this->response->redirect('/admin/users');
        }

        $roles = $this->roleRepo->findAllWithCounts();
        $userRoles = $this->userRepo->getRoles($id);

        $currentRoleId = null;
        if ($userRoles !== []) {
            $currentSlug = $userRoles[0]['slug'] ?? null;
            foreach ($roles as $role) {
                if ($role['code'] === $currentSlug) {
                    $currentRoleId = (int) $role['id'];
                    break;
                }
            }
        }

        View::render('admin/users/edit', [
            'titulo' => 'Editar Usuario | Komorebi Admin',
            'user' => $user->toViewArray(),
            'current_role_id' => $currentRoleId,
            'roles' => $roles,
            'csrf_token' => Csrf::token(),
        ], [], 'backoffice');

        return null;
    }
}
