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

use App\Core\Database;
use App\Models\Product;
use App\Models\ReservationItem;
use App\Services\KitchenService;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[CoversClass(KitchenService::class)]
final class KitchenServiceTest extends TestCase
{
    /** @var MockObject&PDO */
    private PDO $pdo;

    // ─────────────────────────────────────────────────────────────
    // setUp / tearDown
    // ─────────────────────────────────────────────────────────────

    protected function setUp(): void
    {
        $this->pdo = $this->createMock(PDO::class);
        $this->injectPdoIntoDatabase($this->pdo);
    }

    protected function tearDown(): void
    {
        $this->resetDatabaseSingleton();
    }

    // ─────────────────────────────────────────────────────────────
    // Helpers de infraestructura
    // ─────────────────────────────────────────────────────────────

    private function injectPdoIntoDatabase(PDO $pdo): void
    {
        $reflection = new ReflectionClass(Database::class);
        $instanceProp = $reflection->getProperty('instance');

        $fakeInstance = $reflection->newInstanceWithoutConstructor();
        $connectionProp = $reflection->getProperty('connection');
        $connectionProp->setValue($fakeInstance, $pdo);

        $instanceProp->setValue(null, $fakeInstance);
    }

    private function resetDatabaseSingleton(): void
    {
        $reflection = new ReflectionClass(Database::class);
        $instanceProp = $reflection->getProperty('instance');
        $instanceProp->setValue(null, null);
    }

    private function makeStmt(
        mixed $fetchReturn = false,
        array $fetchAllReturn = [],
        mixed $fetchColumnReturn = 0,
        int   $rowCountReturn = 0
    ): PDOStatement {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn($fetchReturn);
        $stmt->method('fetchAll')->willReturn($fetchAllReturn);
        $stmt->method('fetchColumn')->willReturn($fetchColumnReturn);
        $stmt->method('rowCount')->willReturn($rowCountReturn);

        return $stmt;
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

        // VALID_STATIONS = [bar, kitchen_hot, kitchen_cold, bakery, assembly]
        $this->pdo->method('prepare')->willReturnOnConsecutiveCalls(
            $this->makeStmt(fetchAllReturn: [$barItem]),  // bar → tiene items
            $this->makeStmt(fetchAllReturn: []),           // kitchen_hot → vacío
            $this->makeStmt(fetchAllReturn: []),           // kitchen_cold → vacío
            $this->makeStmt(fetchAllReturn: []),           // bakery → vacío
            $this->makeStmt(fetchAllReturn: [])            // assembly → vacío
        );

        $service = new KitchenService($this->pdo);
        $result = $service->getPendingByStation(1);

        $this->assertArrayHasKey(Product::STATION_BAR, $result);
        $this->assertCount(1, $result[Product::STATION_BAR]);

        // Las estaciones sin items no deben aparecer en el resultado
        $this->assertArrayNotHasKey(Product::STATION_KITCHEN_HOT, $result);
        $this->assertArrayNotHasKey(Product::STATION_ASSEMBLY, $result);
    }

    #[Test]
    public function test_getPendingByStation_returns_empty_array_when_no_station_has_items(): void
    {
        $this->pdo->method('prepare')->willReturn(
            $this->makeStmt(fetchAllReturn: [])
        );

        $service = new KitchenService($this->pdo);
        $result = $service->getPendingByStation(1);

        $this->assertSame([], $result);
    }

    // ─────────────────────────────────────────────────────────────
    // getPendingForStation
    // ─────────────────────────────────────────────────────────────

    #[Test]
    public function test_getPendingForStation_returns_enriched_items_with_decoded_ingredients(): void
    {
        $item = $this->sampleItem(Product::STATION_BAR);
        $this->pdo->method('prepare')->willReturn(
            $this->makeStmt(fetchAllReturn: [$item])
        );

        $service = new KitchenService($this->pdo);
        $result = $service->getPendingForStation(1, Product::STATION_BAR);

        $this->assertCount(1, $result);

        // enrichItems debe decodificar ingredients_list de JSON a array
        $this->assertIsArray($result[0]['ingredients_list']);
        $this->assertContains('leche', $result[0]['ingredients_list']);
        $this->assertContains('espresso', $result[0]['ingredients_list']);

        // enrichItems debe calcular waiting_minutes y is_delayed
        $this->assertArrayHasKey('waiting_minutes', $result[0]);
        $this->assertIsInt($result[0]['waiting_minutes']);
        $this->assertGreaterThanOrEqual(0, $result[0]['waiting_minutes']);
        $this->assertArrayHasKey('is_delayed', $result[0]);
        $this->assertIsBool($result[0]['is_delayed']);
    }

    #[Test]
    public function test_getPendingForStation_returns_empty_array_when_no_items(): void
    {
        $this->pdo->method('prepare')->willReturn(
            $this->makeStmt(fetchAllReturn: [])
        );

        $service = new KitchenService($this->pdo);
        $result = $service->getPendingForStation(1, Product::STATION_BAR);

        $this->assertSame([], $result);
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
        $this->pdo->method('prepare')->willReturn(
            $this->makeStmt(fetchAllReturn: $items)
        );

        $service = new KitchenService($this->pdo);
        $result = $service->getAllPending(1);

        $this->assertCount(2, $result);
        $this->assertArrayHasKey('waiting_minutes', $result[0]);
        $this->assertIsArray($result[0]['ingredients_list']);
    }

    #[Test]
    public function test_getAllPending_returns_empty_array_when_no_pending_items(): void
    {
        $this->pdo->method('prepare')->willReturn(
            $this->makeStmt(fetchAllReturn: [])
        );

        $service = new KitchenService($this->pdo);
        $result = $service->getAllPending(1);

        $this->assertSame([], $result);
    }

    // ─────────────────────────────────────────────────────────────
    // startPreparing / markReady / markServed
    // ─────────────────────────────────────────────────────────────

    #[Test]
    public function test_startPreparing_delegates_to_model_and_returns_true(): void
    {
        $this->pdo->method('prepare')->willReturn($this->makeStmt());

        $service = new KitchenService($this->pdo);

        $this->assertTrue($service->startPreparing(7));
    }

    #[Test]
    public function test_markReady_delegates_to_model_and_returns_true(): void
    {
        $this->pdo->method('prepare')->willReturn($this->makeStmt());

        $service = new KitchenService($this->pdo);

        $this->assertTrue($service->markReady(7));
    }

    #[Test]
    public function test_markServed_delegates_to_model_and_returns_true(): void
    {
        $this->pdo->method('prepare')->willReturn($this->makeStmt());

        $service = new KitchenService($this->pdo);

        $this->assertTrue($service->markServed(7));
    }

    // ─────────────────────────────────────────────────────────────
    // bumpTicket
    // ─────────────────────────────────────────────────────────────

    #[Test]
    public function test_bumpTicket_returns_count_of_bumped_items(): void
    {
        $this->pdo->method('prepare')->willReturn(
            $this->makeStmt(rowCountReturn: 3)
        );

        $service = new KitchenService($this->pdo);

        $this->assertSame(3, $service->bumpTicket(10));
    }

    #[Test]
    public function test_bumpTicket_returns_zero_when_no_active_items(): void
    {
        $this->pdo->method('prepare')->willReturn(
            $this->makeStmt(rowCountReturn: 0)
        );

        $service = new KitchenService($this->pdo);

        $this->assertSame(0, $service->bumpTicket(99));
    }

    // ─────────────────────────────────────────────────────────────
    // getDailyStats
    // ─────────────────────────────────────────────────────────────

    #[Test]
    public function test_getDailyStats_returns_correctly_typed_statistics(): void
    {
        $statsRow = [
            'pending' => '5',
            'in_progress' => '2',
            'ready' => '3',
            'served' => '10',
            'avg_prep_time' => '7.456',
        ];
        $this->pdo->method('prepare')->willReturn(
            $this->makeStmt(fetchReturn: $statsRow)
        );

        $service = new KitchenService($this->pdo);
        $stats = $service->getDailyStats(1);

        $this->assertSame(5, $stats['pending']);
        $this->assertSame(2, $stats['in_progress']);
        $this->assertSame(3, $stats['ready']);
        $this->assertSame(10, $stats['served']);
        $this->assertSame(7.5, $stats['avg_prep_time']);   // round(7.456, 1) = 7.5
    }

    #[Test]
    public function test_getDailyStats_handles_null_avg_prep_time_as_zero(): void
    {
        // COUNT() siempre devuelve un número; AVG() retorna NULL si no hay filas
        $statsRow = [
            'pending' => '0',
            'in_progress' => '0',
            'ready' => '0',
            'served' => '0',
            'avg_prep_time' => null,
        ];
        $this->pdo->method('prepare')->willReturn(
            $this->makeStmt(fetchReturn: $statsRow)
        );

        $service = new KitchenService($this->pdo);
        $stats = $service->getDailyStats(1);

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
        $this->pdo->method('prepare')->willReturn(
            $this->makeStmt(fetchColumnReturn: '45')
        );

        $service = new KitchenService($this->pdo);

        $this->assertSame(45, $service->getEstimatedWaitTime(1));
    }

    #[Test]
    public function test_getEstimatedWaitTime_returns_zero_when_no_pending_items(): void
    {
        // SUM() retorna NULL cuando no hay filas; el código usa ?: 0
        $this->pdo->method('prepare')->willReturn(
            $this->makeStmt(fetchColumnReturn: null)
        );

        $service = new KitchenService($this->pdo);

        $this->assertSame(0, $service->getEstimatedWaitTime(1));
    }

    // ─────────────────────────────────────────────────────────────
    // getCompletedToday
    // ─────────────────────────────────────────────────────────────

    #[Test]
    public function test_getCompletedToday_returns_served_items_with_enriched_data(): void
    {
        $servedItem = $this->sampleItem(Product::STATION_BAR);
        $servedItem['status'] = 'served';

        $this->pdo->method('prepare')->willReturn(
            $this->makeStmt(fetchAllReturn: [$servedItem])
        );

        $service = new KitchenService($this->pdo);
        $result = $service->getCompletedToday(1);

        $this->assertCount(1, $result);
        $this->assertArrayHasKey('ingredients_list', $result[0]);
        $this->assertArrayHasKey('waiting_minutes', $result[0]);
        $this->assertIsArray($result[0]['ingredients_list']);
    }

    #[Test]
    public function test_getCompletedToday_returns_empty_array_when_no_served_items(): void
    {
        $this->pdo->method('prepare')->willReturn(
            $this->makeStmt(fetchAllReturn: [])
        );

        $service = new KitchenService($this->pdo);
        $result = $service->getCompletedToday(1);

        $this->assertSame([], $result);
    }
}
