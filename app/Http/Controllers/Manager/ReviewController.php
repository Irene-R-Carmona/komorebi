<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manager;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\View;
use App\Domain\DTO\PaginationParams;
use App\Services\Contracts\ReviewQueryServiceInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Controlador de Reseñas del Manager
 *
 * Responsabilidad: Moderación de reseñas del café asignado al manager.
 * Permisos: role = 'manager'
 */
final class ReviewController
{
    private ReviewQueryServiceInterface $queryService;

    public function __construct(
        ?ReviewQueryServiceInterface $queryService = null
    ) {
        $this->queryService = $queryService ?? Container::make(ReviewQueryServiceInterface::class);
    }

    /**
     * GET /manager/reviews
     * Listado de reseñas del café del manager
     */
    public function index(ServerRequestInterface $request): ?ResponseInterface
    {
        $user = Session::user();
        $cafeId = $user['cafe_id'] ?? null;

        if (!$cafeId) {
            View::render('errors/403', [
                'message' => 'No tienes un café asignado. Contacta con el administrador.',
            ]);

            return null;
        }

        $params = PaginationParams::fromRequest($request);
        $query = $request->getQueryParams();
        $status = (isset($query['status']) && $query['status'] !== '') ? (string) $query['status'] : null;

        $rawRows = $this->queryService->getManagerReviews((int) $cafeId, $status, $params->page);
        $hasNext = \count($rawRows) > 20;
        $reviews = $hasNext ? \array_slice($rawRows, 0, 20) : $rawRows;

        $meta = ['page' => $params->page, 'has_next_page' => $hasNext];
        $currentParams = $params->toQueryArray(['status' => $status ?? '']);
        $stats = $this->queryService->getCafeRatingStats((int) $cafeId);

        View::render('manager/reviews/index', [
            'titulo' => 'Gestión de Reseñas',
            'reviews' => $reviews,
            'stats' => $stats,
            'csrf_token' => Csrf::token(),
            'activeStatus' => $status,
            'meta' => $meta,
            'currentParams' => $currentParams,
        ], [], 'backoffice');

        return null;
    }
}
