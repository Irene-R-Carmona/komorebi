<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Flash;
use App\Core\View;
use App\Core\Http\ResponseFactory;
use App\Services\ReviewService;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Random\RandomException;

/**
 * Controlador de Gestión de Reseñas (Admin)
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
     * GET /admin/reviews
     * Lista de reseñas con filtros
     * @throws JsonException
     * @throws RandomException
     */
    public function index(): ?ResponseInterface
    {
        $reviews = $this->reviewService->listPendingReviews();

        View::render('backoffice/admin/reviews/index', [
            'titulo' => 'Gestión de Reseñas',
            'reviews' => $reviews,
            'csrf_token' => Csrf::token(),
        ], [], 'backoffice');
        return null;
    }

    /**
     * POST /admin/reviews/{id}/approve
     * Aprobar reseña
     * @throws JsonException
     * @throws RandomException
     */
    public function approve(): ResponseInterface
    {
        if (!Csrf::validate()) {
            Flash::error('Token de seguridad inválido');
            return $this->response->redirect('/admin/reviews');
        }

        $id = (int) ($_POST['id'] ?? 0);

        $result = $this->reviewService->approveReview($id);

        if ($result->isOk()) {
            Flash::success('Reseña aprobada correctamente');
        } else {
            Flash::error($result->getMessage('Error al aprobar reseña'));
        }

        return $this->response->redirect('/admin/reviews');
    }

    /**
     * POST /admin/reviews/{id}/reject
     * Rechazar reseña
     * @throws JsonException
     * @throws RandomException
     */
    public function reject(): ResponseInterface
    {
        if (!Csrf::validate()) {
            Flash::error('Token de seguridad inválido');
            return $this->response->redirect('/admin/reviews');
        }

        $id = (int) ($_POST['id'] ?? 0);
        $reason = $_POST['reason'] ?? 'Contenido inapropiado';

        $result = $this->reviewService->rejectReview($id, $reason);

        if ($result->isOk()) {
            Flash::success('Reseña rechazada');
        } else {
            Flash::error($result->getMessage('Error al rechazar reseña'));
        }

        return $this->response->redirect('/admin/reviews');
    }

    /**
     * POST /admin/reviews/{id}/delete
     * Eliminar reseña
     * @throws JsonException
     * @throws RandomException
     */
    public function delete(): ResponseInterface
    {
        if (!Csrf::validate()) {
            Flash::error('Token de seguridad inválido');
            return $this->response->redirect('/admin/reviews');
        }

        $id = (int) ($_POST['id'] ?? 0);

        $result = $this->reviewService->deleteReview($id);

        if ($result->isOk()) {
            Flash::success('Reseña eliminada');
        } else {
            Flash::error($result->getMessage('Error al eliminar reseña'));
        }

        return $this->response->redirect('/admin/reviews');
    }
}
