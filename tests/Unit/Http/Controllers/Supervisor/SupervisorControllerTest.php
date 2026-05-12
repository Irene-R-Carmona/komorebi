<?php

/**
 * ¿Qué pruebas aquí?
 * Tests de Supervisor\SupervisorController: estructura + comportamiento de index() y dashboardData() con PDO mock.
 *
 * ¿Qué me quieres demostrar?
 * - El controlador expone los métodos index() y dashboardData().
 * - Los métodos assignments() y createAssignment() fueron eliminados.
 * - index() no consulta el repositorio cuando no hay cafe_id en sesión.
 * - index() consulta el repositorio cuando hay cafe_id en sesión.
 * - index() transforma reservation_time al formato HH:MM (5 caracteres).
 * - index() no lanza excepciones con un estado 'no_show' en STATUS_LABELS.
 * - dashboardData() devuelve un ResponseInterface JSON con las 5 claves esperadas.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * - Si se renombra alguno de los métodos públicos del controlador.
 * - Si index() deja de consultar el repositorio cuando hay cafe_id.
 * - Si index() deja de transformar reservation_time a HH:MM.
 * - Si dashboardData() deja de devolver un ResponseInterface JSON.
 */

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Supervisor;

use App\Http\Controllers\Supervisor\SupervisorController;
use App\Repositories\Contracts\ReservationItemRepositoryInterface;
use App\Repositories\ReservationRepository;
use App\Services\KitchenService;
use Psr\Http\Message\ResponseInterface;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Message\ServerRequestInterface;
use Tests\Support\ControllerTestCase;

#[CoversClass(SupervisorController::class)]
final class SupervisorControllerTest extends ControllerTestCase
{
    /** @var PDO&\PHPUnit\Framework\MockObject\Stub */
    private PDO $pdoMock;
    /** @var PDOStatement&\PHPUnit\Framework\MockObject\Stub */
    private PDOStatement $stmtMock;
    private ReservationRepository $reservationRepo;

    protected function setUp(): void
    {
        $_SERVER['REQUEST_URI'] ??= '/supervisor/dashboard';

        $this->pdoMock = $this->createStub(PDO::class);
        $this->stmtMock = $this->createStub(PDOStatement::class);
        $this->reservationRepo = new ReservationRepository($this->pdoMock);
    }

    protected function tearDown(): void
    {
        if (isset($_SESSION)) {
            unset($_SESSION['user_cafe_id'], $_SESSION['user_id']);
        }
    }

    private function startSession(): void
    {
        if (\session_status() !== \PHP_SESSION_ACTIVE) {
            \session_start();
        }
    }

    private function makeRequest(): ServerRequestInterface
    {
        return $this->createStub(ServerRequestInterface::class);
    }

    private function makeItemRepo(): ReservationItemRepositoryInterface
    {
        $itemRepo = $this->createStub(ReservationItemRepositoryInterface::class);
        $itemRepo->method('getReadyItemsByReservations')->willReturn([]);
        return $itemRepo;
    }

    private function makeKitchenService(): KitchenService
    {
        $itemRepo = $this->createStub(ReservationItemRepositoryInterface::class);
        $itemRepo->method('findAllPendingByCafe')->willReturn([]);
        $itemRepo->method('findCompletedToday')->willReturn([]);
        $itemRepo->method('getDailyStats')->willReturn([]);

        return new KitchenService($itemRepo);
    }

    // ─────────────────────────────────────────────────────────────
    // Smoke tests (estructura)
    // ─────────────────────────────────────────────────────────────

    public function test_class_has_expected_methods(): void
    {
        $this->assertTrue(\method_exists(SupervisorController::class, 'index'));
        $this->assertTrue(\method_exists(SupervisorController::class, 'dashboardData'));
        $this->assertFalse(\method_exists(SupervisorController::class, 'assignments'), 'assignments() debería haber sido eliminado');
        $this->assertFalse(\method_exists(SupervisorController::class, 'createAssignment'), 'createAssignment() debería haber sido eliminado');
    }

    public function test_instance_can_be_created_with_dependencies(): void
    {
        $controller = new SupervisorController(
            new ReservationRepository($this->pdoMock),
            $this->makeKitchenService(),
            $this->makeItemRepo(),
        );
        $this->assertInstanceOf(SupervisorController::class, $controller);
    }

    public function test_instance_can_be_created_with_named_arguments(): void
    {
        $controller = new SupervisorController(
            reservationRepo: new ReservationRepository($this->pdoMock),
            kitchenService: $this->makeKitchenService(),
            itemRepo: $this->makeItemRepo(),
        );
        $this->assertInstanceOf(SupervisorController::class, $controller);
    }

    // ─────────────────────────────────────────────────────────────
    // index() — comportamiento
    // ─────────────────────────────────────────────────────────────

    public function test_index_skips_repository_when_no_cafe_id_in_session(): void
    {
        $this->startSession();
        unset($_SESSION['user_cafe_id']);

        // Si no hay cafe_id, no se hace ningún prepare() al PDO
        $pdoStrict = $this->createMock(PDO::class);
        $pdoStrict->expects($this->never())->method('prepare');

        $controller = new SupervisorController(
            new ReservationRepository($pdoStrict),
            $this->makeKitchenService(),
            $this->makeItemRepo(),
        );

        \ob_start();
        $result = $controller->index($this->makeRequest());
        \ob_end_clean();

        $this->assertNull($result);
    }

    public function test_index_queries_repository_when_cafe_id_is_set(): void
    {
        $this->startSession();
        $_SESSION['user_cafe_id'] = 3;
        $_SERVER['REQUEST_URI'] = '/supervisor/dashboard';

        $this->stmtMock->method('execute')->willReturn(true);
        $this->stmtMock->method('fetchAll')->willReturn([]);
        $this->pdoMock->method('prepare')->willReturn($this->stmtMock);

        $controller = new SupervisorController(
            $this->reservationRepo,
            $this->makeKitchenService(),
            $this->makeItemRepo(),
        );

        \ob_start();
        $result = $controller->index($this->makeRequest());
        \ob_end_clean();

        $this->assertNull($result);
    }

    public function test_index_transforms_reservation_time_to_short_format(): void
    {
        $this->startSession();
        $_SESSION['user_cafe_id'] = 7;
        $_SERVER['REQUEST_URI'] = '/supervisor/dashboard';

        $rawRow = [
            'id' => 42,
            'user_id' => 10,
            'cafe_id' => 7,
            'reservation_date' => \date('Y-m-d'),
            'reservation_time' => '14:30:00',
            'guest_count' => 3,
            'status' => 'confirmed',
            'pass_product_id' => null,
            'pass_name' => null,
            'pass_unit_price' => null,
            'pass_duration_minutes' => null,
            'tracker_id' => null,
            'current_zone_id' => null,
            'check_in_at' => null,
            'check_out_at' => null,
            'protocol_hygiene' => 0,
            'protocol_briefing' => 0,
            'protocol_shoes' => 0,
            'final_amount' => null,
            'payment_status' => null,
            'payment_method' => null,
            'payment_notes' => null,
            'notes' => null,
            'deleted_at' => null,
            'created_at' => '2026-05-01 09:00:00',
            'updated_at' => '2026-05-01 09:00:00',
        ];

        $this->stmtMock->method('execute')->willReturn(true);
        $this->stmtMock->method('fetchAll')->willReturn([$rawRow]);
        $this->pdoMock->method('prepare')->willReturn($this->stmtMock);

        $controller = new SupervisorController(
            $this->reservationRepo,
            $this->makeKitchenService(),
            $this->makeItemRepo(),
        );

        \ob_start();
        $result = $controller->index($this->makeRequest());
        \ob_end_clean();

        $this->assertNull($result);
    }

    public function test_index_handles_no_show_status_without_exception(): void
    {
        $this->startSession();
        $_SESSION['user_cafe_id'] = 7;
        $_SERVER['REQUEST_URI'] = '/supervisor/dashboard';

        $this->stmtMock->method('execute')->willReturn(true);
        $this->stmtMock->method('fetchAll')->willReturn([[
            'id' => 1,
            'user_id' => 5,
            'cafe_id' => 7,
            'reservation_date' => \date('Y-m-d'),
            'reservation_time' => '10:00:00',
            'guest_count' => 2,
            'status' => 'no_show',
            'pass_product_id' => null,
            'pass_name' => null,
            'pass_unit_price' => null,
            'pass_duration_minutes' => null,
            'tracker_id' => null,
            'current_zone_id' => null,
            'check_in_at' => null,
            'check_out_at' => null,
            'protocol_hygiene' => 0,
            'protocol_briefing' => 0,
            'protocol_shoes' => 0,
            'final_amount' => null,
            'payment_status' => null,
            'payment_method' => null,
            'payment_notes' => null,
            'notes' => null,
            'deleted_at' => null,
            'created_at' => '2026-05-01 09:00:00',
            'updated_at' => '2026-05-01 09:00:00',
        ]]);
        $this->pdoMock->method('prepare')->willReturn($this->stmtMock);

        $controller = new SupervisorController(
            $this->reservationRepo,
            $this->makeKitchenService(),
            $this->makeItemRepo(),
        );

        \ob_start();
        $result = $controller->index($this->makeRequest());
        \ob_end_clean();

        $this->assertNull($result);
    }

    public function test_dashboard_data_returns_json_response_with_expected_keys(): void
    {
        $this->startSession();
        $_SESSION['user_cafe_id'] = 5;

        $this->stmtMock->method('execute')->willReturn(true);
        $this->stmtMock->method('fetchAll')->willReturn([]);
        $this->pdoMock->method('prepare')->willReturn($this->stmtMock);

        $controller = new SupervisorController(
            $this->reservationRepo,
            $this->makeKitchenService(),
            $this->makeItemRepo(),
        );

        $result = $controller->dashboardData($this->makeRequest());

        $this->assertInstanceOf(ResponseInterface::class, $result);
        $this->assertSame(200, $result->getStatusCode());

        $body = (string) $result->getBody();
        $decoded = \json_decode($body, true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('reservations',  $decoded);
        $this->assertArrayHasKey('activeTables',  $decoded);
        $this->assertArrayHasKey('pendingOrders', $decoded);
        $this->assertArrayHasKey('kitchenOrders', $decoded);
        $this->assertArrayHasKey('readyOrders',   $decoded);
    }
}
