<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * ReviewQueryService: consultas de reseñas por usuario, café, paginación y estadísticas.
 *
 * ¿Qué me quieres demostrar?
 * Que el servicio delega correctamente en el repositorio, que getCafeRatingStats
 * transforma el resultado del repositorio al formato esperado, y que ante excepciones
 * el servicio devuelve arrays vacíos o con valores por defecto en lugar de propagar el error.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se modifica el formato de salida de getCafeRatingStats, si listApprovedReviews
 * deja de capturar excepciones, o si getReview deja de devolver null ante un fallo.
 */

namespace Tests\Unit\Services;

use App\Repositories\Contracts\ReviewRepositoryInterface;
use App\Services\ReviewQueryService;
use Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReviewQueryService::class)]
final class ReviewQueryServiceTest extends TestCase
{
    private ReviewQueryService $service;
    private ReviewRepositoryInterface&Stub $reviewRepoStub;

    protected function setUp(): void
    {
        $this->reviewRepoStub = $this->createStub(ReviewRepositoryInterface::class);
        $this->service        = new ReviewQueryService($this->reviewRepoStub);
    }

    #[TestDox('getReviewsByUserId retorna el listado de reseñas del usuario')]
    public function testGetReviewsByUserIdReturnsUserReviews(): void
    {
        $reviews = [
            ['id' => 1, 'user_id' => 5, 'rating' => 4],
            ['id' => 2, 'user_id' => 5, 'rating' => 5],
        ];

        $this->reviewRepoStub
            ->method('findByUserId')
            ->willReturn($reviews);

        $result = $this->service->getReviewsByUserId(5);

        $this->assertCount(2, $result);
        $this->assertSame(4, $result[0]['rating']);
    }

    #[TestDox('getReviewsByCafeId retorna reseñas aprobadas del café')]
    public function testGetReviewsByCafeIdReturnsApprovedReviews(): void
    {
        $reviews = [['id' => 3, 'cafe_id' => 2, 'status' => 'approved']];

        $this->reviewRepoStub
            ->method('findByCafeId')
            ->willReturn($reviews);

        $result = $this->service->getReviewsByCafeId(2);

        $this->assertCount(1, $result);
        $this->assertSame('approved', $result[0]['status']);
    }

    #[TestDox('calculateAverageRating retorna el promedio calculado por el repositorio')]
    public function testCalculateAverageRatingReturnsDelegatedFloat(): void
    {
        $this->reviewRepoStub
            ->method('calculateAverageRating')
            ->willReturn(4.25);

        $this->assertSame(4.25, $this->service->calculateAverageRating(1));
    }

    #[TestDox('listApprovedReviews retorna datos paginados del repositorio')]
    public function testListApprovedReviewsReturnsPaginatedData(): void
    {
        $paginated = [
            ['id' => 10, 'cafe_id' => 1, 'status' => 'approved'],
            ['id' => 11, 'cafe_id' => 1, 'status' => 'approved'],
        ];

        $this->reviewRepoStub
            ->method('findApprovedPaginated')
            ->willReturn($paginated);

        $result = $this->service->listApprovedReviews(1, 2);

        $this->assertCount(2, $result);
    }

    #[TestDox('listApprovedReviews retorna array vacío de fallback cuando el repositorio lanza excepción')]
    public function testListApprovedReviewsReturnsFallbackOnException(): void
    {
        $repoStub = $this->createStub(ReviewRepositoryInterface::class);
        $repoStub
            ->method('findApprovedPaginated')
            ->willThrowException(new Exception('DB error'));

        $service = new ReviewQueryService($repoStub);
        $result  = $service->listApprovedReviews(1, 1);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('data', $result);
        $this->assertEmpty($result['data']);
    }

    #[TestDox('listUserReviews retorna el listado de reseñas del usuario')]
    public function testListUserReviewsReturnsUserReviews(): void
    {
        $reviews = [['id' => 20, 'user_id' => 7]];

        $this->reviewRepoStub
            ->method('findByUserId')
            ->willReturn($reviews);

        $result = $this->service->listUserReviews(7);

        $this->assertCount(1, $result);
    }

    #[TestDox('getCafeRatingStats transforma correctamente las estadísticas del repositorio')]
    public function testGetCafeRatingStatsReturnsFormattedStats(): void
    {
        $this->reviewRepoStub
            ->method('getRatingStats')
            ->willReturn([
                'avg_rating'     => 4.5,
                'total_reviews'  => 10,
                'one_star'       => 0,
                'two_stars'      => 1,
                'three_stars'    => 2,
                'four_stars'     => 3,
                'five_stars'     => 4,
            ]);

        $result = $this->service->getCafeRatingStats(1);

        $this->assertSame(4.5, $result['average']);
        $this->assertSame(10, $result['count']);
        $this->assertArrayHasKey('distribution', $result);
        $this->assertSame(4, $result['distribution'][5]);
    }

    #[TestDox('getCafeRatingStats retorna valores por defecto cuando el repositorio lanza excepción')]
    public function testGetCafeRatingStatsReturnsDefaultsOnException(): void
    {
        $repoStub = $this->createStub(ReviewRepositoryInterface::class);
        $repoStub
            ->method('getRatingStats')
            ->willThrowException(new Exception('DB error'));

        $service = new ReviewQueryService($repoStub);
        $result  = $service->getCafeRatingStats(1);

        $this->assertSame(0.0, $result['average']);
        $this->assertSame(0, $result['count']);
        $this->assertSame([1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0], $result['distribution']);
    }

    #[TestDox('getReview retorna los datos de la reseña cuando existe')]
    public function testGetReviewReturnsReviewDataWhenFound(): void
    {
        $review = ['id' => 55, 'body' => 'Muy buen café', 'rating' => 5];

        $this->reviewRepoStub
            ->method('findById')
            ->willReturn($review);

        $result = $this->service->getReview(55);

        $this->assertNotNull($result);
        $this->assertSame(55, $result['id']);
    }

    #[TestDox('getReview retorna null cuando la reseña no existe')]
    public function testGetReviewReturnsNullWhenNotFound(): void
    {
        $this->reviewRepoStub
            ->method('findById')
            ->willReturn(null);

        $this->assertNull($this->service->getReview(999));
    }
}
