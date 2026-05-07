<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? ReviewModerationService: aprobación/rechazo de reseñas con validaciones de motivo.
 * ¿Qué me quieres demostrar? Que un motivo demasiado corto o largo retorna fail, y que review no encontrada retorna fail.
 * ¿Qué va a fallar en este test si se cambia el código? Si cambia el rango de longitud del motivo (5-500) o la guard de reseña no encontrada.
 */

namespace Tests\Unit\Services;

use App\Domain\DTO\ReviewDTO;
use App\Repositories\Contracts\CafeRepositoryInterface;
use App\Repositories\Contracts\ReviewRepositoryInterface;
use App\Services\ReviewModerationService;
use Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReviewModerationService::class)]
final class ReviewModerationServiceTest extends TestCase
{
    private ReviewRepositoryInterface $reviewRepoStub;
    private CafeRepositoryInterface $cafeRepoStub;
    private ReviewModerationService $service;

    protected function setUp(): void
    {
        $this->reviewRepoStub = $this->createStub(ReviewRepositoryInterface::class);
        $this->cafeRepoStub = $this->createStub(CafeRepositoryInterface::class);
        $this->service = new ReviewModerationService(
            $this->reviewRepoStub,
            $this->cafeRepoStub,
            null
        );
    }

    public function testApproveReviewFailsWhenNotFound(): void
    {
        $this->reviewRepoStub->method('findById')->willReturn(null);

        $result = $this->service->approveReview(999);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('no encontrada', $result->error);
    }

    public function testApproveReviewSucceeds(): void
    {
        $this->reviewRepoStub->method('findById')->willReturn(
            new ReviewDTO(
                id: 1,
                user_id: 1,
                cafe_id: 2,
                cafe_name: '',
                user_name: '',
                rating: 0,
                title: '',
                body: '',
                status: 'pending',
                created_at: '',
            )
        );

        $result = $this->service->approveReview(1);

        $this->assertTrue($result->ok);
    }

    public function testRejectReviewFailsWhenReasonTooShort(): void
    {
        $result = $this->service->rejectReview(1, 'hi');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Motivo', $result->error);
    }

    public function testRejectReviewFailsWhenReasonTooLong(): void
    {
        $reason = \str_repeat('x', 501);

        $result = $this->service->rejectReview(1, $reason);

        $this->assertFalse($result->ok);
    }

    public function testRejectReviewFailsWhenNotFound(): void
    {
        $this->reviewRepoStub->method('findById')->willReturn(null);

        $result = $this->service->rejectReview(1, 'Este es un motivo válido con suficientes chars');

        $this->assertFalse($result->ok);
    }

    public function testRejectReviewSucceedsWithValidReason(): void
    {
        $this->reviewRepoStub->method('findById')->willReturn(
            new ReviewDTO(
                id: 1,
                user_id: 1,
                cafe_id: 2,
                cafe_name: '',
                user_name: '',
                rating: 0,
                title: '',
                body: '',
                status: 'pending',
                created_at: '',
            )
        );

        $result = $this->service->rejectReview(1, 'Este es un motivo válido con suficientes chars');

        $this->assertTrue($result->ok);
    }

    public function testListPendingReviewsReturnsArray(): void
    {
        $this->reviewRepoStub->method('findPendingPaginated')->willReturn([]);

        $reviews = $this->service->listPendingReviews();

        $this->assertIsArray($reviews);
    }

    public function testListPendingReviewsReturnsEmptyOnException(): void
    {
        $this->reviewRepoStub->method('findPendingPaginated')->willThrowException(new Exception('DB error'));

        $reviews = $this->service->listPendingReviews();

        $this->assertSame([], $reviews);
    }

    public function testModerateReviewReturnsFalseWhenRepoThrows(): void
    {
        $this->reviewRepoStub->method('findById')->willThrowException(new Exception('DB error'));

        $result = $this->service->moderateReview(1, 'approved');

        $this->assertFalse($result);
    }

    public function testModerateReviewReturnsTrueOnSuccess(): void
    {
        $this->reviewRepoStub->method('findById')->willReturn(
            new ReviewDTO(
                id: 1,
                user_id: 1,
                cafe_id: 2,
                cafe_name: '',
                user_name: '',
                rating: 4,
                title: '',
                body: '',
                status: 'pending',
                created_at: '',
            )
        );
        $this->reviewRepoStub->method('updateStatus')->willReturn(true);

        $result = $this->service->moderateReview(1, 'approved');

        $this->assertTrue($result);
    }

    public function testModerateReviewReturnsFalseWhenUpdateFails(): void
    {
        $this->reviewRepoStub->method('findById')->willReturn(null);
        $this->reviewRepoStub->method('updateStatus')->willReturn(false);

        $result = $this->service->moderateReview(1, 'rejected');

        $this->assertFalse($result);
    }

    public function testModerateReviewWithPendingStatusDoesNotUpdateRating(): void
    {
        $this->reviewRepoStub->method('findById')->willReturn(
            new ReviewDTO(
                id: 1,
                user_id: 1,
                cafe_id: 2,
                cafe_name: '',
                user_name: '',
                rating: 2,
                title: '',
                body: '',
                status: 'pending',
                created_at: '',
            )
        );
        $this->reviewRepoStub->method('updateStatus')->willReturn(true);

        $result = $this->service->moderateReview(1, 'pending');

        $this->assertTrue($result);
    }

    public function testDeleteReviewByIdReturnsTrueOnSuccess(): void
    {
        $this->reviewRepoStub->method('delete')->willReturn(true);

        $result = $this->service->deleteReviewById(1);

        $this->assertTrue($result);
    }

    public function testDeleteReviewByIdReturnsFalseWhenRepoReturnsFalse(): void
    {
        $this->reviewRepoStub->method('delete')->willReturn(false);

        $result = $this->service->deleteReviewById(1);

        $this->assertFalse($result);
    }

    public function testDeleteReviewByIdReturnsFalseOnException(): void
    {
        $this->reviewRepoStub->method('delete')->willThrowException(new Exception('DB error'));

        $result = $this->service->deleteReviewById(1);

        $this->assertFalse($result);
    }
}
