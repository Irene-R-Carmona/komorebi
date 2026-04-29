<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? ReservationService: validación de campos requeridos y formatos al crear reservas.
 * ¿Qué me quieres demostrar? Que create retorna Result::fail si faltan campos o tienen formatos incorrectos.
 * ¿Qué va a fallar en este test si se cambia el código? Si se eliminan validaciones de campos requeridos o de formato.
 */

namespace Tests\Unit\Services;

use App\Repositories\Contracts\CafeRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\ReservationRepositoryInterface;
use App\Services\Contracts\EmailServiceInterface;
use App\Services\Contracts\InvoicePDFServiceInterface;
use App\Services\ReservationService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReservationService::class)]
final class ReservationServiceTest extends TestCase
{
    private ReservationRepositoryInterface $reservationRepoStub;
    private CafeRepositoryInterface $cafeRepoStub;
    private ProductRepositoryInterface $productRepoStub;
    private InvoicePDFServiceInterface $invoiceServiceStub;
    private EmailServiceInterface $emailServiceStub;
    private ReservationService $service;

    protected function setUp(): void
    {
        $this->reservationRepoStub = $this->createStub(ReservationRepositoryInterface::class);
        $this->cafeRepoStub        = $this->createStub(CafeRepositoryInterface::class);
        $this->productRepoStub     = $this->createStub(ProductRepositoryInterface::class);
        $this->invoiceServiceStub  = $this->createStub(InvoicePDFServiceInterface::class);
        $this->emailServiceStub    = $this->createStub(EmailServiceInterface::class);

        $this->service = new ReservationService(
            $this->reservationRepoStub,
            $this->cafeRepoStub,
            $this->productRepoStub,
            $this->invoiceServiceStub,
            $this->emailServiceStub
        );
    }

    public function testCreateReturnsFailWhenRequiredFieldsMissing(): void
    {
        $result = $this->service->create(['user_id' => 1]);

        $this->assertFalse($result->ok);
        $this->assertSame('validation_error', $result->code);
    }

    public function testCreateReturnsFailWhenDateFormatInvalid(): void
    {
        $result = $this->service->create([
            'user_id'         => 1,
            'cafe_id'         => 1,
            'pass_product_id' => 1,
            'date'            => '20-12-2025',
            'time'            => '10:00',
            'guests'          => 2,
        ]);

        $this->assertFalse($result->ok);
        $this->assertSame('validation_error', $result->code);
    }

    public function testCreateReturnsFailWhenTimeFormatInvalid(): void
    {
        $result = $this->service->create([
            'user_id'         => 1,
            'cafe_id'         => 1,
            'pass_product_id' => 1,
            'date'            => '2099-12-20',
            'time'            => '9am',
            'guests'          => 2,
        ]);

        $this->assertFalse($result->ok);
        $this->assertSame('validation_error', $result->code);
    }

    public function testCreateReturnsFailWhenGuestsOutOfRange(): void
    {
        $result = $this->service->create([
            'user_id'         => 1,
            'cafe_id'         => 1,
            'pass_product_id' => 1,
            'date'            => '2099-12-20',
            'time'            => '10:00',
            'guests'          => 0,
        ]);

        $this->assertFalse($result->ok);
        $this->assertSame('validation_error', $result->code);
    }

    public function testGetByUserReturnsDelegatedArray(): void
    {
        $this->reservationRepoStub->method('findByUser')->willReturn([]);

        $result = $this->service->getByUser(1);

        $this->assertIsArray($result);
    }

    public function testGetUpcomingReturnsDelegatedArray(): void
    {
        $this->reservationRepoStub->method('findUpcomingByUser')->willReturn([]);

        $result = $this->service->getUpcoming(1);

        $this->assertIsArray($result);
    }

    public function testCancelFailsWhenRepoReturnsFalse(): void
    {
        $this->reservationRepoStub->method('cancel')->willReturn(false);

        $result = $this->service->cancel(999, 1);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('cancelar', $result->error);
    }

    public function testCancelSucceeds(): void
    {
        $this->reservationRepoStub->method('cancel')->willReturn(true);

        $result = $this->service->cancel(1, 1);

        $this->assertTrue($result->ok);
    }

    public function testAdminCancelFailsWhenReservationNotFound(): void
    {
        $this->reservationRepoStub->method('findById')->willReturn(null);

        $result = $this->service->adminCancel(999);

        $this->assertFalse($result->ok);
        $this->assertSame('not_found', $result->code);
    }

    public function testAdminCancelSucceedsWhenValidTransition(): void
    {
        $dto = new \App\Domain\DTO\ReservationDTO(
            id: 1,
            uuid: 'abc',
            cafe_id: 1,
            user_id: 1,
            date: '2025-12-01',
            time: '10:00',
            guest_count: 2,
            status: 'confirmed',
            time_slot_id: null,
            pass_name: null,
            check_in_at: null,
            check_out_at: null,
            final_amount: null,
            payment_status: null,
            payment_method: null,
            notes: null,
        );
        $this->reservationRepoStub->method('findById')->willReturn($dto);
        $this->reservationRepoStub->method('updateStatus')->willReturn(true);

        $result = $this->service->adminCancel(1);

        $this->assertTrue($result->ok);
    }

    public function testAdminConfirmFailsWhenReservationNotFound(): void
    {
        $this->reservationRepoStub->method('findById')->willReturn(null);

        $result = $this->service->adminConfirm(999);

        $this->assertFalse($result->ok);
        $this->assertSame('not_found', $result->code);
    }

    public function testEnrichCartItemsReturnsEmptyArrayWhenNoItems(): void
    {
        $result = $this->service->enrichCartItems([]);

        $this->assertSame([], $result);
    }

    public function testEnrichCartItemsDelegatesToProductRepo(): void
    {
        $this->productRepoStub->method('findItemsByIds')->willReturn([['id' => 1, 'name' => 'Pass']]);

        $result = $this->service->enrichCartItems([1 => 2]);

        $this->assertCount(1, $result);
    }
}
