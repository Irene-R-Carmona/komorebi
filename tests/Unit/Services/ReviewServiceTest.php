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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReviewService::class)]
final class ReviewServiceTest extends TestCase
{
    private ReviewService $service;
    private UserRepositoryInterface&Stub $userRepoMock;
    private ReviewRepositoryInterface&MockObject $reviewRepoMock;
    private ReservationRepositoryInterface&Stub $reservationRepoMock;

    protected function setUp(): void
    {
        $this->userRepoMock = $this->createStub(UserRepositoryInterface::class);
        $this->reviewRepoMock = $this->createMock(ReviewRepositoryInterface::class);
        $this->reservationRepoMock = $this->createStub(ReservationRepositoryInterface::class);
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
        $this->reviewRepoMock->method('userHasReview')->willReturn(false);
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
        $this->reviewRepoMock->method('userHasReview')->willReturn(false);

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

    // ─────────────────────────────────────────────────────────────
    // Casos adicionales
    // ─────────────────────────────────────────────────────────────

    #[TestDox('Crear una reseña duplicada retorna error con código duplicate_review')]
    public function testCreateReviewWithDuplicateReviewReturnsError(): void
    {
        // arrange
        $this->userRepoMock->method('findById')->willReturn([
            'id'        => 1,
            'is_active' => true,
        ]);
        $this->reviewRepoMock->method('userHasReview')->willReturn(true);

        // act
        $result = $this->service->createReview(
            userId: 1,
            cafeId: 5,
            rating: 4,
            title: 'Segunda reseña',
            body: 'Intento de segunda reseña para el mismo café.'
        );

        // assert
        $this->assertFalse($result->ok);
        $this->assertStringContainsString('reseña', \strtolower($result->error ?? ''));
    }

    #[TestDox('El body de más de 5000 caracteres retorna error de validación')]
    public function testCreateReviewWithLongBodyReturnsError(): void
    {
        // arrange
        $this->userRepoMock->method('findById')->willReturn([
            'id'        => 1,
            'is_active' => true,
        ]);

        // act
        $result = $this->service->createReview(
            userId: 1,
            cafeId: 5,
            rating: 5,
            title: 'Título válido',
            body: \str_repeat('A', 5001)
        );

        // assert
        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Descripción', $result->error ?? '');
    }

    #[TestDox('userHasCompletedReservation retorna false cuando el usuario no tiene reserva completada')]
    public function testUserWithoutCompletedReservationReturnsFalse(): void
    {
        // arrange
        $this->reservationRepoMock->method('hasCompletedReservation')->willReturn(false);

        // act
        $result = $this->service->userHasCompletedReservation(userId: 1, cafeId: 5);

        // assert
        $this->assertFalse($result);
    }

    // ─────────────────────────────────────────────────────────────
    // updateReview
    // ─────────────────────────────────────────────────────────────

    #[TestDox('Actualizar una reseña con datos válidos por el propietario retorna éxito')]
    public function testUpdateReviewWithValidDataByOwnerReturnsSuccess(): void
    {
        // arrange
        $this->reviewRepoMock->method('findById')->willReturn([
            'id'      => 1,
            'user_id' => 1,
            'cafe_id' => 5,
        ]);

        // act
        $result = $this->service->updateReview(
            reviewId: 1,
            userId: 1,
            rating: 4,
            title: 'Título actualizado',
            body: 'Descripción actualizada con suficientes caracteres para ser válida.'
        );

        // assert
        $this->assertTrue($result->ok);
    }

    #[TestDox('Actualizar una reseña por alguien que no es el propietario retorna error')]
    public function testUpdateReviewByNonOwnerReturnsError(): void
    {
        // arrange
        $this->reviewRepoMock->method('findById')->willReturn([
            'id'      => 1,
            'user_id' => 99, // propietario real
            'cafe_id' => 5,
        ]);

        // act
        $result = $this->service->updateReview(
            reviewId: 1,
            userId: 1, // distinto al propietario (99)
            rating: 4,
            title: 'Título válido',
            body: 'Descripción suficientemente larga para superar la validación.'
        );

        // assert
        $this->assertFalse($result->ok);
        $this->assertStringContainsString('editar', \strtolower($result->error ?? ''));
    }

    #[TestDox('Actualizar una reseña que no existe retorna error')]
    public function testUpdateReviewNotFoundReturnsError(): void
    {
        // arrange
        $this->reviewRepoMock->method('findById')->willReturn(null);

        // act
        $result = $this->service->updateReview(
            reviewId: 999,
            userId: 1,
            rating: 4,
            title: 'Título válido',
            body: 'Descripción suficientemente larga para superar la validación.'
        );

        // assert
        $this->assertFalse($result->ok);
        $this->assertStringContainsString('encontrad', \strtolower($result->error ?? ''));
    }

    // ─────────────────────────────────────────────────────────────
    // deleteReview — casos de fallo
    // ─────────────────────────────────────────────────────────────

    #[TestDox('Eliminar una reseña por alguien que no es el propietario retorna error')]
    public function testDeleteReviewByNonOwnerReturnsError(): void
    {
        // arrange
        $this->reviewRepoMock->method('findById')->willReturn([
            'id'      => 1,
            'user_id' => 99, // propietario real
        ]);

        // act
        $result = $this->service->deleteReview(reviewId: 1, userId: 1); // 1 ≠ 99

        // assert
        $this->assertFalse($result->ok);
        $this->assertStringContainsString('eliminar', \strtolower($result->error ?? ''));
    }

    #[TestDox('Eliminar una reseña que no existe retorna error')]
    public function testDeleteReviewNotFoundReturnsError(): void
    {
        // arrange
        $this->reviewRepoMock->method('findById')->willReturn(null);

        // act
        $result = $this->service->deleteReview(reviewId: 999, userId: 1);

        // assert
        $this->assertFalse($result->ok);
        $this->assertStringContainsString('encontrad', \strtolower($result->error ?? ''));
    }

    // ─────────────────────────────────────────────────────────────
    // canUserReview
    // ─────────────────────────────────────────────────────────────

    #[TestDox('canUserReview retorna false con razón cuando el usuario ya tiene una reseña')]
    public function testCanUserReviewReturnsFalseWhenUserAlreadyHasReview(): void
    {
        // arrange
        $this->reviewRepoMock->method('userHasReview')->willReturn(true);

        // act
        $result = $this->service->canUserReview(userId: 1, cafeId: 5);

        // assert
        $this->assertFalse($result['can_review']);
        $this->assertNotEmpty($result['reason']);
    }

    #[TestDox('canUserReview retorna true cuando el usuario no tiene reseña previa')]
    public function testCanUserReviewReturnsTrueWhenNoExistingReview(): void
    {
        // arrange
        $this->reviewRepoMock->method('userHasReview')->willReturn(false);

        // act
        $result = $this->service->canUserReview(userId: 1, cafeId: 5);

        // assert
        $this->assertTrue($result['can_review']);
    }
}
