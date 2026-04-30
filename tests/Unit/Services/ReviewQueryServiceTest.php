<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? ReviewQueryService: delegación de consultas de reseñas al repositorio.
 * ¿Qué me quieres demostrar? Que los métodos delegan correctamente y retornan los datos del repositorio.
 * ¿Qué va a fallar en este test si se cambia el código? Si los métodos dejan de delegar al repositorio o cambia la firma.
 */

namespace Tests\Unit\Services;

use App\Domain\DTO\ReviewDTO;
use App\Repositories\Contracts\ReviewRepositoryInterface;
use App\Services\ReviewQueryService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReviewQueryService::class)]
final class ReviewQueryServiceTest extends TestCase
{
    private ReviewRepositoryInterface $repoStub;
    private ReviewQueryService $service;

    protected function setUp(): void
    {
        $this->repoStub = $this->createStub(ReviewRepositoryInterface::class);
        $this->service = new ReviewQueryService($this->repoStub);
    }

    public function testGetReviewsByUserIdReturnsArray(): void
    {
        $expected = [['id' => 1, 'rating' => 5]];
        $this->repoStub->method('findByUserId')->willReturn($expected);

        $result = $this->service->getReviewsByUserId(1);

        $this->assertSame($expected, $result);
    }

    public function testGetReviewsByCafeIdReturnsArray(): void
    {
        $expected = [['id' => 2, 'rating' => 4]];
        $this->repoStub->method('findByCafeId')->willReturn($expected);

        $result = $this->service->getReviewsByCafeId(1);

        $this->assertSame($expected, $result);
    }

    public function testCalculateAverageRatingReturnsDelegatedFloat(): void
    {
        $this->repoStub->method('calculateAverageRating')->willReturn(4.5);

        $avg = $this->service->calculateAverageRating(1);

        $this->assertEqualsWithDelta(4.5, $avg, 0.001);
    }

    public function testListApprovedReviewsReturnsArray(): void
    {
        $this->repoStub->method('findApprovedPaginated')->willReturn([]);

        $reviews = $this->service->listApprovedReviews(1, 1);

        $this->assertIsArray($reviews);
    }

    public function testGetReviewReturnsNullWhenNotFound(): void
    {
        $this->repoStub->method('findById')->willReturn(null);

        $review = $this->service->getReview(999);

        $this->assertNull($review);
    }

    public function testGetReviewReturnsDataWhenFound(): void
    {
        $expected = new ReviewDTO(
            id: 3,
            user_id: 1,
            cafe_id: 1,
            cafe_name: '',
            user_name: '',
            rating: 5,
            title: 'Genial',
            body: '',
            status: 'pending',
            created_at: '',
        );
        $this->repoStub->method('findById')->willReturn($expected);

        $review = $this->service->getReview(3);

        $this->assertSame($expected, $review);
    }

    public function testGetCafeRatingStatsReturnsArray(): void
    {
        $this->repoStub->method('getRatingStats')->willReturn(['avg' => 4.2, 'total' => 10]);

        $stats = $this->service->getCafeRatingStats(1);

        $this->assertIsArray($stats);
    }
}
