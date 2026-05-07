<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\View;
use App\Repositories\Contracts\NewsletterSubscriptionRepositoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Random\RandomException;

/**
 * Controlador de Newsletter (Admin) — SSR únicamente.
 * Las mutaciones (delete, export) están en Api\V1\Admin\NewsletterApiController.
 */
final class NewsletterController
{
    private NewsletterSubscriptionRepositoryInterface $repo;

    public function __construct(?NewsletterSubscriptionRepositoryInterface $repo = null)
    {
        $this->repo = $repo ?? Container::make(NewsletterSubscriptionRepositoryInterface::class);
    }

    /**
     * GET /admin/newsletter
     * @throws RandomException
     */
    public function index(ServerRequestInterface $request): ?ResponseInterface
    {
        $queryParams = $request->getQueryParams();
        $page = \max(1, (int) ($queryParams['page'] ?? 1));
        $perPage = 25;

        $filters = \array_filter([
            'email' => $queryParams['email'] ?? null,
            'status' => $queryParams['status'] ?? null,
        ], static fn ($v) => $v !== null && $v !== '');

        $stats = $this->repo->getAdminStats();
        $paginated = $this->repo->getAllPaginated($page, $perPage, $filters);

        View::render('admin/newsletter/index', [
            'titulo' => 'Newsletter',
            'csrf_token' => Csrf::token(),
            'stats' => $stats,
            'items' => $paginated['items'],
            'total' => $paginated['total'],
            'page' => $paginated['page'],
            'per_page' => $paginated['per_page'],
            'has_next' => $paginated['has_next'],
            'filters' => $filters,
        ], [], 'backoffice');

        return null;
    }
}
