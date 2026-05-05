<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manager;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\View;
use App\Services\Contracts\ReviewQueryServiceInterface;
use Psr\Http\Message\ResponseInterface;

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
    public function index(): ?ResponseInterface
    {
        $user = Session::user();
        $cafeId = $user['cafe_id'] ?? null;

        if (!$cafeId) {
            View::render('errors/403', [
                'message' => 'No tienes un café asignado. Contacta con el administrador.',
            ]);

            return null;
        }

        $reviews = $this->queryService->getReviewsByCafeId($cafeId);
        $stats = $this->queryService->getCafeRatingStats($cafeId);

        View::render('manager/reviews/index', [
            'titulo' => 'Gestión de Reseñas',
            'reviews' => $reviews,
            'stats' => $stats,
            'csrf_token' => Csrf::token(),
        ], [], 'backoffice');

        return null;
    }
}
