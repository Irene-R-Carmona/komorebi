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
use Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;

#[CoversClass(ReviewService::class)]
final class ReviewServiceTest extends ServiceTestCase
{
    private UserRepositoryInterface $userRepoStub;
    private ReviewRepositoryInterface $reviewRepoStub;
    private ReservationRepositoryInterface $reservationRepoStub;
    private ReviewService $service;

    protected function setUp(): void
    {
        $this->userRepoStub = $this->createStub(UserRepositoryInterface::class);
        $this->reviewRepoStub = $this->createStub(ReviewRepositoryInterface::class);
        $this->reservationRepoStub = $this->createStub(ReservationRepositoryInterface::class);
        $this->service = new ReviewService(
            $this->userRepoStub,
            $this->reviewRepoStub,
            $this->reservationRepoStub
        );
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
        $this->userRepoStub->method('findById')->willReturn($this->makeActiveUser());

        $result = $this->service->createReview(1, 1, 0, 'Título OK aquí', 'Cuerpo suficientemente largo para pasar');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Rating', $result->error);
    }

    public function testCreateReviewFailsWhenRatingAboveFive(): void
    {
        $this->userRepoStub->method('findById')->willReturn($this->makeActiveUser());

        $result = $this->service->createReview(1, 1, 6, 'Título OK aquí', 'Cuerpo suficientemente largo para pasar');

        $this->assertFalse($result->ok);
    }

    public function testCreateReviewFailsWhenTitleTooShort(): void
    {
        $this->userRepoStub->method('findById')->willReturn($this->makeActiveUser());

        $result = $this->service->createReview(1, 1, 4, 'AB', 'Cuerpo suficientemente largo para pasar');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Título', $result->error);
    }

    public function testCreateReviewFailsWhenBodyTooShort(): void
    {
        $this->userRepoStub->method('findById')->willReturn($this->makeActiveUser());

        $result = $this->service->createReview(1, 1, 4, 'Título válido aquí', 'Corto');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Descripción', $result->error);
    }

    public function testCreateReviewFailsWhenDuplicateReview(): void
    {
        $this->userRepoStub->method('findById')->willReturn($this->makeActiveUser());
        // Todas las reservas completadas ya tienen reseña
        $this->reservationRepoStub->method('getCompletedByUserAndCafe')->willReturn([['id' => 10]]);
        $this->reviewRepoStub->method('existsByReservationId')->willReturn(true);

        $result = $this->service->createReview(1, 1, 4, 'Título válido aquí', 'Cuerpo suficientemente largo para pasar correctamente');

        $this->assertFalse($result->ok);
        $this->assertSame('duplicate_review', $result->code);
    }

    public function testCreateReviewSucceedsWithValidData(): void
    {
        $this->userRepoStub->method('findById')->willReturn($this->makeActiveUser());
        // Al menos una reserva completada sin reseña
        $this->reservationRepoStub->method('getCompletedByUserAndCafe')->willReturn([['id' => 10]]);
        $this->reviewRepoStub->method('existsByReservationId')->willReturn(false);
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

    // ──────────────────────────────────────────────
    // deleteReview — ownership + success
    // ──────────────────────────────────────────────

    public function testDeleteReviewFailsWhenOwnershipMismatch(): void
    {
        $this->reviewRepoStub->method('findById')->willReturn($this->makeReview(userId: 99));

        $result = $this->service->deleteReview(10, 1);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('eliminar', $result->error);
    }

    public function testDeleteReviewSucceedsWhenOwner(): void
    {
        $this->reviewRepoStub->method('findById')->willReturn($this->makeReview(userId: 1));
        $this->reviewRepoStub->method('delete')->willReturn(true);

        $result = $this->service->deleteReview(10, 1);

        $this->assertTrue($result->ok);
    }

    // ──────────────────────────────────────────────
    // updateReview — todos los caminos
    // ──────────────────────────────────────────────

    public function testUpdateReviewFailsWhenNotFound(): void
    {
        $this->reviewRepoStub->method('findById')->willReturn(null);

        $result = $this->service->updateReview(999, 1, 4, 'Título válido', 'Cuerpo suficientemente largo para pasar');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('encontrada', $result->error);
    }

    public function testUpdateReviewFailsWhenOwnershipMismatch(): void
    {
        $this->reviewRepoStub->method('findById')->willReturn($this->makeReview(userId: 99));

        $result = $this->service->updateReview(10, 1, 4, 'Título válido', 'Cuerpo suficientemente largo para pasar');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('editar', $result->error);
    }

    public function testUpdateReviewFailsWhenRatingBelowOne(): void
    {
        $this->reviewRepoStub->method('findById')->willReturn($this->makeReview(userId: 1));

        $result = $this->service->updateReview(10, 1, 0, 'Título válido', 'Cuerpo suficientemente largo para pasar');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Rating', $result->error);
    }

    public function testUpdateReviewFailsWhenRatingAboveFive(): void
    {
        $this->reviewRepoStub->method('findById')->willReturn($this->makeReview(userId: 1));

        $result = $this->service->updateReview(10, 1, 6, 'Título válido', 'Cuerpo suficientemente largo para pasar');

        $this->assertFalse($result->ok);
    }

    public function testUpdateReviewFailsWhenTitleTooShort(): void
    {
        $this->reviewRepoStub->method('findById')->willReturn($this->makeReview(userId: 1));

        $result = $this->service->updateReview(10, 1, 4, 'AB', 'Cuerpo suficientemente largo para pasar');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Título', $result->error);
    }

    public function testUpdateReviewFailsWhenTitleTooLong(): void
    {
        $this->reviewRepoStub->method('findById')->willReturn($this->makeReview(userId: 1));
        $longTitle = \str_repeat('A', 101);

        $result = $this->service->updateReview(10, 1, 4, $longTitle, 'Cuerpo suficientemente largo para pasar');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Título', $result->error);
    }

    public function testUpdateReviewFailsWhenBodyTooShort(): void
    {
        $this->reviewRepoStub->method('findById')->willReturn($this->makeReview(userId: 1));

        $result = $this->service->updateReview(10, 1, 4, 'Título válido', 'Corto');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Descripción', $result->error);
    }

    public function testUpdateReviewFailsWhenBodyTooLong(): void
    {
        $this->reviewRepoStub->method('findById')->willReturn($this->makeReview(userId: 1));
        $longBody = \str_repeat('X', 5001);

        $result = $this->service->updateReview(10, 1, 4, 'Título válido', $longBody);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Descripción', $result->error);
    }

    public function testUpdateReviewSucceedsWithValidData(): void
    {
        $this->reviewRepoStub->method('findById')->willReturn($this->makeReview(userId: 1));
        $this->reviewRepoStub->method('update')->willReturn(true);

        $result = $this->service->updateReview(10, 1, 5, 'Título perfectamente válido', 'Cuerpo suficientemente largo para pasar');

        $this->assertTrue($result->ok);
    }

    // ──────────────────────────────────────────────
    // canUserReview
    // ──────────────────────────────────────────────

    public function testCanUserReviewReturnsFalseWhenAlreadyReviewed(): void
    {
        $this->reservationRepoStub->method('getCompletedByUserAndCafe')->willReturn([['id' => 1]]);
        $this->reviewRepoStub->method('existsByReservationId')->willReturn(true);

        $result = $this->service->canUserReview(1, 1);

        $this->assertFalse($result['can_review']);
        $this->assertStringContainsString('reseña', $result['reason']);
    }

    public function testCanUserReviewReturnsTrueWhenNoExistingReview(): void
    {
        $this->reservationRepoStub->method('getCompletedByUserAndCafe')->willReturn([['id' => 1]]);
        $this->reviewRepoStub->method('existsByReservationId')->willReturn(false);

        $result = $this->service->canUserReview(1, 1);

        $this->assertTrue($result['can_review']);
    }

    public function testCanUserReviewReturnsFalseWhenNoCompletedReservation(): void
    {
        $this->reservationRepoStub->method('getCompletedByUserAndCafe')->willReturn([]);

        $result = $this->service->canUserReview(1, 1);

        $this->assertFalse($result['can_review']);
        $this->assertStringContainsString('reserva', $result['reason']);
    }

    public function testCanUserReviewReturnsTrueWhenCompletedReservationExists(): void
    {
        $this->reservationRepoStub->method('getCompletedByUserAndCafe')->willReturn([['id' => 5]]);
        $this->reviewRepoStub->method('existsByReservationId')->willReturn(false);

        $result = $this->service->canUserReview(1, 1);

        $this->assertTrue($result['can_review']);
    }

    public function testCanUserReviewReturnsFalseWhenAllReservationsReviewed(): void
    {
        $this->reservationRepoStub->method('getCompletedByUserAndCafe')->willReturn([['id' => 5]]);
        $this->reviewRepoStub->method('existsByReservationId')->willReturn(true);

        $result = $this->service->canUserReview(1, 1);

        $this->assertFalse($result['can_review']);
        $this->assertStringContainsString('reseña', $result['reason']);
    }

    public function testCanUserReviewReturnsTrueWhenSecondReservationNotReviewed(): void
    {
        $this->reservationRepoStub->method('getCompletedByUserAndCafe')
            ->willReturn([['id' => 5], ['id' => 6]]);
        $this->reviewRepoStub->method('existsByReservationId')
            ->willReturnOnConsecutiveCalls(true, false);

        $result = $this->service->canUserReview(1, 1);

        $this->assertTrue($result['can_review']);
    }

    // ──────────────────────────────────────────────
    // userHasCompletedReservation
    // ──────────────────────────────────────────────

    public function testUserHasCompletedReservationReturnsTrue(): void
    {
        $this->reservationRepoStub->method('hasCompletedReservation')->willReturn(true);

        $this->assertTrue($this->service->userHasCompletedReservation(1, 1));
    }

    public function testUserHasCompletedReservationReturnsFalse(): void
    {
        $this->reservationRepoStub->method('hasCompletedReservation')->willReturn(false);

        $this->assertFalse($this->service->userHasCompletedReservation(1, 1));
    }

    // ──────────────────────────────────────────────
    // userHasReviewInCafe
    // ──────────────────────────────────────────────

    public function testUserHasReviewInCafeReturnsTrue(): void
    {
        $this->reviewRepoStub->method('userHasReview')->willReturn(true);

        $this->assertTrue($this->service->userHasReviewInCafe(1, 1));
    }

    public function testUserHasReviewInCafeReturnsFalse(): void
    {
        $this->reviewRepoStub->method('userHasReview')->willReturn(false);

        $this->assertFalse($this->service->userHasReviewInCafe(1, 1));
    }

    // ──────────────────────────────────────────────
    // createReview — casos límite adicionales
    // ──────────────────────────────────────────────

    public function testCreateReviewFailsWhenTitleTooLong(): void
    {
        $this->userRepoStub->method('findById')->willReturn($this->makeActiveUser());
        $longTitle = \str_repeat('A', 101);

        $result = $this->service->createReview(1, 1, 4, $longTitle, 'Cuerpo suficientemente largo para pasar');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Título', $result->error);
    }

    public function testCreateReviewFailsWhenBodyTooLong(): void
    {
        $this->userRepoStub->method('findById')->willReturn($this->makeActiveUser());
        $longBody = \str_repeat('X', 5001);

        $result = $this->service->createReview(1, 1, 4, 'Título válido aquí', $longBody);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Descripción', $result->error);
    }

    // ──────────────────────────────────────────────
    // Exception paths
    // ──────────────────────────────────────────────

    public function testCreateReviewHandlesRuntimeException(): void
    {
        $this->userRepoStub->method('findById')
            ->willThrowException(new RuntimeException('Connection lost'));

        $result = $this->service->createReview(1, 1, 3, 'Título válido aquí', 'Cuerpo suficientemente largo para pasar');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Connection lost', $result->error);
    }

    public function testCreateReviewHandlesGenericException(): void
    {
        $this->userRepoStub->method('findById')->willReturn($this->makeActiveUser());
        // Hay una reserva sin reseña — supera el guard y llega a create()
        $this->reservationRepoStub->method('getCompletedByUserAndCafe')->willReturn([['id' => 10]]);
        $this->reviewRepoStub->method('existsByReservationId')->willReturn(false);
        $this->reviewRepoStub->method('create')
            ->willThrowException(new Exception('Unexpected error'));

        $result = $this->service->createReview(1, 1, 4, 'Título válido aquí', 'Cuerpo suficientemente largo para pasar');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Error', $result->error);
    }

    public function testUpdateReviewHandlesException(): void
    {
        $this->reviewRepoStub->method('findById')
            ->willThrowException(new Exception('DB error'));

        $result = $this->service->updateReview(1, 1, 4, 'Título válido', 'Cuerpo suficientemente largo para pasar');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Error', $result->error);
    }

    public function testDeleteReviewHandlesException(): void
    {
        $this->reviewRepoStub->method('findById')
            ->willThrowException(new Exception('DB error'));

        $result = $this->service->deleteReview(1, 1);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Error', $result->error);
    }

    public function testDeleteReviewAdminHandlesException(): void
    {
        $this->reviewRepoStub->method('delete')
            ->willThrowException(new Exception('DB error'));

        $result = $this->service->deleteReviewAdmin(1);

        $this->assertFalse($result->ok);
        $this->assertSame('delete_error', $result->code);
    }

    public function testCanUserReviewHandlesException(): void
    {
        $this->reservationRepoStub->method('getCompletedByUserAndCafe')
            ->willThrowException(new Exception('DB error'));

        $result = $this->service->canUserReview(1, 1);

        $this->assertFalse($result['can_review']);
        $this->assertStringContainsString('Error', $result['reason']);
    }

    public function testUserHasCompletedReservationHandlesException(): void
    {
        $this->reservationRepoStub->method('hasCompletedReservation')
            ->willThrowException(new Exception('DB error'));

        $result = $this->service->userHasCompletedReservation(1, 1);

        $this->assertFalse($result);
    }

    public function testUserHasReviewInCafeHandlesException(): void
    {
        $this->reviewRepoStub->method('userHasReview')
            ->willThrowException(new Exception('DB error'));

        $result = $this->service->userHasReviewInCafe(1, 1);

        $this->assertFalse($result);
    }
}
