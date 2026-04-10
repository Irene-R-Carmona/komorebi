<?php

declare(strict_types=1);


/**
 * ¿Qué pruebas aquí?
 * ¿Qué me quieres demostrar?
 * ¿Qué va a fallar en este test si se cambia el código?
 */

namespace Tests\Unit\Services;

use App\Core\Result;
use App\Models\Review;
use App\Models\User;
use App\Services\ReviewService;
use App\Repositories\Contracts\ReviewRepositoryInterface;
use App\Events\ReviewPublishedEvent;
use App\Repositories\Contracts\CafeRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Tests para ReviewService
 *
 * Verifica:
 * - Creación de reseñas con validaciones
 * - Validación de ratings
 * - Sanitización de contenido
 * - Moderación de reseñas
 */
#[AllowMockObjectsWithoutExpectations]
final class ReviewServiceTest extends TestCase
{
    private ReviewService $service;
    private Review $reviewModelMock;
    private User&MockObject $userModelMock;
    private ReviewRepositoryInterface&MockObject $reviewRepoMock;

    protected function setUp(): void
    {
        $this->reviewModelMock = $this->createStub(Review::class);
        $this->userModelMock = $this->createMock(User::class);
        $this->reviewRepoMock = $this->createMock(ReviewRepositoryInterface::class);
        $this->service = new ReviewService($this->reviewModelMock, $this->userModelMock, $this->reviewRepoMock);
    }

    public function testCreateReviewWithValidDataReturnsSuccess(): void
    {
        $this->userModelMock->method('findById')->willReturn([
            'id' => 1,
            'is_active' => true
        ]);

        $this->reviewRepoMock->method('create')->willReturn(123);

        $result = $this->service->createReview(
            userId: 1,
            cafeId: 5,
            rating: 5,
            title: 'Excelente café',
            body: 'Una experiencia maravillosa con los gatos.'
        );

        $this->assertTrue($result->ok);
        $this->assertArrayHasKey('id', $result->data);
    }

    public function testCreateReviewWithInvalidUserReturnsError(): void
    {
        $this->userModelMock->method('findById')->willReturn(null);

        $result = $this->service->createReview(
            userId: 999,
            cafeId: 5,
            rating: 5,
            title: 'Test',
            body: 'Test review'
        );

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Usuario no encontrado', $result->error);
    }

    public function testCreateReviewWithInactiveUserReturnsError(): void
    {
        $this->userModelMock->method('findById')->willReturn([
            'id' => 1,
            'is_active' => false
        ]);

        $result = $this->service->createReview(
            userId: 1,
            cafeId: 5,
            rating: 5,
            title: 'Test',
            body: 'Test review body'
        );

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('desactivada', $result->error);
    }

    public function testCreateReviewWithInvalidRatingReturnsError(): void
    {
        $this->userModelMock->method('findById')->willReturn([
            'id' => 1,
            'is_active' => true
        ]);

        // Rating fuera de rango (< 1)
        $result = $this->service->createReview(
            userId: 1,
            cafeId: 5,
            rating: 0,
            title: 'Test',
            body: 'Test review body'
        );

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Rating', $result->error);

        // Rating fuera de rango (> 5)
        $result = $this->service->createReview(
            userId: 1,
            cafeId: 5,
            rating: 6,
            title: 'Test',
            body: 'Test review body'
        );

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Rating', $result->error);
    }

    public function testCreateReviewWithShortTitleReturnsError(): void
    {
        $this->userModelMock->method('findById')->willReturn([
            'id' => 1,
            'is_active' => true
        ]);

        $result = $this->service->createReview(
            userId: 1,
            cafeId: 5,
            rating: 5,
            title: 'Ab', // Muy corto
            body: 'This is a valid review body with enough characters.'
        );

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Título', $result->error);
    }

    public function testCreateReviewWithLongTitleReturnsError(): void
    {
        $this->userModelMock->method('findById')->willReturn([
            'id' => 1,
            'is_active' => true
        ]);

        $longTitle = str_repeat('A', 101); // Más de 100 caracteres

        $result = $this->service->createReview(
            userId: 1,
            cafeId: 5,
            rating: 5,
            title: $longTitle,
            body: 'This is a valid review body.'
        );

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Título', $result->error);
    }

    public function testCreateReviewWithShortBodyReturnsError(): void
    {
        $this->userModelMock->method('findById')->willReturn([
            'id' => 1,
            'is_active' => true
        ]);

        $result = $this->service->createReview(
            userId: 1,
            cafeId: 5,
            rating: 5,
            title: 'Valid Title',
            body: 'Short' // Menos de 10 caracteres
        );

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Descripción', $result->error);
    }

    public function testCreateReviewSanitizesHtmlContent(): void
    {
        $this->userModelMock->method('findById')->willReturn([
            'id' => 1,
            'is_active' => true
        ]);

        // Capturar los argumentos pasados al método create del repository
        $capturedData = null;

        $this->reviewRepoMock->expects($this->once())
            ->method('create')
            ->willReturnCallback(function ($data) use (&$capturedData) {
                $capturedData = $data;
                return 123;
            });

        $this->service->createReview(
            userId: 1,
            cafeId: 5,
            rating: 5,
            title: 'Test <script>alert("XSS")</script>',
            body: 'Body with <b>HTML</b> tags and <script>malicious</script> code'
        );

        // Verificar que el HTML fue escapado
        $this->assertNotNull($capturedData);
        $this->assertStringContainsString('&lt;script&gt;', $capturedData['title'] ?? '');
        $this->assertStringContainsString('&lt;script&gt;', $capturedData['body'] ?? '');
    }

    public function testGetReviewsByUserIdReturnsArray(): void
    {
        $expectedReviews = [
            ['id' => 1, 'rating' => 5, 'title' => 'Great'],
            ['id' => 2, 'rating' => 4, 'title' => 'Good']
        ];

        $this->reviewRepoMock->method('findByUserId')->willReturn($expectedReviews);

        $reviews = $this->service->getReviewsByUserId(1);

        $this->assertIsArray($reviews);
        $this->assertCount(2, $reviews);
    }

    public function testGetReviewsByCafeIdReturnsArray(): void
    {
        $expectedReviews = [
            ['id' => 1, 'rating' => 5],
            ['id' => 2, 'rating' => 3]
        ];

        $this->reviewRepoMock->method('findByCafeId')->willReturn($expectedReviews);

        $reviews = $this->service->getReviewsByCafeId(5);

        $this->assertIsArray($reviews);
        $this->assertCount(2, $reviews);
    }

    public function testCalculateAverageRatingReturnsCorrectValue(): void
    {
        $this->reviewRepoMock->method('calculateAverageRating')
            ->with(5)
            ->willReturn(4.0);

        $average = $this->service->calculateAverageRating(5);

        $this->assertEquals(4.0, $average);
    }

    public function testCalculateAverageRatingWithNoReviewsReturnsZero(): void
    {
        $this->reviewRepoMock->method('calculateAverageRating')
            ->with(5)
            ->willReturn(0.0);

        $average = $this->service->calculateAverageRating(5);

        $this->assertEquals(0, $average);
    }

    public function testModerateReviewUpdatesStatus(): void
    {
        $this->reviewRepoMock->expects($this->once())
            ->method('updateStatus')
            ->with(123, 'approved')
            ->willReturn(true);

        $result = $this->service->moderateReview(123, 'approved');

        $this->assertTrue($result);
    }

    public function testDeleteReviewReturnsBoolean(): void
    {
        $this->reviewRepoMock->method('delete')->willReturn(true);

        $result = $this->service->deleteReview(123);

        $this->assertTrue($result);
    }

    public function testApproveReviewDispatchesReviewPublishedEvent(): void
    {
        // ARRANGE: Construir servicio con CafeRepository stub y EventDispatcher mock
        $cafeRepoStub = $this->createStub(CafeRepositoryInterface::class);
        $dispatcherMock = $this->createMock(EventDispatcherInterface::class);

        $service = new ReviewService(
            $this->reviewModelMock,
            $this->userModelMock,
            $this->reviewRepoMock,
            $cafeRepoStub,
            $dispatcherMock
        );

        $this->reviewRepoMock->method('findById')->willReturn([
            'id' => 42,
            'user_id' => 7,
            'cafe_id' => 3,
            'rating' => 4,
            'body' => 'Muy buena experiencia',
            'status' => 'pending',
        ]);
        $this->reviewRepoMock->method('updateStatus')->willReturn(true);

        // ASSERT: dispatch() debe ser llamado con ReviewPublishedEvent
        $dispatcherMock->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (ReviewPublishedEvent $event): bool {
                return $event->reviewId === 42
                    && $event->userId === 7
                    && $event->rating === 4
                    && $event->comment === 'Muy buena experiencia';
            }));

        // ACT
        $result = $service->approveReview(42);

        $this->assertTrue($result->ok);
    }

    public function testApproveReviewDoesNotDispatchWhenNullDispatcher(): void
    {
        // ARRANGE: Servicio sin EventDispatcher (por defecto)
        $cafeRepoStub = $this->createStub(CafeRepositoryInterface::class);

        $service = new ReviewService(
            $this->reviewModelMock,
            $this->userModelMock,
            $this->reviewRepoMock,
            $cafeRepoStub,
            null
        );

        $this->reviewRepoMock->method('findById')->willReturn([
            'id' => 42,
            'user_id' => 7,
            'cafe_id' => 3,
            'rating' => 4,
            'body' => 'OK',
            'status' => 'pending',
        ]);
        $this->reviewRepoMock->method('updateStatus')->willReturn(true);

        // ACT: sin dispatcher, no debe lanzar error
        $result = $service->approveReview(42);

        // ASSERT
        $this->assertTrue($result->ok);
    }
}
