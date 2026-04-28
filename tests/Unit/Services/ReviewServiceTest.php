<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? ReviewService: validaciones de rating, título, cuerpo y reglas de negocio (una reseña por usuario/café).
 * ¿Qué me quieres demostrar? Que valores inválidos (rating fuera de rango, title corto, body corto) retornan Result::fail.
 * ¿Qué va a fallar en este test si se cambia el código? Si cambian los rangos válidos de rating/título/cuerpo o la guard de reseña duplicada.
 */

namespace Tests\Unit\Services;

use App\Domain\DTO\UserDTO;
use App\Repositories\Contracts\ReservationRepositoryInterface;
use App\Repositories\Contracts\ReviewRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\ReviewService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReviewService::class)]
final class ReviewServiceTest extends TestCase
{
    private UserRepositoryInterface $userRepoStub;
    private ReviewRepositoryInterface $reviewRepoStub;
    private ReservationRepositoryInterface $reservationRepoStub;
    private ReviewService $service;

    protected function setUp(): void
    {
        $this->userRepoStub        = $this->createStub(UserRepositoryInterface::class);
        $this->reviewRepoStub      = $this->createStub(ReviewRepositoryInterface::class);
        $this->reservationRepoStub = $this->createStub(ReservationRepositoryInterface::class);
        $this->service             = new ReviewService(
            $this->userRepoStub,
            $this->reviewRepoStub,
            $this->reservationRepoStub
        );
    }

    private function activeUser(): UserDTO
    {
        return new UserDTO(id: 1, uuid: '', name: 'Test', email: 'test@test.com', avatar: null, roles: [], is_active: true, cafe_id: null, created_at: '');
    }

    public function testCreateReviewFailsWhenUserNotFound(): void
    {
        $this->userRepoStub->method('findById')->willReturn(null);

        $result = $this->service->createReview(1, 1, 3, 'Título OK aquí', 'Cuerpo suficientemente largo para pasar');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Usuario no encontrado', $result->error);
    }

    public function testCreateReviewFailsWhenUserIsInactive(): void
    {
        $this->userRepoStub->method('findById')->willReturn(new UserDTO(id: 1, uuid: '', name: 'Test', email: 'test@test.com', avatar: null, roles: [], is_active: false, cafe_id: null, created_at: ''));

        $result = $this->service->createReview(1, 1, 3, 'Título OK aquí', 'Cuerpo suficientemente largo para pasar');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('desactivada', $result->error);
    }

    public function testCreateReviewFailsWhenRatingBelowOne(): void
    {
        $this->userRepoStub->method('findById')->willReturn($this->activeUser());

        $result = $this->service->createReview(1, 1, 0, 'Título OK aquí', 'Cuerpo suficientemente largo para pasar');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Rating', $result->error);
    }

    public function testCreateReviewFailsWhenRatingAboveFive(): void
    {
        $this->userRepoStub->method('findById')->willReturn($this->activeUser());

        $result = $this->service->createReview(1, 1, 6, 'Título OK aquí', 'Cuerpo suficientemente largo para pasar');

        $this->assertFalse($result->ok);
    }

    public function testCreateReviewFailsWhenTitleTooShort(): void
    {
        $this->userRepoStub->method('findById')->willReturn($this->activeUser());

        $result = $this->service->createReview(1, 1, 4, 'AB', 'Cuerpo suficientemente largo para pasar');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Título', $result->error);
    }

    public function testCreateReviewFailsWhenBodyTooShort(): void
    {
        $this->userRepoStub->method('findById')->willReturn($this->activeUser());

        $result = $this->service->createReview(1, 1, 4, 'Título válido aquí', 'Corto');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Descripción', $result->error);
    }

    public function testCreateReviewFailsWhenDuplicateReview(): void
    {
        $this->userRepoStub->method('findById')->willReturn($this->activeUser());
        $this->reviewRepoStub->method('userHasReview')->willReturn(true);

        $result = $this->service->createReview(1, 1, 4, 'Título válido aquí', 'Cuerpo suficientemente largo para pasar correctamente');

        $this->assertFalse($result->ok);
        $this->assertSame('duplicate_review', $result->code);
    }

    public function testCreateReviewSucceedsWithValidData(): void
    {
        $this->userRepoStub->method('findById')->willReturn($this->activeUser());
        $this->reviewRepoStub->method('userHasReview')->willReturn(false);
        $this->reviewRepoStub->method('create')->willReturn(42);

        $result = $this->service->createReview(1, 1, 4, 'Título válido aquí', 'Cuerpo suficientemente largo para pasar correctamente');

        $this->assertTrue($result->ok);
        $this->assertArrayHasKey('id', $result->data);
    }

    public function testDeleteReviewFailsWhenReviewNotFound(): void
    {
        $this->reviewRepoStub->method('findById')->willReturn(null);

        $result = $this->service->deleteReview(999, 1);

        $this->assertFalse($result->ok);
    }

    public function testDeleteReviewAdminSucceeds(): void
    {
        $this->reviewRepoStub->method('delete')->willReturn(true);

        $result = $this->service->deleteReviewAdmin(1);

        $this->assertTrue($result->ok);
    }

    public function testDeleteReviewAdminFailsWhenDeleteFails(): void
    {
        $this->reviewRepoStub->method('delete')->willReturn(false);

        $result = $this->service->deleteReviewAdmin(1);

        $this->assertFalse($result->ok);
        $this->assertSame('delete_failed', $result->code);
    }
}
