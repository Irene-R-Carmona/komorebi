<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Container;
use App\Core\Http\ResponseFactory;
use App\Core\View;
use App\Http\Transformers\ReviewTransformer;
use App\Services\Contracts\ReviewModerationServiceInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Controlador de Gestión de Reseñas (Admin)
 */
final class ReviewController
{
    private ReviewModerationServiceInterface $moderationService;
    private ReviewTransformer $reviewTransformer;
    private ResponseFactory $response;

    public function __construct(?ReviewModerationServiceInterface $moderationService = null, ?ReviewTransformer $reviewTransformer = null, ?ResponseFactory $response = null)
    {
        $this->moderationService = $moderationService ?? Container::make(ReviewModerationServiceInterface::class);
        $this->reviewTransformer = $reviewTransformer ?? new ReviewTransformer();
        $this->response = $response ?? Container::make(ResponseFactory::class);
    }

    /**
     * GET /admin/reviews
     */
    public function index(): ?ResponseInterface
    {
        $reviews = $this->reviewTransformer->collection(
            $this->moderationService->listPendingReviews()
        );

        View::render('admin/reviews/pending', [
            'titulo' => 'Gestión de Reseñas | Komorebi Admin',
            'pending' => $reviews,
            'extraJs' => ['admin/admin-reviews.js'],
        ], [], 'backoffice');

        return null;
    }

    /**
     * POST /admin/reviews/{id}/approve
     */
    public function approve(int $id): ResponseInterface
    {
        $result = $this->moderationService->approveReview($id);

        if (!$result->ok) {
            return $this->response->json(['ok' => false, 'message' => $result->error], 422);
        }

        return $this->response->json(['ok' => true]);
    }

    /**
     * POST /admin/reviews/{id}/reject
     */
    public function reject(int $id): ResponseInterface
    {
        $result = $this->moderationService->rejectReview($id, '');

        if (!$result->ok) {
            return $this->response->json(['ok' => false, 'message' => $result->error], 422);
        }

        return $this->response->json(['ok' => true]);
    }

    /**
     * POST /admin/reviews/{id}/delete
     */
    public function delete(int $id): ResponseInterface
    {
        $deleted = $this->moderationService->deleteReviewById($id);

        if (!$deleted) {
            return $this->response->json(['ok' => false, 'message' => 'No se pudo eliminar la reseña'], 422);
        }

        return $this->response->json(['ok' => true]);
    }
}
