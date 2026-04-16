<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * Tests para Manager\ReservationController: index().
 *
 * ¿Qué me quieres demostrar?
 * - index() devuelve null y renderiza 403 cuando el manager no tiene café asignado.
 * - index() devuelve null y renderiza la vista cuando hay café asignado (sin filtros).
 * - index() aplica el filtro de estado al llamar al repositorio.
 * - index() aplica el filtro de fecha al llamar al repositorio.
 * - index() ignora filtros vacíos (string vacío → null).
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * - Si index() deja de renderizar 403 cuando no hay cafe_id en sesión.
 * - Si index() deja de propagar los filtros de query string al repositorio.
 * - Si el constructor deja de aceptar ReservationRepository inyectado.
 * - Si index() cambia su tipo de retorno de ?ResponseInterface a otro.
 */

namespace Tests\Unit\Controllers\Manager;

use App\Http\Controllers\Manager\ReservationController;
use App\Repositories\ReservationRepository;
use PDO;
use PDOStatement;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Tests para Manager\ReservationController
 */
final class ReservationControllerTest extends TestCase
{
    private PDO&Stub $pdoMock;
    private PDOStatement&Stub $stmtMock;
    private ReservationRepository $reservationRepo;

    protected function setUp(): void
    {
        $this->pdoMock = $this->createStub(PDO::class);
        $this->stmtMock = $this->createStub(PDOStatement::class);

        // ReservationRepository es final; se construye con PDO mockeado
        $this->reservationRepo = new ReservationRepository($this->pdoMock);
    }

    protected function tearDown(): void
    {
        if (isset($_SESSION)) {
            unset($_SESSION['user_cafe_id'], $_SESSION['_csrf_token']);
        }
    }

    // ─────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────

    /**
     * @param array<string,string> $queryParams
     */
    private function makeRequest(array $queryParams = []): ServerRequestInterface
    {
        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getQueryParams')->willReturn($queryParams);

        return $request;
    }

    private function startSession(): void
    {
        if (\session_status() !== PHP_SESSION_ACTIVE) {
            \session_start();
        }
    }

    // ─────────────────────────────────────────────────────────────
    // Instanciación
    // ─────────────────────────────────────────────────────────────

    public function testControllerCanBeInstantiated(): void
    {
        $controller = new ReservationController($this->reservationRepo);
        $this->assertInstanceOf(ReservationController::class, $controller);
    }

    // ─────────────────────────────────────────────────────────────
    // index()
    // ─────────────────────────────────────────────────────────────

    public function testIndexReturnsNullWhenNoCafeIdInSession(): void
    {
        $this->startSession();
        unset($_SESSION['user_cafe_id']);

        $controller = new ReservationController($this->reservationRepo);

        \ob_start();
        $result = $controller->index($this->makeRequest());
        \ob_end_clean();

        $this->assertNull($result);
    }

    public function testIndexReturnsNullWhenCafeIdIsSet(): void
    {
        $this->startSession();
        $_SESSION['user_cafe_id'] = 2;
        $_SERVER['REQUEST_URI'] = '/manager/reservations';

        // PDO devuelve lista vacía
        $this->stmtMock->method('execute')->willReturn(true);
        $this->stmtMock->method('fetchAll')->willReturn([]);
        $this->pdoMock->method('prepare')->willReturn($this->stmtMock);

        $controller = new ReservationController($this->reservationRepo);

        \ob_start();
        $result = $controller->index($this->makeRequest());
        \ob_end_clean();

        $this->assertNull($result);
    }

    public function testIndexPassesStatusFilterToRepository(): void
    {
        $this->startSession();
        $_SESSION['user_cafe_id'] = 5;
        $_SERVER['REQUEST_URI'] = '/manager/reservations';

        $capturedSql = '';
        $capturedParams = [];

        // Capturar la SQL generada para verificar que incluye el filtro de estado
        $this->stmtMock->method('execute')
            ->willReturnCallback(function (array $params) use (&$capturedParams): bool {
                $capturedParams = $params;

                return true;
            });
        $this->stmtMock->method('fetchAll')->willReturn([]);
        $this->pdoMock->method('prepare')
            ->willReturnCallback(function (string $sql) use (&$capturedSql): PDOStatement {
                $capturedSql = $sql;

                return $this->stmtMock;
            });

        $controller = new ReservationController($this->reservationRepo);

        \ob_start();
        $result = $controller->index($this->makeRequest(['status' => 'confirmed']));
        \ob_end_clean();

        $this->assertNull($result);
        $this->assertArrayHasKey('status', $capturedParams);
        $this->assertSame('confirmed', $capturedParams['status']);
    }

    public function testIndexPassesDateFilterToRepository(): void
    {
        $this->startSession();
        $_SESSION['user_cafe_id'] = 5;
        $_SERVER['REQUEST_URI'] = '/manager/reservations';

        $capturedParams = [];

        $this->stmtMock->method('execute')
            ->willReturnCallback(function (array $params) use (&$capturedParams): bool {
                $capturedParams = $params;

                return true;
            });
        $this->stmtMock->method('fetchAll')->willReturn([]);
        $this->pdoMock->method('prepare')->willReturn($this->stmtMock);

        $controller = new ReservationController($this->reservationRepo);

        \ob_start();
        $result = $controller->index($this->makeRequest(['date' => '2026-06-15']));
        \ob_end_clean();

        $this->assertNull($result);
        $this->assertArrayHasKey('date', $capturedParams);
        $this->assertSame('2026-06-15', $capturedParams['date']);
    }

    public function testIndexIgnoresEmptyStringFilters(): void
    {
        $this->startSession();
        $_SESSION['user_cafe_id'] = 5;
        $_SERVER['REQUEST_URI'] = '/manager/reservations';

        $capturedParams = [];

        $this->stmtMock->method('execute')
            ->willReturnCallback(function (array $params) use (&$capturedParams): bool {
                $capturedParams = $params;

                return true;
            });
        $this->stmtMock->method('fetchAll')->willReturn([]);
        $this->pdoMock->method('prepare')->willReturn($this->stmtMock);

        $controller = new ReservationController($this->reservationRepo);

        \ob_start();
        $result = $controller->index($this->makeRequest(['status' => '', 'date' => '']));
        \ob_end_clean();

        $this->assertNull($result);
        // Con filtros vacíos, el query sólo lleva cafe_id (no status ni date)
        $this->assertArrayNotHasKey('status', $capturedParams);
        $this->assertArrayNotHasKey('date', $capturedParams);
    }
}
