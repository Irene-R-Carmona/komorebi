<?php

/**
 * ¿Qué prueba aquí? ReviewRepository — acceso a datos de reseñas.
 * ¿Qué me quieres demostrar? El repositorio delega correctamente en PDO y retorna los tipos esperados
 *   (ReviewDTO, array, bool, int, float) para cada método público.
 * ¿Qué va a fallar en este test si se cambia el código? Cambios en la firma pública, las queries o la
 *   lógica de fallback (avg_rating=null, getRatingStats empty, update sin campos válidos).
 */

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Domain\DTO\ReviewDTO;
use App\Repositories\ReviewRepository;

final class ReviewRepositoryTest extends RepositoryTestCase
{
    // ─────────────────────────────────────────────────────────────
    // findById
    // ─────────────────────────────────────────────────────────────

    public function testFindByIdReturnsDtoWhenFound(): void
    {
        $pdo = $this->makePdo(fetchReturn: RowFactory::reviewRow());
        $repo = new ReviewRepository($pdo);

        $dto = $repo->findById(1);

        $this->assertInstanceOf(ReviewDTO::class, $dto);
        $this->assertSame(1, $dto->id);
        $this->assertSame('approved', $dto->status);
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $pdo = $this->makePdo(fetchReturn: false);
        $repo = new ReviewRepository($pdo);

        $this->assertNull($repo->findById(99));
    }

    // ─────────────────────────────────────────────────────────────
    // findByUserId
    // ─────────────────────────────────────────────────────────────

    public function testFindByUserIdReturnsRows(): void
    {
        $pdo = $this->makePdo(fetchAllReturn: [RowFactory::reviewRow()]);
        $repo = new ReviewRepository($pdo);

        $result = $repo->findByUserId(1);

        $this->assertCount(1, $result);
    }

    public function testFindByUserIdReturnsEmptyArray(): void
    {
        $pdo = $this->makePdo(fetchAllReturn: []);
        $repo = new ReviewRepository($pdo);

        $this->assertSame([], $repo->findByUserId(1));
    }

    // ─────────────────────────────────────────────────────────────
    // findByCafeId
    // ─────────────────────────────────────────────────────────────

    public function testFindByCafeIdReturnsApprovedRows(): void
    {
        $pdo = $this->makePdo(fetchAllReturn: [
            RowFactory::reviewRow(),
            RowFactory::reviewRow(['id' => 2]),
        ]);
        $repo = new ReviewRepository($pdo);

        $result = $repo->findByCafeId(1, 'approved');

        $this->assertCount(2, $result);
    }

    public function testFindByCafeIdReturnsEmptyWhenNone(): void
    {
        $pdo = $this->makePdo(fetchAllReturn: []);
        $repo = new ReviewRepository($pdo);

        $this->assertSame([], $repo->findByCafeId(1));
    }

    // ─────────────────────────────────────────────────────────────
    // findApprovedPaginated
    // ─────────────────────────────────────────────────────────────

    public function testFindApprovedPaginatedReturnsRows(): void
    {
        $pdo = $this->makePdo(fetchAllReturn: [RowFactory::reviewRow()]);
        $repo = new ReviewRepository($pdo);

        $result = $repo->findApprovedPaginated(1, 1, 10);

        $this->assertCount(1, $result['data']);
    }

    public function testFindApprovedPaginatedReturnsEmptyArray(): void
    {
        $pdo = $this->makePdo(fetchAllReturn: []);
        $repo = new ReviewRepository($pdo);

        $result = $repo->findApprovedPaginated(1, 2, 10);
        $this->assertSame([], $result['data']);
        $this->assertSame(0, $result['total']);
    }

    // ─────────────────────────────────────────────────────────────
    // findPendingPaginated
    // ─────────────────────────────────────────────────────────────

    public function testFindPendingPaginatedReturnsRows(): void
    {
        $pdo = $this->makePdo(fetchAllReturn: [RowFactory::reviewRow(['status' => 'pending'])]);
        $repo = new ReviewRepository($pdo);

        $result = $repo->findPendingPaginated(1, 20);

        $this->assertCount(1, $result);
    }

    public function testFindPendingPaginatedReturnsEmptyArray(): void
    {
        $pdo = $this->makePdo(fetchAllReturn: []);
        $repo = new ReviewRepository($pdo);

        $this->assertSame([], $repo->findPendingPaginated());
    }

    // ─────────────────────────────────────────────────────────────
    // create
    // ─────────────────────────────────────────────────────────────

    public function testCreateReturnsNewId(): void
    {
        $pdo = $this->makePdo(lastInsertId: '42');
        $repo = new ReviewRepository($pdo);

        $id = $repo->create([
            'user_id' => 1,
            'cafe_id' => 1,
            'rating' => 5,
            'title' => 'Genial',
            'body' => 'Muy buen lugar.',
            'status' => 'pending',
            'rejection_reason' => null,
        ]);

        $this->assertSame(42, $id);
    }

    // ─────────────────────────────────────────────────────────────
    // update
    // ─────────────────────────────────────────────────────────────

    public function testUpdateReturnsTrueOnSuccess(): void
    {
        $pdo = $this->makePdo(rowCount: 1);
        $repo = new ReviewRepository($pdo);

        $result = $repo->update(1, ['rating' => 4, 'title' => 'Actualizada']);

        $this->assertTrue($result);
    }

    public function testUpdateReturnsTrueWhenNoValidFields(): void
    {
        $pdo = $this->makePdo();
        $repo = new ReviewRepository($pdo);

        // No valid fields → early return true without hitting DB
        $this->assertTrue($repo->update(1, ['unknown_field' => 'value']));
    }

    // ─────────────────────────────────────────────────────────────
    // updateStatus
    // ─────────────────────────────────────────────────────────────

    public function testUpdateStatusReturnsTrueOnSuccess(): void
    {
        $pdo = $this->makePdo(rowCount: 1);
        $repo = new ReviewRepository($pdo);

        $this->assertTrue($repo->updateStatus(1, 'approved'));
    }

    // ─────────────────────────────────────────────────────────────
    // calculateAverageRating
    // ─────────────────────────────────────────────────────────────

    public function testCalculateAverageRatingReturnsFloat(): void
    {
        $pdo = $this->makePdo(fetchReturn: ['avg_rating' => '4.50']);
        $repo = new ReviewRepository($pdo);

        $avg = $repo->calculateAverageRating(1);

        $this->assertSame(4.5, $avg);
    }

    public function testCalculateAverageRatingReturnsZeroWhenNoReviews(): void
    {
        $pdo = $this->makePdo(fetchReturn: false);
        $repo = new ReviewRepository($pdo);

        $this->assertSame(0.0, $repo->calculateAverageRating(1));
    }

    public function testCalculateAverageRatingReturnsZeroWhenAvgIsNull(): void
    {
        $pdo = $this->makePdo(fetchReturn: ['avg_rating' => null]);
        $repo = new ReviewRepository($pdo);

        $this->assertSame(0.0, $repo->calculateAverageRating(1));
    }

    // ─────────────────────────────────────────────────────────────
    // userHasReview
    // ─────────────────────────────────────────────────────────────

    public function testUserHasReviewReturnsTrueWhenExists(): void
    {
        $pdo = $this->makePdo(fetchReturn: ['1' => 1]);
        $repo = new ReviewRepository($pdo);

        $this->assertTrue($repo->userHasReview(1, 1));
    }

    public function testUserHasReviewReturnsFalseWhenNotExists(): void
    {
        $pdo = $this->makePdo(fetchReturn: false);
        $repo = new ReviewRepository($pdo);

        $this->assertFalse($repo->userHasReview(1, 99));
    }

    // ─────────────────────────────────────────────────────────────
    // getRatingStats
    // ─────────────────────────────────────────────────────────────

    public function testGetRatingStatsReturnsStatsRow(): void
    {
        $row = [
            'total_reviews' => 10,
            'avg_rating' => '4.3',
            'min_rating' => 3,
            'max_rating' => 5,
            'five_stars' => 5,
            'four_stars' => 3,
            'three_stars' => 2,
            'two_stars' => 0,
            'one_star' => 0,
        ];
        $pdo = $this->makePdo(fetchReturn: $row);
        $repo = new ReviewRepository($pdo);

        $result = $repo->getRatingStats(1);

        $this->assertSame(10, $result['total_reviews']);
        $this->assertSame('4.3', $result['avg_rating']);
    }

    public function testGetRatingStatsReturnsDefaultsWhenNoData(): void
    {
        $pdo = $this->makePdo(fetchReturn: false);
        $repo = new ReviewRepository($pdo);

        $result = $repo->getRatingStats(1);

        $this->assertSame(0, $result['total_reviews']);
        $this->assertSame(0.0, $result['avg_rating']);
    }
}
