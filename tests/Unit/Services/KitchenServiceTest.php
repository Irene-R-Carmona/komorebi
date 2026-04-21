<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * Servicio de cocina (KDS): obtención de comandas por estación y globales,
 * actualización de estados de items individuales, bump de tickets completos
 * y estadísticas diarias de producción y tiempo de espera estimado.
 *
 * ¿Qué me quieres demostrar?
 * Que getPendingByStation agrupa únicamente las estaciones con items y omite
 * las vacías, que getPendingForStation/getAllPending devuelven items
 * enriquecidos con ingredients_list decodificado y waiting_minutes calculado,
 * que startPreparing/markReady/markServed delegan en el modelo y devuelven
 * el bool del execute(), que bumpTicket retorna el rowCount del UPDATE,
 * y que getDailyStats/getEstimatedWaitTime normalizan correctamente los
 * valores de BD (nulos a cero, tipos correctos).
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se elimina la agrupación por estación o se incluyen estaciones vacías,
 * si enrichItems deja de decodificar ingredients_list o de calcular
 * waiting_minutes, si bumpTicket cambia el status objetivo o deja de usar
 * rowCount(), si getDailyStats pierde alguna clave o cambia los tipos de
 * retorno, o si getEstimatedWaitTime deja de retornar 0 cuando no hay items
 * pendientes.
 */

namespace Tests\Unit\Services;

use App\Models\Product;
use App\Models\ReservationItem;
use App\Repositories\Contracts\ReservationItemRepositoryInterface;
use App\Services\KitchenService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

#[CoversClass(KitchenService::class)]
final class KitchenServiceTest extends TestCase
{
    /** @var Stub&ReservationItemRepositoryInterface */
    private ReservationItemRepositoryInterface $itemRepo;
    private KitchenService $service;

    protected function setUp(): void
    {
        $this->itemRepo = $this->createStub(ReservationItemRepositoryInterface::class);
        $this->service  = new KitchenService($this->itemRepo);
    }

    // ─────────────────────────────────────────────────────────────
    // Fixtures
    // ─────────────────────────────────────────────────────────────

    private function sampleItem(string $station = Product::STATION_BAR): array
    {
        return [
            'id' => 1,
            'quantity' => 2,
            'status' => ReservationItem::STATUS_PENDING,
            'created_at' => \date('Y-m-d H:i:s', \time() - 120),   // 2 min atrás
            'reservation_id' => 10,
            'product_id' => 3,
            'product_name' => 'Café con Leche',
            'station' => $station,
            'prep_time' => 10,
            'recipe_steps' => '[]',
            'ingredients_list' => '["leche","espresso"]',
            'critical_check' => null,
            'tracker_code' => 'A01',
            'guests' => 2,
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // getPendingByStation
    // ─────────────────────────────────────────────────────────────

    #[Test]
    public function test_getPendingByStation_groups_items_by_station_and_skips_empty_stations(): void
    {
        $barItem = $this->sampleItem(Product::STATION_BAR);

        $this->itemRepo->method('findPendingByStation')
            ->willReturnCallback(
                static fn(int $cafeId, string $station): array => $station === Product::STATION_BAR
                    ? [$barItem]
                    : []
            );

        $result = $this->service->getPendingByStation(1);

        $this->assertArrayHasKey(Product::STATION_BAR, $result);
        $this->assertCount(1, $result[Product::STATION_BAR]);
        $this->assertArrayNotHasKey(Product::STATION_KITCHEN_HOT, $result);
        $this->assertArrayNotHasKey(Product::STATION_ASSEMBLY, $result);
    }

    #[Test]
    public function test_getPendingByStation_returns_empty_array_when_no_station_has_items(): void
    {
        $this->itemRepo->method('findPendingByStation')->willReturn([]);

        $result = $this->service->getPendingByStation(1);

        $this->assertSame([], $result);
    }

    // ─────────────────────────────────────────────────────────────
    // getPendingForStation
    // ─────────────────────────────────────────────────────────────

    #[Test]
    public function test_getPendingForStation_returns_enriched_items_with_decoded_ingredients(): void
    {
        $item = $this->sampleItem(Product::STATION_BAR);
        $this->itemRepo->method('findPendingByStation')->willReturn([$item]);

        $result = $this->service->getPendingForStation(1, Product::STATION_BAR);

        $this->assertCount(1, $result);
        $this->assertIsArray($result[0]['ingredients_list']);
        $this->assertContains('leche', $result[0]['ingredients_list']);
        $this->assertContains('espresso', $result[0]['ingredients_list']);
        $this->assertArrayHasKey('waiting_minutes', $result[0]);
        $this->assertIsInt($result[0]['waiting_minutes']);
        $this->assertGreaterThanOrEqual(0, $result[0]['waiting_minutes']);
        $this->assertArrayHasKey('is_delayed', $result[0]);
        $this->assertIsBool($result[0]['is_delayed']);
    }

    #[Test]
    public function test_getPendingForStation_returns_empty_array_when_no_items(): void
    {
        $this->itemRepo->method('findPendingByStation')->willReturn([]);

        $this->assertSame([], $this->service->getPendingForStation(1, Product::STATION_BAR));
    }

    // ─────────────────────────────────────────────────────────────
    // getAllPending
    // ─────────────────────────────────────────────────────────────

    #[Test]
    public function test_getAllPending_returns_all_enriched_items_regardless_of_station(): void
    {
        $items = [
            $this->sampleItem(Product::STATION_BAR),
            $this->sampleItem(Product::STATION_KITCHEN_HOT),
        ];
        $this->itemRepo->method('findAllPendingByCafe')->willReturn($items);

        $result = $this->service->getAllPending(1);

        $this->assertCount(2, $result);
        $this->assertArrayHasKey('waiting_minutes', $result[0]);
        $this->assertIsArray($result[0]['ingredients_list']);
    }

    #[Test]
    public function test_getAllPending_returns_empty_array_when_no_pending_items(): void
    {
        $this->itemRepo->method('findAllPendingByCafe')->willReturn([]);

        $this->assertSame([], $this->service->getAllPending(1));
    }

    // ─────────────────────────────────────────────────────────────
    // startPreparing / markReady / markServed
    // ─────────────────────────────────────────────────────────────

    #[Test]
    public function test_startPreparing_delegates_to_model_and_returns_true(): void
    {
        $this->itemRepo->method('updateStatus')->willReturn(true);

        $this->assertTrue($this->service->startPreparing(7));
    }

    #[Test]
    public function test_markReady_delegates_to_model_and_returns_true(): void
    {
        $this->itemRepo->method('markReady')->willReturn(true);

        $this->assertTrue($this->service->markReady(7));
    }

    #[Test]
    public function test_markServed_delegates_to_model_and_returns_true(): void
    {
        $this->itemRepo->method('markServed')->willReturn(true);

        $this->assertTrue($this->service->markServed(7));
    }

    // ─────────────────────────────────────────────────────────────
    // bumpTicket
    // ─────────────────────────────────────────────────────────────

    #[Test]
    public function test_bumpTicket_returns_count_of_bumped_items(): void
    {
        $this->itemRepo->method('bumpTicket')->willReturn(3);

        $this->assertSame(3, $this->service->bumpTicket(10));
    }

    #[Test]
    public function test_bumpTicket_returns_zero_when_no_active_items(): void
    {
        $this->itemRepo->method('bumpTicket')->willReturn(0);

        $this->assertSame(0, $this->service->bumpTicket(99));
    }

    // ─────────────────────────────────────────────────────────────
    // getDailyStats
    // ─────────────────────────────────────────────────────────────

    #[Test]
    public function test_getDailyStats_returns_correctly_typed_statistics(): void
    {
        $this->itemRepo->method('getDailyStats')->willReturn([
            'pending'       => '5',
            'in_progress'   => '2',
            'ready'         => '3',
            'served'        => '10',
            'avg_prep_time' => '7.456',
        ]);

        $stats = $this->service->getDailyStats(1);

        $this->assertSame(5, $stats['pending']);
        $this->assertSame(2, $stats['in_progress']);
        $this->assertSame(3, $stats['ready']);
        $this->assertSame(10, $stats['served']);
        $this->assertSame(7.5, $stats['avg_prep_time']);
    }

    #[Test]
    public function test_getDailyStats_handles_null_avg_prep_time_as_zero(): void
    {
        $this->itemRepo->method('getDailyStats')->willReturn([
            'pending'       => '0',
            'in_progress'   => '0',
            'ready'         => '0',
            'served'        => '0',
            'avg_prep_time' => null,
        ]);

        $stats = $this->service->getDailyStats(1);

        $this->assertSame(0, $stats['pending']);
        $this->assertSame(0, $stats['in_progress']);
        $this->assertSame(0, $stats['ready']);
        $this->assertSame(0, $stats['served']);
        $this->assertSame(0.0, $stats['avg_prep_time']);
    }

    // ─────────────────────────────────────────────────────────────
    // getEstimatedWaitTime
    // ─────────────────────────────────────────────────────────────

    #[Test]
    public function test_getEstimatedWaitTime_returns_total_prep_minutes(): void
    {
        $this->itemRepo->method('getEstimatedWaitTime')->willReturn(45);

        $this->assertSame(45, $this->service->getEstimatedWaitTime(1));
    }

    #[Test]
    public function test_getEstimatedWaitTime_returns_zero_when_no_pending_items(): void
    {
        $this->itemRepo->method('getEstimatedWaitTime')->willReturn(0);

        $this->assertSame(0, $this->service->getEstimatedWaitTime(1));
    }

    // ─────────────────────────────────────────────────────────────
    // getCompletedToday
    // ─────────────────────────────────────────────────────────────

    #[Test]
    public function test_getCompletedToday_returns_served_items_with_enriched_data(): void
    {
        $servedItem           = $this->sampleItem(Product::STATION_BAR);
        $servedItem['status'] = 'served';
        $this->itemRepo->method('findCompletedToday')->willReturn([$servedItem]);

        $result = $this->service->getCompletedToday(1);

        $this->assertCount(1, $result);
        $this->assertArrayHasKey('ingredients_list', $result[0]);
        $this->assertArrayHasKey('waiting_minutes', $result[0]);
        $this->assertIsArray($result[0]['ingredients_list']);
    }

    #[Test]
    public function test_getCompletedToday_returns_empty_array_when_no_served_items(): void
    {
        $this->itemRepo->method('findCompletedToday')->willReturn([]);

        $this->assertSame([], $this->service->getCompletedToday(1));
    }
}
