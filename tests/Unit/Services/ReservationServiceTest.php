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
use App\Services\Contracts\SettingsServiceInterface;
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
        $this->cafeRepoStub = $this->createStub(CafeRepositoryInterface::class);
        $this->productRepoStub = $this->createStub(ProductRepositoryInterface::class);
        $this->invoiceServiceStub = $this->createStub(InvoicePDFServiceInterface::class);
        $this->emailServiceStub = $this->createStub(EmailServiceInterface::class);

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
            'user_id' => 1,
            'cafe_id' => 1,
            'pass_product_id' => 1,
            'date' => '20-12-2025',
            'time' => '10:00',
            'guests' => 2,
        ]);

        $this->assertFalse($result->ok);
        $this->assertSame('validation_error', $result->code);
    }

    public function testCreateReturnsFailWhenTimeFormatInvalid(): void
    {
        $result = $this->service->create([
            'user_id' => 1,
            'cafe_id' => 1,
            'pass_product_id' => 1,
            'date' => '2099-12-20',
            'time' => '9am',
            'guests' => 2,
        ]);

        $this->assertFalse($result->ok);
        $this->assertSame('validation_error', $result->code);
    }

    public function testCreateReturnsFailWhenGuestsOutOfRange(): void
    {
        $result = $this->service->create([
            'user_id' => 1,
            'cafe_id' => 1,
            'pass_product_id' => 1,
            'date' => '2099-12-20',
            'time' => '10:00',
            'guests' => 0,
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
        $this->reservationRepoStub->method('findById')->willReturn($this->makeReservationDto());
        $this->reservationRepoStub->method('cancel')->willReturn(false);

        $result = $this->service->cancel(999, 1);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('cancelar', $result->error);
    }

    public function testCancelSucceeds(): void
    {
        $this->reservationRepoStub->method('findById')->willReturn($this->makeReservationDto());
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
            pass_duration_minutes: null,
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
        $this->productRepoStub->method('findItemsByIds')->willReturn([['id' => 1, 'name' => 'Pass', 'price' => '5.00']]);

        $result = $this->service->enrichCartItems([1 => 2]);

        $this->assertCount(1, $result);
        $this->assertSame(2, $result[0]['qty']);
        $this->assertSame(10.0, $result[0]['subtotal']);
    }

    // ─────────────────────────────────────────────────────────────
    // create() — rutas de error post-validación de formato
    // ─────────────────────────────────────────────────────────────

    public function testCreateReturnsFailWhenPastDate(): void
    {
        $result = $this->service->create([
            'user_id' => 1,
            'cafe_id' => 1,
            'pass_product_id' => 1,
            'date' => '2000-01-01',
            'time' => '10:00',
            'guests' => 2,
        ]);

        $this->assertFalse($result->ok);
        $this->assertSame('past_date', $result->code);
    }

    public function testCreateReturnsNotFoundWhenCafeDoesNotExist(): void
    {
        $this->cafeRepoStub->method('findById')->willReturn(null);

        $result = $this->service->create([
            'user_id' => 1,
            'cafe_id' => 99,
            'pass_product_id' => 1,
            'date' => '2099-12-01',
            'time' => '10:00',
            'guests' => 2,
        ]);

        $this->assertFalse($result->ok);
        $this->assertSame('not_found', $result->code);
    }

    public function testCreateReturnsBusinessRuleWhenCafeNotActive(): void
    {
        $cafe = $this->makeCafe(is_active: false, has_reservations: true);
        $this->cafeRepoStub->method('findById')->willReturn($cafe);

        $result = $this->service->create([
            'user_id' => 1,
            'cafe_id' => 1,
            'pass_product_id' => 1,
            'date' => '2099-12-01',
            'time' => '10:00',
            'guests' => 2,
        ]);

        $this->assertFalse($result->ok);
        $this->assertSame('cafe_reservations_disabled', $result->code);
    }

    public function testCreateReturnsBusinessRuleWhenCafeNotAcceptingReservations(): void
    {
        $cafe = $this->makeCafe(is_active: true, has_reservations: false);
        $this->cafeRepoStub->method('findById')->willReturn($cafe);

        $result = $this->service->create([
            'user_id' => 1,
            'cafe_id' => 1,
            'pass_product_id' => 1,
            'date' => '2099-12-01',
            'time' => '10:00',
            'guests' => 2,
        ]);

        $this->assertFalse($result->ok);
        $this->assertSame('cafe_reservations_disabled', $result->code);
    }

    public function testCreateReturnsNotFoundWhenPassDoesNotExist(): void
    {
        $cafe = $this->makeCafe();
        $this->cafeRepoStub->method('findById')->willReturn($cafe);
        $this->productRepoStub->method('findById')->willReturn(null);

        $result = $this->service->create([
            'user_id' => 1,
            'cafe_id' => 1,
            'pass_product_id' => 99,
            'date' => '2099-12-01',
            'time' => '10:00',
            'guests' => 2,
        ]);

        $this->assertFalse($result->ok);
        $this->assertSame('not_found', $result->code);
    }

    public function testCreateReturnsBusinessRuleWhenPassNotActive(): void
    {
        $cafe = $this->makeCafe();
        $pass = $this->makePass(is_active: false);
        $this->cafeRepoStub->method('findById')->willReturn($cafe);
        $this->productRepoStub->method('findById')->willReturn($pass);

        $result = $this->service->create([
            'user_id' => 1,
            'cafe_id' => 1,
            'pass_product_id' => 1,
            'date' => '2099-12-01',
            'time' => '10:00',
            'guests' => 2,
        ]);

        $this->assertFalse($result->ok);
        $this->assertSame('pass_not_available', $result->code);
    }

    public function testCreateReturnsBusinessRuleWhenProductIsNotAPass(): void
    {
        $cafe = $this->makeCafe();
        $pass = $this->makePass(product_type: 'food');
        $this->cafeRepoStub->method('findById')->willReturn($cafe);
        $this->productRepoStub->method('findById')->willReturn($pass);

        $result = $this->service->create([
            'user_id' => 1,
            'cafe_id' => 1,
            'pass_product_id' => 1,
            'date' => '2099-12-01',
            'time' => '10:00',
            'guests' => 2,
        ]);

        $this->assertFalse($result->ok);
        $this->assertSame('product_not_available', $result->code);
    }

    public function testCreateReturnsBusinessRuleWhenGuestsBelowMinPax(): void
    {
        $cafe = $this->makeCafe();
        $pass = $this->makePass(min_pax: 3);
        $this->cafeRepoStub->method('findById')->willReturn($cafe);
        $this->productRepoStub->method('findById')->willReturn($pass);

        $result = $this->service->create([
            'user_id' => 1,
            'cafe_id' => 1,
            'pass_product_id' => 1,
            'date' => '2099-12-01',
            'time' => '10:00',
            'guests' => 1,
        ]);

        $this->assertFalse($result->ok);
        $this->assertSame('minimum_guests_required', $result->code);
    }

    public function testCreateReturnsBusinessRuleWhenGuestsExceedMaxPax(): void
    {
        $cafe = $this->makeCafe();
        $pass = $this->makePass(max_pax: 2);
        $this->cafeRepoStub->method('findById')->willReturn($cafe);
        $this->productRepoStub->method('findById')->willReturn($pass);

        $result = $this->service->create([
            'user_id' => 1,
            'cafe_id' => 1,
            'pass_product_id' => 1,
            'date' => '2099-12-01',
            'time' => '10:00',
            'guests' => 5,
        ]);

        $this->assertFalse($result->ok);
        $this->assertSame('maximum_guests_exceeded', $result->code);
    }

    public function testCreateReturnsBusinessRuleWhenTimeBeforeOpening(): void
    {
        $cafe = $this->makeCafe(opening_time: '12:00', closing_time: '22:00');
        $pass = $this->makePass();
        $this->cafeRepoStub->method('findById')->willReturn($cafe);
        $this->productRepoStub->method('findById')->willReturn($pass);

        $result = $this->service->create([
            'user_id' => 1,
            'cafe_id' => 1,
            'pass_product_id' => 1,
            'date' => '2099-12-01',
            'time' => '09:00',
            'guests' => 2,
        ]);

        $this->assertFalse($result->ok);
        $this->assertSame('cafe_not_open', $result->code);
    }

    public function testCreateReturnsBusinessRuleWhenInsufficientTimeBeforeClose(): void
    {
        // Pass de 120 min, cierre a 22:00 → inicio a 21:30 es demasiado tarde
        $cafe = $this->makeCafe(opening_time: '10:00', closing_time: '22:00');
        $pass = $this->makePass(duration_minutes: 120);
        $this->cafeRepoStub->method('findById')->willReturn($cafe);
        $this->productRepoStub->method('findById')->willReturn($pass);

        $result = $this->service->create([
            'user_id' => 1,
            'cafe_id' => 1,
            'pass_product_id' => 1,
            'date' => '2099-12-01',
            'time' => '21:30',
            'guests' => 2,
        ]);

        $this->assertFalse($result->ok);
        $this->assertSame('insufficient_time_before_close', $result->code);
    }

    public function testCreateReturnsNoCapacityCodeWhenCafeFull(): void
    {
        $cafe = $this->makeCafe();
        $pass = $this->makePass();
        $this->cafeRepoStub->method('findById')->willReturn($cafe);
        $this->cafeRepoStub->method('hasAvailableCapacity')->willReturn(false);
        $this->productRepoStub->method('findById')->willReturn($pass);

        $result = $this->service->create([
            'user_id' => 1,
            'cafe_id' => 1,
            'pass_product_id' => 1,
            'date' => '2099-12-01',
            'time' => '11:00',
            'guests' => 2,
        ]);

        $this->assertFalse($result->ok);
        $this->assertSame('cafe_no_capacity', $result->code);
    }

    public function testCreateReturnsDuplicateCodeWhenReservationExists(): void
    {
        $cafe = $this->makeCafe();
        $pass = $this->makePass();
        $this->cafeRepoStub->method('findById')->willReturn($cafe);
        $this->cafeRepoStub->method('hasAvailableCapacity')->willReturn(true);
        $this->productRepoStub->method('findById')->willReturn($pass);
        $this->reservationRepoStub->method('existsForUserAndDateTime')->willReturn(true);

        $result = $this->service->create([
            'user_id' => 1,
            'cafe_id' => 1,
            'pass_product_id' => 1,
            'date' => '2099-12-01',
            'time' => '11:00',
            'guests' => 2,
        ]);

        $this->assertFalse($result->ok);
        $this->assertSame('duplicate_reservation', $result->code);
    }

    // ─────────────────────────────────────────────────────────────
    // adminConfirm() — rutas adicionales
    // ─────────────────────────────────────────────────────────────

    public function testAdminConfirmSucceedsWhenPending(): void
    {
        $dto = $this->makeReservationDto(status: 'pending');
        $this->reservationRepoStub->method('findById')->willReturn($dto);
        $this->reservationRepoStub->method('updateStatus')->willReturn(true);

        $result = $this->service->adminConfirm(1);

        $this->assertTrue($result->ok);
    }

    public function testAdminConfirmFailsOnInvalidTransition(): void
    {
        $dto = $this->makeReservationDto(status: 'cancelled');
        $this->reservationRepoStub->method('findById')->willReturn($dto);

        $result = $this->service->adminConfirm(1);

        $this->assertFalse($result->ok);
        $this->assertSame('invalid_transition', $result->code);
    }

    public function testAdminConfirmFailsWhenUpdateFails(): void
    {
        $dto = $this->makeReservationDto(status: 'pending');
        $this->reservationRepoStub->method('findById')->willReturn($dto);
        $this->reservationRepoStub->method('updateStatus')->willReturn(false);

        $result = $this->service->adminConfirm(1);

        $this->assertFalse($result->ok);
    }

    public function testAdminCancelFailsOnInvalidTransitionFromAlreadyCancelled(): void
    {
        $dto = $this->makeReservationDto(status: 'cancelled');
        $this->reservationRepoStub->method('findById')->willReturn($dto);

        $result = $this->service->adminCancel(1);

        $this->assertFalse($result->ok);
    }

    public function testGetByUserWithStatusFilterDelegates(): void
    {
        $this->reservationRepoStub->method('findByUser')->willReturn([]);

        $result = $this->service->getByUser(1, 'confirmed');

        $this->assertIsArray($result);
    }

    public function testGetUpcomingWithCustomLimitDelegates(): void
    {
        $this->reservationRepoStub->method('findUpcomingByUser')->willReturn([]);

        $result = $this->service->getUpcoming(1, 3);

        $this->assertIsArray($result);
    }

    // ─────────────────────────────────────────────────────────────
    // Settings — max_guests_per_reservation
    // ─────────────────────────────────────────────────────────────

    public function testCreateFailsWhenGuestsExceedsSettingsMaxGuests(): void
    {
        $settingsStub = $this->createStub(SettingsServiceInterface::class);
        $settingsStub->method('get')->willReturnMap([['max_guests_per_reservation', '10', '5']]);

        $service = new ReservationService(
            $this->reservationRepoStub,
            $this->cafeRepoStub,
            $this->productRepoStub,
            $this->invoiceServiceStub,
            $this->emailServiceStub,
            null,
            null,
            null,
            $settingsStub,
        );

        $result = $service->create([
            'user_id' => 1,
            'cafe_id' => 1,
            'pass_product_id' => 1,
            'date' => '2099-12-01',
            'time' => '10:00',
            'guests' => 6,
        ]);

        $this->assertFalse($result->ok);
        $this->assertSame('validation_error', $result->code);
    }

    // ─────────────────────────────────────────────────────────────
    // cancel() — cálculo de tarifa de cancelación
    // ─────────────────────────────────────────────────────────────

    public function testCancelWithZeroFeeRefundsFullAmount(): void
    {
        $settingsStub = $this->createStub(SettingsServiceInterface::class);
        $settingsStub->method('get')->willReturnMap([['cancellation_fee_percentage', '0', '0']]);

        $service = new ReservationService(
            $this->reservationRepoStub,
            $this->cafeRepoStub,
            $this->productRepoStub,
            $this->invoiceServiceStub,
            $this->emailServiceStub,
            null,
            null,
            null,
            $settingsStub,
        );

        $reservation = $this->makeReservationDto(final_amount: 2000.0);
        $this->reservationRepoStub->method('findById')->willReturn($reservation);
        $this->reservationRepoStub->method('cancel')->willReturn(true);

        $result = $service->cancel(1, 1);

        $this->assertTrue($result->ok);
        $this->assertSame(2000.0, $result->data['refund_amount']);
    }

    public function testCancelWithFiftyPercentFeeRefundsHalf(): void
    {
        $settingsStub = $this->createStub(SettingsServiceInterface::class);
        $settingsStub->method('get')->willReturnMap([['cancellation_fee_percentage', '0', '50']]);

        $service = new ReservationService(
            $this->reservationRepoStub,
            $this->cafeRepoStub,
            $this->productRepoStub,
            $this->invoiceServiceStub,
            $this->emailServiceStub,
            null,
            null,
            null,
            $settingsStub,
        );

        $reservation = $this->makeReservationDto(final_amount: 2000.0);
        $this->reservationRepoStub->method('findById')->willReturn($reservation);
        $this->reservationRepoStub->method('cancel')->willReturn(true);

        $result = $service->cancel(1, 1);

        $this->assertTrue($result->ok);
        $this->assertSame(1000.0, $result->data['refund_amount']);
    }

    public function testCancelWithNullFinalAmountRefundsZero(): void
    {
        $this->reservationRepoStub->method('findById')->willReturn($this->makeReservationDto(final_amount: null));
        $this->reservationRepoStub->method('cancel')->willReturn(true);

        $result = $this->service->cancel(1, 1);

        $this->assertTrue($result->ok);
        $this->assertSame(0.0, $result->data['refund_amount']);
    }

    public function testCancelWithHundredPercentFeeRefundsZero(): void
    {
        $settingsStub = $this->createStub(SettingsServiceInterface::class);
        $settingsStub->method('get')->willReturnMap([['cancellation_fee_percentage', '0', '100']]);

        $service = new ReservationService(
            $this->reservationRepoStub,
            $this->cafeRepoStub,
            $this->productRepoStub,
            $this->invoiceServiceStub,
            $this->emailServiceStub,
            null,
            null,
            null,
            $settingsStub,
        );

        $reservation = $this->makeReservationDto(final_amount: 2000.0);
        $this->reservationRepoStub->method('findById')->willReturn($reservation);
        $this->reservationRepoStub->method('cancel')->willReturn(true);

        $result = $service->cancel(1, 1);

        $this->assertTrue($result->ok);
        $this->assertSame(0.0, $result->data['refund_amount']);
    }

    public function testCancelFailsWhenReservationBelongsToDifferentUser(): void
    {
        $reservation = new \App\Domain\DTO\ReservationDTO(
            id: 1,
            uuid: 'abc',
            cafe_id: 1,
            user_id: 999,
            date: '2099-12-01',
            time: '10:00',
            guest_count: 2,
            status: 'confirmed',
            time_slot_id: null,
            pass_name: null,
            pass_duration_minutes: null,
            check_in_at: null,
            check_out_at: null,
            final_amount: null,
            payment_status: null,
            payment_method: null,
            notes: null,
        );
        $this->reservationRepoStub->method('findById')->willReturn($reservation);

        $result = $this->service->cancel(1, 1); // userId=1 but reservation belongs to userId=999

        $this->assertFalse($result->ok);
        $this->assertSame('not_found', $result->code);
    }

    // ─────────────────────────────────────────────────────────────
    // Helpers de fábrica
    // ─────────────────────────────────────────────────────────────

    private function makeCafe(
        bool $is_active = true,
        bool $has_reservations = true,
        string $opening_time = '10:00',
        string $closing_time = '22:00',
        string $category = 'cat',
        string $animal_type = 'cat',
    ): \App\Domain\DTO\CafeDTO {
        return new \App\Domain\DTO\CafeDTO(
            id: 1,
            slug: 'test-cafe',
            name: 'Test Café',
            japanese_name: null,
            description: null,
            location: 'Tokyo',
            category: $category,
            animal_type: $animal_type,
            price_per_hour: 10.0,
            capacity_max: 20,
            rating_avg: 5.0,
            opening_time: $opening_time,
            closing_time: $closing_time,
            timezone: 'UTC',
            is_active: $is_active,
            has_reservations: $has_reservations,
            image_url: null,
        );
    }

    private function makePass(
        bool $is_active = true,
        string $product_type = 'pass',
        int $duration_minutes = 90,
        ?int $min_pax = 1,
        ?int $max_pax = 10,
        ?string $attributes = null,
        ?string $target_cafe_types = null,
        ?string $target_animal_types = null,
    ): \App\Domain\DTO\ProductDTO {
        return new \App\Domain\DTO\ProductDTO(
            id: 1,
            name: 'Standard Pass',
            slug: 'standard-pass',
            description: null,
            price: 25.0,
            category_id: 1,
            category_name: 'Passes',
            allergens: [],
            is_active: $is_active,
            image_url: null,
            product_type: $product_type,
            min_pax: $min_pax,
            max_pax: $max_pax,
            duration_minutes: $duration_minutes,
            attributes: $attributes,
            target_cafe_types: $target_cafe_types,
            target_animal_types: $target_animal_types,
            stock_quantity: null,
        );
    }

    private function makeReservationDto(
        string $status = 'confirmed',
        ?float $final_amount = null,
    ): \App\Domain\DTO\ReservationDTO {
        return new \App\Domain\DTO\ReservationDTO(
            id: 1,
            uuid: 'abc',
            cafe_id: 1,
            user_id: 1,
            date: '2099-12-01',
            time: '10:00',
            guest_count: 2,
            status: $status,
            time_slot_id: null,
            pass_name: null,
            pass_duration_minutes: null,
            check_in_at: null,
            check_out_at: null,
            final_amount: $final_amount,
            payment_status: null,
            payment_method: null,
            notes: null,
        );
    }
}
