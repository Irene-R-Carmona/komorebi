<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Container;
use App\Core\View;
use App\Http\Transformers\ReviewTransformer;
use App\Services\Contracts\ReviewModerationServiceInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Controlador de Gestión de Reseñas (Admin) — SSR únicamente.
 * Las mutaciones (approve/reject/delete) están en Api\V1\Admin\ReviewApiController.
 */
final class ReviewController
{
    private ReviewModerationServiceInterface $moderationService;
    private ReviewTransformer $reviewTransformer;

    public function __construct(?ReviewModerationServiceInterface $moderationService = null, ?ReviewTransformer $reviewTransformer = null)
    {
        $this->moderationService = $moderationService ?? Container::make(ReviewModerationServiceInterface::class);
        $this->reviewTransformer = $reviewTransformer ?? new ReviewTransformer();
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
}
