<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use App\Core\Result;
use App\Events\ReviewPublishedEvent;
use App\Repositories\Contracts\CafeRepositoryInterface;
use App\Repositories\Contracts\ReviewRepositoryInterface;
use App\Services\Contracts\ReviewModerationServiceInterface;
use DateTimeImmutable;
use Exception;
use Psr\EventDispatcher\EventDispatcherInterface;

final class ReviewModerationService implements ReviewModerationServiceInterface
{
    public function __construct(
        private ReviewRepositoryInterface $reviewRepository,
        private CafeRepositoryInterface $cafeRepository,
        private ?EventDispatcherInterface $eventDispatcher = null,
    ) {}

    #[\Override]
    public function approveReview(int $reviewId): Result
    {
        try {
            $review = $this->reviewRepository->findById($reviewId);

            if (!$review) {
                return Result::fail('Reseña no encontrada');
            }

            $this->reviewRepository->updateStatus($reviewId, 'approved');

            $cafeId = (int) $review['cafe_id'];
            $this->cafeRepository->updateRating($cafeId);

            Logger::info('Reseña aprobada', [
                'review_id' => $reviewId,
                'cafe_id' => $cafeId,
                'action' => 'approve',
            ]);

            if ($this->eventDispatcher !== null) {
                $this->eventDispatcher->dispatch(new ReviewPublishedEvent(
                    (int) $review['id'],
                    (int) $review['user_id'],
                    (int) $review['rating'],
                    (string) ($review['body'] ?? ''),
                    new DateTimeImmutable(),
                ));
            }

            return Result::ok('Reseña aprobada exitosamente');
        } catch (Exception $e) {
            Logger::error('Error al aprobar reseña', [
                'exception' => \get_class($e),
                'message' => $e->getMessage(),
                'review_id' => $reviewId,
            ]);

            return Result::fail('Error al aprobar reseña');
        }
    }

    #[\Override]
    public function rejectReview(int $reviewId, string $reason): Result
    {
        try {
            if (\strlen(\trim($reason)) < 5 || \strlen(\trim($reason)) > 500) {
                return Result::fail('Motivo debe tener entre 5 y 500 caracteres');
            }

            $review = $this->reviewRepository->findById($reviewId);

            if (!$review) {
                return Result::fail('Reseña no encontrada');
            }

            $reason = \htmlspecialchars($reason, ENT_QUOTES, 'UTF-8');
            $this->reviewRepository->updateStatus($reviewId, 'rejected');

            Logger::info('Reseña rechazada', [
                'review_id' => $reviewId,
                'action' => 'reject',
                'reason_length' => \strlen($reason),
            ]);

            return Result::ok('Reseña rechazada');
        } catch (Exception $e) {
            Logger::error('Error al rechazar reseña', [
                'exception' => \get_class($e),
                'message' => $e->getMessage(),
                'review_id' => $reviewId,
            ]);

            return Result::fail('Error al rechazar reseña');
        }
    }

    #[\Override]
    public function moderateReview(int $reviewId, string $status): bool
    {
        try {
            $review = $this->reviewRepository->findById($reviewId);
            $result = $this->reviewRepository->updateStatus($reviewId, $status);

            if ($result && $review && \in_array($status, ['approved', 'rejected'], true)) {
                $cafeId = (int) $review['cafe_id'];
                $this->cafeRepository->updateRating($cafeId);
            }

            return $result;
        } catch (Exception $e) {
            Logger::error('Error al moderar reseña', [
                'exception' => \get_class($e),
                'message' => $e->getMessage(),
                'review_id' => $reviewId,
                'status' => $status,
            ]);

            return false;
        }
    }

    #[\Override]
    public function listPendingReviews(int $page = 1): array
    {
        try {
            return $this->reviewRepository->findPendingPaginated(10, $page);
        } catch (Exception $e) {
            Logger::error('Error al listar reseñas pendientes', [
                'exception' => \get_class($e),
                'message' => $e->getMessage(),
                'page' => $page,
            ]);

            return [];
        }
    }

    #[\Override]
    public function deleteReviewById(int $reviewId): bool
    {
        try {
            return $this->reviewRepository->delete($reviewId);
        } catch (Exception $e) {
            Logger::error('Error al eliminar reseña (byId)', [
                'exception' => \get_class($e),
                'message' => $e->getMessage(),
                'review_id' => $reviewId,
            ]);

            return false;
        }
    }
}
