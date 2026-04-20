<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * ReviewService: creación, edición y eliminación de reseñas por el propietario.
 *
 * ¿Qué me quieres demostrar?
 * Que la lógica de validación de reseñas (rating, título, body, usuario activo)
 * funciona correctamente y que el sanitizado HTML se aplica.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se relajan validaciones de rating, longitud de título/body, o se elimina
 * la sanitización XSS en createReview.
 */

namespace Tests\Unit\Services;

use App\Core\Result;
use App\Repositories\Contracts\ReservationRepositoryInterface;
use App\Repositories\Contracts\ReviewRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\ReviewService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ReviewService::class)]
final class ReviewServiceTest extends TestCase
{
    private ReviewService $service;
    private UserRepositoryInterface&MockObject $userRepoMock;
    private ReviewRepositoryInterface&MockObject $reviewRepoMock;
    private ReservationRepositoryInterface&MockObject $reservationRepoMock;

    protected function setUp(): void
    {
        $this->userRepoMock        = $this->createMock(UserRepositoryInterface::class);
        $this->reviewRepoMock      = $this->createMock(ReviewRepositoryInterface::class);
        $this->reservationRepoMock = $this->createMock(ReservationRepositoryInterface::class);
        $this->service = new ReviewService(
            $this->userRepoMock,
            $this->reviewRepoMock,
            $this->reservationRepoMock,
        );
    }

    public function testCreateReviewWithValidDataReturnsSuccess(): void
    {
        $this->userRepoMock->method('findById')->willReturn([
            'id' => 1,
            'is_active' => true,
        ]);

        $this->reservationRepoMock->method('hasCompletedReservation')->willReturn(true);
        $this->reviewRepoMock->method('findByUserAndCafe')->willReturn(null);
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
        $this->userRepoMock->method('findById')->willReturn(null);

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
        $this->userRepoMock->method('findById')->willReturn([
            'id' => 1,
            'is_active' => false,
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
        $this->userRepoMock->method('findById')->willReturn([
            'id' => 1,
            'is_active' => true,
        ]);

        $result = $this->service->createReview(
            userId: 1,
            cafeId: 5,
            rating: 0,
            title: 'Test',
            body: 'Test review body'
        );

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Rating', $result->error);

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
        $this->userRepoMock->method('findById')->willReturn([
            'id' => 1,
            'is_active' => true,
        ]);

        $result = $this->service->createReview(
            userId: 1,
            cafeId: 5,
            rating: 5,
            title: 'Ab',
            body: 'This is a valid review body with enough characters.'
        );

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Título', $result->error);
    }

    public function testCreateReviewWithLongTitleReturnsError(): void
    {
        $this->userRepoMock->method('findById')->willReturn([
            'id' => 1,
            'is_active' => true,
        ]);

        $result = $this->service->createReview(
            userId: 1,
            cafeId: 5,
            rating: 5,
            title: \str_repeat('A', 101),
            body: 'This is a valid review body.'
        );

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Título', $result->error);
    }

    public function testCreateReviewWithShortBodyReturnsError(): void
    {
        $this->userRepoMock->method('findById')->willReturn([
            'id' => 1,
            'is_active' => true,
        ]);

        $result = $this->service->createReview(
            userId: 1,
            cafeId: 5,
            rating: 5,
            title: 'Valid Title',
            body: 'Short'
        );

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Descripción', $result->error);
    }

    public function testCreateReviewSanitizesHtmlContent(): void
    {
        $this->userRepoMock->method('findById')->willReturn([
            'id' => 1,
            'is_active' => true,
        ]);
        $this->reservationRepoMock->method('hasCompletedReservation')->willReturn(true);
        $this->reviewRepoMock->method('findByUserAndCafe')->willReturn(null);

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

        $this->assertNotNull($capturedData);
        $this->assertStringContainsString('&lt;script&gt;', $capturedData['title'] ?? '');
        $this->assertStringContainsString('&lt;script&gt;', $capturedData['body'] ?? '');
    }

    public function testDeleteReviewReturnsResult(): void
    {
        $this->reviewRepoMock->method('findById')->willReturn(['id' => 123, 'user_id' => 1]);
        $this->reviewRepoMock->method('delete')->willReturn(true);

        $result = $this->service->deleteReview(123, 1);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertTrue($result->ok);
    }
}
