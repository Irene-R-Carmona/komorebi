<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Container;
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

    public function __construct(?ReviewModerationServiceInterface $moderationService = null, ?ReviewTransformer $reviewTransformer = null)
    {
        $this->moderationService = $moderationService ?? Container::make(ReviewModerationServiceInterface::class);
        $this->reviewTransformer = $reviewTransformer ?? new ReviewTransformer();
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
            'titulo'  => 'Gestión de Reseñas | Komorebi Admin',
            'pending' => $reviews,
            'extraJs' => ['admin/admin-reviews.js'],
        ], [], 'backoffice');

        return null;
    }
}
