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
use App\Models\User;
use App\Services\ReviewService;
use App\Repositories\Contracts\ReviewRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

/**
 * Tests para ReviewService
 *
 * Verifica:
 * - Creación de reseñas con validaciones
 * - Validación de ratings
 * - Sanitización de contenido
 * - Eliminación de reseñas
 */
#[AllowMockObjectsWithoutExpectations]
final class ReviewServiceTest extends TestCase
{
    private ReviewService $service;
    private User&MockObject $userModelMock;
    private ReviewRepositoryInterface&MockObject $reviewRepoMock;

    protected function setUp(): void
    {
        $this->userModelMock = $this->createMock(User::class);
        $this->reviewRepoMock = $this->createMock(ReviewRepositoryInterface::class);
        $this->service = new ReviewService($this->userModelMock, $this->reviewRepoMock);
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

    public function testDeleteReviewReturnsResult(): void
    {
        $this->reviewRepoMock->method('delete')->willReturn(true);

        $result = $this->service->deleteReview(123);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertTrue($result->ok);
    }
}
