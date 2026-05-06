<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use App\Domain\DTO\ReviewDTO;
use App\Repositories\Contracts\ReviewRepositoryInterface;
use App\Services\Contracts\ReviewQueryServiceInterface;
use Exception;
use Override;

final class ReviewQueryService implements ReviewQueryServiceInterface
{
    public function __construct(
        private ReviewRepositoryInterface $reviewRepository,
    ) {}

    #[Override]
    public function getReviewsByUserId(int $userId): array
    {
        return $this->reviewRepository->findByUserId($userId);
    }

    #[Override]
    public function getReviewsByCafeId(int $cafeId): array
    {
        return $this->reviewRepository->findByCafeId($cafeId, 'approved');
    }

    #[Override]
    public function getManagerReviews(int $cafeId, ?string $status, int $page): array
    {
        return $this->reviewRepository->findAllStatusesPaginated($cafeId, $status, $page);
    }

    #[Override]
    public function calculateAverageRating(int $cafeId): float
    {
        return $this->reviewRepository->calculateAverageRating($cafeId);
    }

    #[Override]
    public function listApprovedReviews(int $cafeId, int $page = 1): array
    {
        try {
            return $this->reviewRepository->findApprovedPaginated($cafeId, $page, 10);
        } catch (Exception $e) {
            Logger::error('Error al listar reseñas aprobadas', [
                'exception' => \get_class($e),
                'message' => $e->getMessage(),
                'cafe_id' => $cafeId,
                'page' => $page,
            ]);

            return ['data' => [], 'total' => 0, 'pages' => 0];
        }
    }

    #[Override]
    public function listUserReviews(int $userId): array
    {
        try {
            return $this->reviewRepository->findByUserId($userId);
        } catch (Exception $e) {
            Logger::error('Error al listar reseñas del usuario', [
                'exception' => \get_class($e),
                'message' => $e->getMessage(),
                'user_id' => $userId,
            ]);

            return [];
        }
    }

    #[Override]
    public function getCafeRatingStats(int $cafeId): array
    {
        try {
            $stats = $this->reviewRepository->getRatingStats($cafeId);

            return [
                'average' => $stats['avg_rating'] ?? 0.0,
                'count' => $stats['total_reviews'] ?? 0,
                'distribution' => [
                    1 => $stats['one_star'] ?? 0,
                    2 => $stats['two_stars'] ?? 0,
                    3 => $stats['three_stars'] ?? 0,
                    4 => $stats['four_stars'] ?? 0,
                    5 => $stats['five_stars'] ?? 0,
                ],
            ];
        } catch (Exception $e) {
            Logger::error('Error al obtener estadísticas de ratings', [
                'exception' => \get_class($e),
                'message' => $e->getMessage(),
                'cafe_id' => $cafeId,
            ]);

            return [
                'average' => 0.0,
                'count' => 0,
                'distribution' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0],
            ];
        }
    }

    #[Override]
    public function getReview(int $reviewId): ?ReviewDTO
    {
        try {
            return $this->reviewRepository->findById($reviewId);
        } catch (Exception $e) {
            Logger::error('Error al obtener reseña', [
                'exception' => \get_class($e),
                'message' => $e->getMessage(),
                'review_id' => $reviewId,
            ]);

            return null;
        }
    }
}
