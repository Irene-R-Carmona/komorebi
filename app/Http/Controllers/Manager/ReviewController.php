<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manager;

use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Http\ResponseFactory;
use App\Core\Session;
use App\Core\View;
use App\Services\ReviewService;
use Psr\Http\Message\ResponseInterface;

/**
 * Controlador de Reseñas del Manager
 *
 * Responsabilidad: Moderación de reseñas del café asignado al manager.
 * Permisos: role = 'manager'
 */
final class ReviewController
{
    private ReviewService $reviewService;
    private ResponseFactory $response;

    public function __construct(?ReviewService $reviewService = null)
    {
        $this->reviewService = $reviewService ?? new ReviewService();
        $this->response = new ResponseFactory();
    }

    /**
     * GET /manager/reviews
     * Listado de reseñas del café del manager
     */
    public function index(): ?ResponseInterface
    {
        $user   = Session::user();
        $cafeId = $user['cafe_id'] ?? null;

        if (!$cafeId) {
            View::render('errors/403', [
                'message' => 'No tienes un café asignado. Contacta con el administrador.',
            ]);
            return null;
        }

        $reviews = $this->reviewService->getReviewsByCafeId($cafeId);
        $stats   = $this->reviewService->getCafeRatingStats($cafeId);

        View::render('manager/reviews/index', [
            'titulo'     => 'Gestión de Reseñas',
            'reviews'    => $reviews,
            'stats'      => $stats,
            'csrf_token' => Csrf::token(),
        ], [], 'backoffice');
        return null;
    }

    /**
     * POST /manager/reviews/{id}/approve
     * Aprobar una reseña del café
     */
    public function approve(): ResponseInterface
    {
        if (!Csrf::validate()) {
            Flash::error('Token de seguridad inválido');
            return $this->response->redirect('/manager/reviews');
        }

        $id     = (int) ($_POST['id'] ?? 0);
        $result = $this->reviewService->approveReview($id);

        if ($result->isOk()) {
            Flash::success('Reseña aprobada correctamente');
        } else {
            Flash::error($result->getMessage('Error al aprobar reseña'));
        }

        return $this->response->redirect('/manager/reviews');
    }

    /**
     * POST /manager/reviews/{id}/reject
     * Rechazar una reseña del café
     */
    public function reject(): ResponseInterface
    {
        if (!Csrf::validate()) {
            Flash::error('Token de seguridad inválido');
            return $this->response->redirect('/manager/reviews');
        }

        $id     = (int) ($_POST['id'] ?? 0);
        $reason = $_POST['reason'] ?? 'Contenido inapropiado';
        $result = $this->reviewService->rejectReview($id, $reason);

        if ($result->isOk()) {
            Flash::success('Reseña rechazada');
        } else {
            Flash::error($result->getMessage('Error al rechazar reseña'));
        }

        return $this->response->redirect('/manager/reviews');
    }
}
