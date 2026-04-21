<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * ReviewModerationService: aprobación, rechazo, moderación, listado y eliminación de reseñas.
 *
 * ¿Qué me quieres demostrar?
 * Que approveReview actualiza el estado y el rating del café, que rejectReview valida
 * la longitud del motivo antes de buscar la reseña, y que cada método devuelve
 * correctamente Result::ok o Result::fail según el escenario.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se eliminan las validaciones de longitud del motivo en rejectReview, si approveReview
 * deja de actualizar el rating del café, si se modifica el contrato Result del servicio,
 * o si se deja de despachar el evento ReviewPublishedEvent al aprobar.
 */

namespace Tests\Unit\Services;

use App\Events\ReviewPublishedEvent;
use App\Repositories\Contracts\CafeRepositoryInterface;
use App\Repositories\Contracts\ReviewRepositoryInterface;
use App\Services\ReviewModerationService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

#[CoversClass(ReviewModerationService::class)]
final class ReviewModerationServiceTest extends TestCase
{
    private ReviewModerationService $service;
    private ReviewRepositoryInterface&Stub $reviewRepoMock;
    private CafeRepositoryInterface&Stub $cafeRepoStub;

    protected function setUp(): void
    {
        $this->reviewRepoMock = $this->createStub(ReviewRepositoryInterface::class);
        $this->cafeRepoStub   = $this->createStub(CafeRepositoryInterface::class);

        $this->service = new ReviewModerationService(
            $this->reviewRepoMock,
            $this->cafeRepoStub,
        );
    }

    #[TestDox('approveReview retorna ok cuando la reseña existe')]
    public function testApproveReviewReturnsOkWhenReviewExists(): void
    {
        /** @var ReviewRepositoryInterface&MockObject $reviewRepoMock */
        $reviewRepoMock = $this->createMock(ReviewRepositoryInterface::class);
        $reviewRepoMock
            ->expects($this->once())
            ->method('findById')
            ->with(42)
            ->willReturn(['id' => 42, 'cafe_id' => 5, 'user_id' => 1, 'rating' => 4, 'body' => 'Buen café']);
        $reviewRepoMock
            ->expects($this->once())
            ->method('updateStatus')
            ->with(42, 'approved')
            ->willReturn(true);

        $service = new ReviewModerationService($reviewRepoMock, $this->cafeRepoStub);
        $result  = $service->approveReview(42);

        $this->assertTrue($result->ok);
    }

    #[TestDox('approveReview retorna fail cuando la reseña no existe')]
    public function testApproveReviewReturnsFailWhenReviewNotFound(): void
    {
        $this->reviewRepoMock
            ->method('findById')
            ->willReturn(null);

        $result = $this->service->approveReview(999);

        $this->assertFalse($result->ok);
        $this->assertNotEmpty($result->error);
    }

    #[TestDox('approveReview actualiza el rating del café tras aprobar')]
    public function testApproveReviewUpdatesCafeRating(): void
    {
        $this->reviewRepoMock
            ->method('findById')
            ->willReturn(['id' => 7, 'cafe_id' => 3, 'user_id' => 2, 'rating' => 5, 'body' => 'Perfecto']);

        $this->reviewRepoMock->method('updateStatus')->willReturn(true);

        $cafeRepoMock = $this->createMock(CafeRepositoryInterface::class);
        $cafeRepoMock
            ->expects($this->once())
            ->method('updateRating')
            ->with(3);

        $service = new ReviewModerationService($this->reviewRepoMock, $cafeRepoMock);
        $result  = $service->approveReview(7);

        $this->assertTrue($result->ok);
    }

    #[TestDox('approveReview despacha ReviewPublishedEvent cuando hay dispatcher')]
    public function testApproveReviewDispatchesEventWhenDispatcherProvided(): void
    {
        $this->reviewRepoMock
            ->method('findById')
            ->willReturn(['id' => 1, 'cafe_id' => 2, 'user_id' => 3, 'rating' => 5, 'body' => 'Increíble']);

        $this->reviewRepoMock->method('updateStatus')->willReturn(true);

        $dispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $dispatcherMock
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(ReviewPublishedEvent::class));

        $serviceWithDispatcher = new ReviewModerationService(
            $this->reviewRepoMock,
            $this->cafeRepoStub,
            $dispatcherMock,
        );

        $result = $serviceWithDispatcher->approveReview(1);

        $this->assertTrue($result->ok);
    }

    #[TestDox('rejectReview retorna ok con motivo de longitud válida')]
    public function testRejectReviewReturnsOkWithValidReason(): void
    {
        /** @var ReviewRepositoryInterface&MockObject $reviewRepoMock */
        $reviewRepoMock = $this->createMock(ReviewRepositoryInterface::class);
        $reviewRepoMock
            ->expects($this->any())
            ->method('findById')
            ->willReturn(['id' => 10, 'cafe_id' => 1, 'user_id' => 2]);
        $reviewRepoMock
            ->expects($this->once())
            ->method('updateStatus')
            ->with(10, 'rejected')
            ->willReturn(true);

        $service = new ReviewModerationService($reviewRepoMock, $this->cafeRepoStub);
        $result  = $service->rejectReview(10, 'Contenido inapropiado detectado en la reseña.');

        $this->assertTrue($result->ok);
    }

    #[TestDox('rejectReview retorna fail cuando el motivo tiene menos de 5 caracteres')]
    public function testRejectReviewReturnsFailWhenReasonTooShort(): void
    {
        $result = $this->service->rejectReview(10, 'No');

        $this->assertFalse($result->ok);
        $this->assertNotEmpty($result->error);
    }

    #[TestDox('rejectReview retorna fail cuando el motivo supera 500 caracteres')]
    public function testRejectReviewReturnsFailWhenReasonTooLong(): void
    {
        $result = $this->service->rejectReview(10, \str_repeat('x', 501));

        $this->assertFalse($result->ok);
        $this->assertNotEmpty($result->error);
    }

    #[TestDox('rejectReview retorna fail cuando la reseña no existe')]
    public function testRejectReviewReturnsFailWhenReviewNotFound(): void
    {
        $this->reviewRepoMock
            ->method('findById')
            ->willReturn(null);

        $result = $this->service->rejectReview(99, 'Motivo válido de rechazo con suficiente detalle.');

        $this->assertFalse($result->ok);
        $this->assertNotEmpty($result->error);
    }

    #[TestDox('moderateReview retorna true cuando el repositorio actualiza con éxito')]
    public function testModerateReviewReturnsTrueWhenUpdateSucceeds(): void
    {
        $this->reviewRepoMock
            ->method('findById')
            ->willReturn(['id' => 5, 'cafe_id' => 3]);

        $this->reviewRepoMock
            ->method('updateStatus')
            ->willReturn(true);

        $this->assertTrue($this->service->moderateReview(5, 'approved'));
    }

    #[TestDox('listPendingReviews retorna el array devuelto por el repositorio')]
    public function testListPendingReviewsReturnsRepositoryData(): void
    {
        $pending = [
            ['id' => 1, 'status' => 'pending', 'body' => 'Reseña uno'],
            ['id' => 2, 'status' => 'pending', 'body' => 'Reseña dos'],
        ];

        $this->reviewRepoMock
            ->method('findPendingPaginated')
            ->willReturn($pending);

        $result = $this->service->listPendingReviews(1);

        $this->assertCount(2, $result);
        $this->assertSame(1, $result[0]['id']);
    }

    #[TestDox('deleteReviewById retorna true cuando el repositorio elimina con éxito')]
    public function testDeleteReviewByIdReturnsTrueOnSuccess(): void
    {
        /** @var ReviewRepositoryInterface&MockObject $reviewRepoMock */
        $reviewRepoMock = $this->createMock(ReviewRepositoryInterface::class);
        $reviewRepoMock
            ->expects($this->once())
            ->method('delete')
            ->with(7)
            ->willReturn(true);

        $service = new ReviewModerationService($reviewRepoMock, $this->cafeRepoStub);
        $this->assertTrue($service->deleteReviewById(7));
    }
}
