<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * ¿Qué me quieres demostrar?
 * ¿Qué va a fallar en este test si se cambia el código?
 */

namespace Repositories;

use App\Repositories\ReviewRepository;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

/**
 * Tests para ReviewRepository
 */
final class ReviewRepositoryTest extends TestCase
{
    private ReviewRepository $repository;

    /** @var \PHPUnit\Framework\MockObject\Stub&\PDO */
    private PDO $db;

    protected function setUp(): void
    {
        $this->db = $this->createStub(PDO::class);
        $this->repository = new ReviewRepository($this->db);
    }

    protected function tearDown(): void
    {
        unset($this->repository, $this->db);
    }

    public function testRepositoryCanBeInstantiated(): void
    {
        $this->assertInstanceOf(ReviewRepository::class, $this->repository);
    }

    public function testFindByIdReturnsReview(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn([
            'id' => 1,
            'user_id' => 5,
            'cafe_id' => 3,
            'rating' => 5,
            'title' => 'Excelente',
            'body' => 'Muy buen café',
            'status' => 'approved',
            'user_name' => 'Juan',
            'cafe_name' => 'Komorebi Café',
        ]);

        $this->db->method('prepare')->willReturn($stmt);

        $result = $this->repository->findById(1);

        $this->assertIsArray($result);
        $this->assertSame(1, $result['id']);
        $this->assertSame('Excelente', $result['title']);
    }

    public function testFindByCafeIdReturnsArray(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn([
            ['id' => 1, 'rating' => 5, 'title' => 'Genial'],
            ['id' => 2, 'rating' => 4, 'title' => 'Bueno'],
        ]);

        $this->db->method('prepare')->willReturn($stmt);

        $result = $this->repository->findByCafeId(3);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    public function testCalculateAverageRatingReturnsFloat(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn(['avg_rating' => '4.5']);

        $this->db->method('prepare')->willReturn($stmt);

        $result = $this->repository->calculateAverageRating(3);

        $this->assertIsFloat($result);
        $this->assertSame(4.5, $result);
    }

    public function testCalculateAverageRatingReturnsZeroWhenNoReviews(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn(['avg_rating' => null]);

        $this->db->method('prepare')->willReturn($stmt);

        $result = $this->repository->calculateAverageRating(999);

        $this->assertSame(0.0, $result);
    }

    public function testUserHasReviewReturnsBool(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn(['1' => 1]);

        $this->db->method('prepare')->willReturn($stmt);

        $result = $this->repository->userHasReview(5, 3);

        $this->assertTrue($result);
    }
}
