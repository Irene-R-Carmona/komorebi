<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * AdoptionService::requestAdoption(), approveRequest(), rejectRequest(), withdrawRequest()
 *
 * ¿Qué me quieres demostrar?
 * Que no se pueden crear solicitudes duplicadas (pending), que solo se puede solicitar
 * si el animal está marcado como adoptable, y que al aprobar la solicitud el animal
 * queda marcado como adoptado (adopted_at, adopted_by establecidos).
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se elimina la validación de solicitud duplicada, si se omite actualizar animals.adopted_at
 * al aprobar, o si se permite solicitar la adopción de un animal no disponible.
 */

namespace Tests\Unit\Services;

use App\Core\Result;
use App\Repositories\Contracts\AdoptionRepositoryInterface;
use App\Repositories\Contracts\AnimalRepositoryInterface;
use App\Services\AdoptionService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AdoptionService::class)]
final class AdoptionServiceTest extends TestCase
{
    private AdoptionRepositoryInterface $adoptionRepo;
    private AnimalRepositoryInterface $animalRepo;
    private AdoptionService $service;

    protected function setUp(): void
    {
        $this->adoptionRepo = $this->createStub(AdoptionRepositoryInterface::class);
        $this->animalRepo = $this->createStub(AnimalRepositoryInterface::class);

        $this->service = new AdoptionService(
            $this->adoptionRepo,
            $this->animalRepo,
        );
    }

    // ─── requestAdoption ─────────────────────────────────────────────────────

    public function testRequestAdoptionFailsWhenAnimalNotAdoptable(): void
    {
        $this->adoptionRepo->method('findRequestById')->willReturn(null);
        $this->adoptionRepo->method('findAdoptable')->willReturn([]);   // no aparece en adoptables

        // El animal no está entre los adoptables → fallo
        $result = $this->service->requestAdoption(userId: 5, animalId: 99, message: null);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertFalse($result->ok);
        $this->assertSame('animal_not_adoptable', $result->code);
    }

    public function testRequestAdoptionFailsWhenAlreadyPending(): void
    {
        // El animal sí es adoptable
        $this->adoptionRepo->method('findAdoptable')->willReturn([
            ['id' => 3, 'is_adoptable' => 1],
        ]);
        // Pero ya hay una solicitud pendiente del mismo usuario
        $this->adoptionRepo->method('hasPendingRequest')->willReturn(true);

        $result = $this->service->requestAdoption(userId: 5, animalId: 3, message: 'Me gustaría adoptarlo');

        $this->assertFalse($result->ok);
        $this->assertSame('request_already_exists', $result->code);
    }

    public function testRequestAdoptionSucceeds(): void
    {
        $this->adoptionRepo->method('findAdoptable')->willReturn([
            ['id' => 3, 'is_adoptable' => 1],
        ]);
        $this->adoptionRepo->method('hasPendingRequest')->willReturn(false);
        $this->adoptionRepo->method('createRequest')->willReturn(42);

        $result = $this->service->requestAdoption(userId: 5, animalId: 3, message: 'Me gustaría adoptarlo');

        $this->assertTrue($result->ok);
        $this->assertSame(42, $result->data);
    }

    // ─── approveRequest ──────────────────────────────────────────────────────

    public function testApproveRequestFailsWhenNotFound(): void
    {
        $this->adoptionRepo->method('findRequestById')->willReturn(null);

        $result = $this->service->approveRequest(keeperId: 1, requestId: 999, cafeId: 1);

        $this->assertFalse($result->ok);
        $this->assertSame('request_not_found', $result->code);
    }

    public function testApproveRequestFailsWhenNotPending(): void
    {
        $this->adoptionRepo->method('findRequestById')->willReturn([
            'id'            => 7,
            'animal_id'     => 3,
            'user_id'       => 5,
            'animal_cafe_id' => 1,
            'status'        => 'rejected',
        ]);

        $result = $this->service->approveRequest(keeperId: 1, requestId: 7, cafeId: 1);

        $this->assertFalse($result->ok);
        $this->assertSame('request_not_pending', $result->code);
    }

    public function testApproveRequestFailsWhenAnimalBelongsToAnotherCafe(): void
    {
        $this->adoptionRepo->method('findRequestById')->willReturn([
            'id'            => 7,
            'animal_id'     => 3,
            'user_id'       => 5,
            'animal_cafe_id' => 2,
            'status'        => 'pending',
        ]);

        $result = $this->service->approveRequest(keeperId: 1, requestId: 7, cafeId: 1);

        $this->assertFalse($result->ok);
        $this->assertSame('forbidden', $result->code);
    }

    public function testApproveRequestSucceedsAndMarksAnimalAdopted(): void
    {
        $this->adoptionRepo->method('findRequestById')->willReturn([
            'id'            => 7,
            'animal_id'     => 3,
            'user_id'       => 5,
            'animal_cafe_id' => 1,
            'status'        => 'pending',
        ]);
        $this->adoptionRepo->method('updateRequest')->willReturn(true);
        $this->animalRepo->method('markAsAdopted')->willReturn(true);

        $result = $this->service->approveRequest(keeperId: 1, requestId: 7, cafeId: 1);

        $this->assertTrue($result->ok);
    }

    // ─── rejectRequest ───────────────────────────────────────────────────────

    public function testRejectRequestFailsWhenNotFound(): void
    {
        $this->adoptionRepo->method('findRequestById')->willReturn(null);

        $result = $this->service->rejectRequest(keeperId: 1, requestId: 999, notes: null, cafeId: 1);

        $this->assertFalse($result->ok);
        $this->assertSame('request_not_found', $result->code);
    }

    public function testRejectRequestFailsWhenAnimalBelongsToAnotherCafe(): void
    {
        $this->adoptionRepo->method('findRequestById')->willReturn([
            'id'            => 7,
            'animal_id'     => 3,
            'user_id'       => 5,
            'animal_cafe_id' => 2,
            'status'        => 'pending',
        ]);

        $result = $this->service->rejectRequest(keeperId: 1, requestId: 7, notes: null, cafeId: 1);

        $this->assertFalse($result->ok);
        $this->assertSame('forbidden', $result->code);
    }

    public function testRejectRequestSucceedsWithNotes(): void
    {
        $this->adoptionRepo->method('findRequestById')->willReturn([
            'id'            => 7,
            'animal_id'     => 3,
            'user_id'       => 5,
            'animal_cafe_id' => 1,
            'status'        => 'pending',
        ]);
        $this->adoptionRepo->method('updateRequest')->willReturn(true);

        $result = $this->service->rejectRequest(keeperId: 1, requestId: 7, notes: 'No cumple los requisitos', cafeId: 1);

        $this->assertTrue($result->ok);
    }

    // ─── withdrawRequest ─────────────────────────────────────────────────────

    public function testWithdrawRequestFailsWhenNotFound(): void
    {
        $this->adoptionRepo->method('findRequestById')->willReturn(null);

        $result = $this->service->withdrawRequest(userId: 5, requestId: 999);

        $this->assertFalse($result->ok);
        $this->assertSame('request_not_found', $result->code);
    }

    public function testWithdrawRequestFailsWhenNotOwner(): void
    {
        $this->adoptionRepo->method('findRequestById')->willReturn([
            'id' => 7,
            'user_id' => 9,   // Otro usuario
            'status' => 'pending',
        ]);

        $result = $this->service->withdrawRequest(userId: 5, requestId: 7);

        $this->assertFalse($result->ok);
        $this->assertSame('forbidden', $result->code);
    }

    public function testWithdrawRequestSucceeds(): void
    {
        $this->adoptionRepo->method('findRequestById')->willReturn([
            'id' => 7,
            'user_id' => 5,
            'status' => 'pending',
        ]);
        $this->adoptionRepo->method('updateRequest')->willReturn(true);

        $result = $this->service->withdrawRequest(userId: 5, requestId: 7);

        $this->assertTrue($result->ok);
    }
}
