<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? ReceptionService: getDashboard, addItem y processPayment.
 * ¿Qué me quieres demostrar? Que el servicio valida estado de reserva, pertenencia al café y delega a repos.
 * ¿Qué va a fallar en este test si se cambia el código? Si addItem/processPayment dejan de validar estado o café.
 */

namespace Tests\Unit\Services;

use App\Domain\DTO\ProductDTO;
use App\Domain\DTO\ReservationDTO;
use App\Repositories\Contracts\CafeRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\ReservationItemRepositoryInterface;
use App\Repositories\Contracts\ReservationRepositoryInterface;
use App\Repositories\Contracts\TrackerRepositoryInterface;
use App\Services\ReceptionService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReceptionService::class)]
final class ReceptionServiceTest extends TestCase
{
    private ReservationRepositoryInterface $reservationRepoStub;
    private TrackerRepositoryInterface $trackerRepoStub;
    private CafeRepositoryInterface $cafeRepoStub;
    private ReservationItemRepositoryInterface $itemRepoStub;
    private ProductRepositoryInterface $productRepoStub;
    private ReceptionService $service;

    protected function setUp(): void
    {
        $this->reservationRepoStub = $this->createStub(ReservationRepositoryInterface::class);
        $this->trackerRepoStub = $this->createStub(TrackerRepositoryInterface::class);
        $this->cafeRepoStub = $this->createStub(CafeRepositoryInterface::class);
        $this->itemRepoStub = $this->createStub(ReservationItemRepositoryInterface::class);
        $this->productRepoStub = $this->createStub(ProductRepositoryInterface::class);

        $this->service = new ReceptionService(
            $this->reservationRepoStub,
            $this->trackerRepoStub,
            $this->cafeRepoStub,
            null,
            $this->itemRepoStub,
            $this->productRepoStub
        );
    }

    public function testGetPendingArrivalsReturnsArray(): void
    {
        $this->reservationRepoStub->method('findByCafeAndDate')->willReturn([]);

        $result = $this->service->getPendingArrivals(1);

        $this->assertIsArray($result);
    }

    public function testGetActiveGroupsReturnsArray(): void
    {
        $this->reservationRepoStub->method('findActiveByCafe')->willReturn([]);

        $result = $this->service->getActiveGroups(1);

        $this->assertIsArray($result);
    }

    public function testGetAvailableTrackersReturnsArray(): void
    {
        $this->trackerRepoStub->method('findAvailable')->willReturn([]);

        $result = $this->service->getAvailableTrackers(1);

        $this->assertIsArray($result);
    }

    public function testGetPendingArrivalsFiltersToConfirmedOnly(): void
    {
        $this->reservationRepoStub->method('findByCafeAndDate')->willReturn([
            ['id' => 1, 'status' => 'confirmed'],
            ['id' => 2, 'status' => 'pending'],
            ['id' => 3, 'status' => 'cancelled'],
        ]);

        $result = $this->service->getPendingArrivals(1);

        $this->assertCount(1, $result);
    }

    public function testGetPendingArrivalsReturnsEmptyWhenNoConfirmed(): void
    {
        $this->reservationRepoStub->method('findByCafeAndDate')->willReturn([
            ['id' => 1, 'status' => 'pending'],
        ]);

        $result = $this->service->getPendingArrivals(1);

        $this->assertCount(0, $result);
    }

    public function testGetDashboardContainsExpectedKeys(): void
    {
        $this->reservationRepoStub->method('findByCafeAndDate')->willReturn([]);
        $this->reservationRepoStub->method('findActiveByCafe')->willReturn([]);
        $this->reservationRepoStub->method('getDailyStats')->willReturn([]);
        $this->trackerRepoStub->method('findAvailable')->willReturn([]);
        $this->cafeRepoStub->method('findById')->willReturn(null);

        $result = $this->service->getDashboard(1);

        $this->assertArrayHasKey('pending_arrivals', $result);
        $this->assertArrayHasKey('active_groups', $result);
        $this->assertArrayHasKey('available_trackers', $result);
        $this->assertArrayHasKey('capacity', $result);
        $this->assertArrayHasKey('stats', $result);
    }

    public function testGetActiveGroupsEnrichesGroupWithCheckinData(): void
    {
        $checkinAt = \date('Y-m-d H:i:s', \time() - 1800); // 30 min ago
        $this->reservationRepoStub->method('findActiveByCafe')->willReturn([
            ['id' => 1, 'status' => 'active', 'guests' => 2, 'check_in_at' => $checkinAt, 'pass_duration_minutes' => 60],
        ]);

        $result = $this->service->getActiveGroups(1);

        $this->assertCount(1, $result);
        $this->assertArrayHasKey('elapsed_minutes', $result[0]);
        $this->assertArrayHasKey('remaining_minutes', $result[0]);
        $this->assertArrayHasKey('is_overtime', $result[0]);
    }

    public function testGetActiveGroupsWithNoCheckinDoesNotEnrich(): void
    {
        $this->reservationRepoStub->method('findActiveByCafe')->willReturn([
            ['id' => 1, 'status' => 'active', 'guests' => 2, 'check_in_at' => null],
        ]);

        $result = $this->service->getActiveGroups(1);

        $this->assertCount(1, $result);
        $this->assertArrayNotHasKey('elapsed_minutes', $result[0]);
    }

    public function testGetCapacityInfoReturnsExpectedStructure(): void
    {
        $cafe = new \App\Domain\DTO\CafeDTO(
            id: 1,
            slug: 'test',
            name: 'Test',
            japanese_name: null,
            description: null,
            location: 'loc',
            category: 'cat',
            animal_type: 'cat',
            price_per_hour: 10.0,
            capacity_max: 20,
            rating_avg: 4.5,
            opening_time: '09:00',
            closing_time: '18:00',
            timezone: 'UTC',
            is_active: true,
            has_reservations: true,
            image_url: null
        );
        $this->cafeRepoStub->method('findById')->willReturn($cafe);
        $this->reservationRepoStub->method('findActiveByCafe')->willReturn([
            ['guests' => 3],
            ['guests' => 2],
        ]);

        $result = $this->service->getCapacityInfo(1);

        $this->assertArrayHasKey('max', $result);
        $this->assertArrayHasKey('current', $result);
        $this->assertArrayHasKey('available', $result);
        $this->assertArrayHasKey('percentage', $result);
        $this->assertArrayHasKey('is_full', $result);
        $this->assertSame(20, $result['max']);
        $this->assertSame(5, $result['current']);
        $this->assertSame(15, $result['available']);
        $this->assertFalse($result['is_full']);
    }

    public function testGetCapacityInfoHandlesNullCafe(): void
    {
        $this->cafeRepoStub->method('findById')->willReturn(null);
        $this->reservationRepoStub->method('findActiveByCafe')->willReturn([]);

        $result = $this->service->getCapacityInfo(1);

        $this->assertSame(0, $result['max']);
        $this->assertSame(0, $result['percentage']);
    }

    public function testGetCapacityInfoIsFullWhenAtCapacity(): void
    {
        $cafe = new \App\Domain\DTO\CafeDTO(
            id: 1,
            slug: 'test',
            name: 'Test',
            japanese_name: null,
            description: null,
            location: 'loc',
            category: 'cat',
            animal_type: 'cat',
            price_per_hour: 10.0,
            capacity_max: 5,
            rating_avg: 4.5,
            opening_time: '09:00',
            closing_time: '18:00',
            timezone: 'UTC',
            is_active: true,
            has_reservations: true,
            image_url: null
        );
        $this->cafeRepoStub->method('findById')->willReturn($cafe);
        $this->reservationRepoStub->method('findActiveByCafe')->willReturn([
            ['guests' => 5],
        ]);

        $result = $this->service->getCapacityInfo(1);

        $this->assertTrue($result['is_full']);
        $this->assertSame(0, $result['available']);
    }

    public function testGetDailyStatsReturnsArrayFromRepo(): void
    {
        $this->reservationRepoStub->method('getDailyStats')->willReturn(['total' => 10, 'completed' => 8]);

        $result = $this->service->getDailyStats(1, '2025-12-01');

        $this->assertSame(['total' => 10, 'completed' => 8], $result);
    }

    public function testAssignTrackerDelegatesToRepo(): void
    {
        $this->reservationRepoStub->method('assignTracker')->willReturn(true);

        $result = $this->service->assignTracker(1, 2);

        $this->assertTrue($result);
    }

    public function testCompleteProtocolDelegatesToRepo(): void
    {
        $this->reservationRepoStub->method('completeProtocol')->willReturn(true);

        $result = $this->service->completeProtocol(1, 'hygiene');

        $this->assertTrue($result);
    }

    public function testGetProtocolStatusFailsWhenReservationNotFound(): void
    {
        $this->reservationRepoStub->method('findWithOperationalData')->willReturn(null);

        $result = $this->service->getProtocolStatus(999);

        $this->assertFalse($result->ok);
        $this->assertSame('not_found', $result->code);
    }

    public function testGetProtocolStatusReturnsAllProtocolFields(): void
    {
        $this->reservationRepoStub->method('findWithOperationalData')->willReturn([
            'protocol_hygiene' => true,
            'protocol_briefing' => true,
            'protocol_shoes' => true,
        ]);

        $result = $this->service->getProtocolStatus(1);

        $this->assertTrue($result->ok);
        $this->assertTrue($result->data['hygiene']);
        $this->assertTrue($result->data['briefing']);
        $this->assertTrue($result->data['shoes']);
        $this->assertTrue($result->data['all_complete']);
    }

    public function testGetProtocolStatusAllCompleteIsFalseWhenAnyMissing(): void
    {
        $this->reservationRepoStub->method('findWithOperationalData')->willReturn([
            'protocol_hygiene' => true,
            'protocol_briefing' => false,
            'protocol_shoes' => true,
        ]);

        $result = $this->service->getProtocolStatus(1);

        $this->assertTrue($result->ok);
        $this->assertFalse($result->data['all_complete']);
    }

    public function testGetActiveGroupsIsOvertimeWhenElapsedExceedsDuration(): void
    {
        $checkinAt = \date('Y-m-d H:i:s', \time() - 4800); // 80 min ago
        $this->reservationRepoStub->method('findActiveByCafe')->willReturn([
            ['id' => 1, 'check_in_at' => $checkinAt, 'pass_duration_minutes' => 60],
        ]);

        $result = $this->service->getActiveGroups(1);

        $this->assertTrue($result[0]['is_overtime']);
        $this->assertGreaterThan(0, $result[0]['overtime_minutes']);
    }

    // ─────────────────────────────────────────────────────────────
    // addItem
    // ─────────────────────────────────────────────────────────────

    public function testAddItemSuccessReturnsOk(): void
    {
        $reservation = new \App\Domain\DTO\ReservationDTO(
            id: 10,
            uuid: 'uuid-1',
            cafe_id: 1,
            user_id: 5,
            date: '2025-12-01',
            time: '10:00',
            guest_count: 2,
            status: 'active',
            time_slot_id: null,
            pass_name: null,
            check_in_at: '2025-12-01 10:00:00',
            check_out_at: null,
            final_amount: null,
            payment_status: null,
            payment_method: null,
            notes: null
        );

        $product = new \App\Domain\DTO\ProductDTO(
            id: 7,
            name: 'Onigiri',
            slug: 'onigiri',
            description: null,
            price: 800.0,
            category_id: 2,
            category_name: 'Snacks',
            allergens: [],
            is_active: true,
            image_url: null,
            product_type: 'food',
            min_pax: null,
            max_pax: null,
            duration_minutes: null,
            attributes: null,
            target_cafe_types: null,
            target_animal_types: null,
            stock_quantity: null
        );

        $this->reservationRepoStub->method('findById')->willReturn($reservation);
        $this->productRepoStub->method('findById')->willReturn($product);
        $this->itemRepoStub->method('add')->willReturn(42);

        $result = $this->service->addItem(10, 7, 2, 1);

        $this->assertTrue($result->ok);
        $this->assertSame(42, $result->data['item_id']);
    }

    public function testAddItemFailsWhenReservationNotFound(): void
    {
        $this->reservationRepoStub->method('findById')->willReturn(null);

        $result = $this->service->addItem(99, 7, 1, 1);

        $this->assertFalse($result->ok);
        $this->assertSame('not_found', $result->code);
    }

    public function testAddItemFailsWhenReservationNotActive(): void
    {
        $reservation = new \App\Domain\DTO\ReservationDTO(
            id: 10,
            uuid: 'uuid-1',
            cafe_id: 1,
            user_id: 5,
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
            notes: null
        );

        $this->reservationRepoStub->method('findById')->willReturn($reservation);

        $result = $this->service->addItem(10, 7, 1, 1);

        $this->assertFalse($result->ok);
        $this->assertSame('invalid_state', $result->code);
    }

    public function testAddItemFailsWhenCafeMismatch(): void
    {
        $reservation = new \App\Domain\DTO\ReservationDTO(
            id: 10,
            uuid: 'uuid-1',
            cafe_id: 2,
            user_id: 5,
            date: '2025-12-01',
            time: '10:00',
            guest_count: 2,
            status: 'active',
            time_slot_id: null,
            pass_name: null,
            check_in_at: '2025-12-01 10:00:00',
            check_out_at: null,
            final_amount: null,
            payment_status: null,
            payment_method: null,
            notes: null
        );

        $this->reservationRepoStub->method('findById')->willReturn($reservation);

        $result = $this->service->addItem(10, 7, 1, 1); // cafe_id=1 but reservation is cafe_id=2

        $this->assertFalse($result->ok);
        $this->assertSame('cafe_mismatch', $result->code);
    }

    public function testAddItemFailsWhenProductNotFound(): void
    {
        $reservation = new \App\Domain\DTO\ReservationDTO(
            id: 10,
            uuid: 'uuid-1',
            cafe_id: 1,
            user_id: 5,
            date: '2025-12-01',
            time: '10:00',
            guest_count: 2,
            status: 'active',
            time_slot_id: null,
            pass_name: null,
            check_in_at: '2025-12-01 10:00:00',
            check_out_at: null,
            final_amount: null,
            payment_status: null,
            payment_method: null,
            notes: null
        );

        $this->reservationRepoStub->method('findById')->willReturn($reservation);
        $this->productRepoStub->method('findById')->willReturn(null);

        $result = $this->service->addItem(10, 999, 1, 1);

        $this->assertFalse($result->ok);
        $this->assertSame('product_unavailable', $result->code);
    }

    public function testAddItemFailsWhenProductIsAPass(): void
    {
        $reservation = new \App\Domain\DTO\ReservationDTO(
            id: 10,
            uuid: 'uuid-1',
            cafe_id: 1,
            user_id: 5,
            date: '2025-12-01',
            time: '10:00',
            guest_count: 2,
            status: 'active',
            time_slot_id: null,
            pass_name: null,
            check_in_at: '2025-12-01 10:00:00',
            check_out_at: null,
            final_amount: null,
            payment_status: null,
            payment_method: null,
            notes: null
        );

        $product = new \App\Domain\DTO\ProductDTO(
            id: 1,
            name: 'Pass Rápido',
            slug: 'pass-rapido',
            description: null,
            price: 1500.0,
            category_id: 1,
            category_name: 'Pases',
            allergens: [],
            is_active: true,
            image_url: null,
            product_type: 'pass',
            min_pax: null,
            max_pax: null,
            duration_minutes: 60,
            attributes: null,
            target_cafe_types: null,
            target_animal_types: null,
            stock_quantity: null
        );

        $this->reservationRepoStub->method('findById')->willReturn($reservation);
        $this->productRepoStub->method('findById')->willReturn($product);

        $result = $this->service->addItem(10, 1, 1, 1);

        $this->assertFalse($result->ok);
        $this->assertSame('invalid_product_type', $result->code);
    }

    public function testAddItemFailsWhenQuantityIsZero(): void
    {
        $result = $this->service->addItem(10, 7, 0, 1);

        $this->assertFalse($result->ok);
        $this->assertSame('invalid_params', $result->code);
    }

    // ─────────────────────────────────────────────────────────────
    // processPayment
    // ─────────────────────────────────────────────────────────────

    public function testProcessPaymentFailsWhenReservationNotFound(): void
    {
        $this->reservationRepoStub->method('findByIdWithCafeDetails')->willReturn(null);

        $result = $this->service->processPayment(99, 'efectivo', 1);

        $this->assertFalse($result->ok);
        $this->assertSame('not_found', $result->code);
    }

    public function testProcessPaymentFailsWhenNotActive(): void
    {
        $this->reservationRepoStub->method('findByIdWithCafeDetails')->willReturn([
            'id' => 10,
            'cafe_id' => 1,
            'status' => 'completed',
            'guest_count' => 2,
            'pass_unit_price' => 1500.0,
        ]);

        $result = $this->service->processPayment(10, 'efectivo', 1);

        $this->assertFalse($result->ok);
        $this->assertSame('invalid_state', $result->code);
    }

    public function testProcessPaymentFailsWhenCafeMismatch(): void
    {
        $this->reservationRepoStub->method('findByIdWithCafeDetails')->willReturn([
            'id' => 10,
            'cafe_id' => 2,
            'status' => 'active',
            'guest_count' => 2,
            'pass_unit_price' => 1500.0,
        ]);

        $result = $this->service->processPayment(10, 'efectivo', 1); // caller cafe_id=1, reservation cafe_id=2

        $this->assertFalse($result->ok);
        $this->assertSame('cafe_mismatch', $result->code);
    }

    public function testProcessPaymentFailsWhenParamsInvalid(): void
    {
        $result = $this->service->processPayment(0, 'efectivo', 1);

        $this->assertFalse($result->ok);
        $this->assertSame('invalid_params', $result->code);
    }

    public function testProcessPaymentFailsWhenPaymentMethodEmpty(): void
    {
        $result = $this->service->processPayment(10, '', 1);

        $this->assertFalse($result->ok);
        $this->assertSame('invalid_params', $result->code);
    }
}
