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
}
