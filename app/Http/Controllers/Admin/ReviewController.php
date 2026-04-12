<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\View;
use App\Core\Http\ResponseFactory;
use App\Services\Contracts\ReviewModerationServiceInterface;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Random\RandomException;

/**
 * Controlador de Gestión de Reseñas (Admin)
 */
final class ReviewController
{
    private ReviewModerationServiceInterface $moderationService;
    private ResponseFactory $response;

    public function __construct(?ReviewModerationServiceInterface $moderationService = null, ?ResponseFactory $response = null)
    {
        $this->moderationService = $moderationService ?? Container::make(ReviewModerationServiceInterface::class);
        $this->response = $response ?? new ResponseFactory();
    }

    /**
     * GET /admin/reviews
     * Lista de reseñas con filtros
     * @throws JsonException
     * @throws RandomException
     */
    public function index(): ?ResponseInterface
    {
        $reviews = $this->moderationService->listPendingReviews();

        View::render('admin/reviews/pending', [
            'titulo' => 'Gestión de Reseñas',
            'pending' => $reviews,
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

        $result = $this->moderationService->approveReview($id);

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

        $result = $this->moderationService->rejectReview($id, $reason);

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

        $deleted = $this->moderationService->deleteReviewById($id);

        if ($deleted) {
            Flash::success('Reseña eliminada');
        } else {
            Flash::error('Error al eliminar reseña');
        }

        return $this->response->redirect('/admin/reviews');
    }
}
