<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Container;
use App\Core\View;
use App\Domain\DTO\PaginationParams;
use App\Http\Transformers\CafeTransformer;
use App\Repositories\CafeRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Controlador de Gestión de Cafés
 *
 * Responsabilidad única: Vista SSR de lista de cafés
 */
final class CafeController
{
    private CafeRepository  $cafeRepo;
    private CafeTransformer $cafeTransformer;

    public function __construct(
        ?CafeTransformer $cafeTransformer = null,
        ?CafeRepository  $cafeRepo        = null,
    ) {
        $this->cafeTransformer = $cafeTransformer ?? new CafeTransformer();
        $this->cafeRepo        = $cafeRepo        ?? Container::make(CafeRepository::class);
    }

    /**
     * GET /admin/cafes
     * Lista paginada con filtros server-side (HDA).
     */
    public function index(ServerRequestInterface $request): ?ResponseInterface
    {
        $params   = PaginationParams::fromRequest($request);
        $q        = $request->getQueryParams();
        $category = \trim((string) ($q['category'] ?? ''));
        $status   = \trim((string) ($q['status']   ?? ''));

        $pagination = $params->toPagination(20);
        $rawCafes   = $this->cafeRepo->findPaginatedAdmin(
            $pagination,
            $params->search,
            $category,
            $status,
            $params->sort ?: 'name',
            $params->dir,
        );

        $hasNext      = $pagination->hasNextPage(\count($rawCafes));
        $cafes        = $this->cafeTransformer->collection(\array_slice($rawCafes, 0, $pagination->limit));
        $stats        = $this->cafeRepo->getAdminStats();
        $meta         = $pagination->toMeta($hasNext);
        $currentParams = $params->toQueryArray(['category' => $category, 'status' => $status]);

        View::render('admin/cafes/index', [
            'titulo'        => 'Gestión de Cafés',
            'cafes'         => $cafes,
            'stats'         => $stats,
            'meta'          => $meta,
            'currentParams' => $currentParams,
            'extraJs'       => ['admin/admin-cafes.js'],
        ], ['admin/admin-cafes.css'], 'backoffice');

        return null;
    }
}
