<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Container;
use App\Core\Http\ResponseFactory;
use App\Core\View;
use App\Http\Transformers\ReviewTransformer;
use App\Services\Contracts\ReviewModerationServiceInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

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
    public function index(ServerRequestInterface $request): ?ResponseInterface
    {
        $page = \max(1, (int) ($request->getQueryParams()['page'] ?? 1));
        $perPage = 10;

        $rawReviews = $this->moderationService->listPendingReviews($page);
        $hasNextPage = \count($rawReviews) > $perPage;
        if ($hasNextPage) {
            \array_pop($rawReviews);
        }

        $reviews = $this->reviewTransformer->collection($rawReviews);
        $meta = ['page' => $page, 'has_next_page' => $hasNextPage];

        View::render('admin/reviews/pending', [
            'titulo' => 'Gestión de Reseñas | Komorebi Admin',
            'pending' => $reviews,
            'meta' => $meta,
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
