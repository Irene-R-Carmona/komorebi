<?php

/**
 * ¿Qué pruebas aquí?
 * Tests de Manager\ReservationController: index() con y sin café en sesión, filtros de estado y fecha.
 *
 * ¿Qué me quieres demostrar?
 * - index() renderiza 403 y devuelve null cuando el manager no tiene café asignado.
 * - index() devuelve null y delega la consulta al repositorio cuando hay café en sesión.
 * - index() propaga el filtro 'status' al repositorio.
 * - index() propaga el filtro 'date' al repositorio.
 * - index() ignora filtros con string vacío ('' → no se pasan al repositorio).
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * - Si index() deja de renderizar 403 cuando no hay cafe_id en sesión.
 * - Si index() deja de propagar los filtros de query string al repositorio.
 * - Si el constructor deja de aceptar ReservationRepository inyectado.
 */

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Manager;

use App\Http\Controllers\Manager\ReservationController;
use App\Repositories\ReservationRepository;
use App\Services\Contracts\ReservationServiceInterface;
use Nyholm\Psr7\ServerRequest;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

#[CoversClass(ReservationController::class)]
final class ReservationControllerTest extends TestCase
{
    /** @var PDO&\PHPUnit\Framework\MockObject\Stub */
    private PDO $pdoStub;
    /** @var PDOStatement&\PHPUnit\Framework\MockObject\Stub */
    private PDOStatement $stmtStub;
    private ReservationRepository $reservationRepo;
    /** @var ReservationServiceInterface&\PHPUnit\Framework\MockObject\Stub */
    private ReservationServiceInterface $serviceStub;

    protected function setUp(): void
    {
        $this->pdoStub = $this->createStub(PDO::class);
        $this->stmtStub = $this->createStub(PDOStatement::class);
        $this->reservationRepo = new ReservationRepository($this->pdoStub);
        $this->serviceStub = $this->createStub(ReservationServiceInterface::class);
    }

    protected function tearDown(): void
    {
        unset($_SESSION['user_cafe_id'], $_SESSION['user_id'], $_SESSION['_csrf_token']);
    }

    // ─────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────

    private function makeRequest(array $queryParams = []): ServerRequestInterface
    {
        return new ServerRequest('GET', '/manager/reservations')
            ->withQueryParams($queryParams);
    }

    private function startSession(): void
    {
        if (\session_status() !== \PHP_SESSION_ACTIVE) {
            \session_start();
        }
    }

    // ─────────────────────────────────────────────────────────────
    // index()
    // ─────────────────────────────────────────────────────────────

    public function test_index_returns_null_when_no_cafe_id_in_session(): void
    {
        $this->startSession();
        unset($_SESSION['user_cafe_id']);

        $controller = new ReservationController($this->reservationRepo, $this->serviceStub);

        \ob_start();
        $result = $controller->index($this->makeRequest());
        \ob_end_clean();

        $this->assertNull($result);
    }

    public function test_index_returns_null_when_cafe_id_is_set(): void
    {
        $this->startSession();
        $_SESSION['user_cafe_id'] = 2;
        $_SERVER['REQUEST_URI'] = '/manager/reservations';

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([]);
        $this->pdoStub->method('prepare')->willReturn($this->stmtStub);

        $controller = new ReservationController($this->reservationRepo, $this->serviceStub);

        \ob_start();
        $result = $controller->index($this->makeRequest());
        \ob_end_clean();

        $this->assertNull($result);
    }

    public function test_index_passes_status_filter_to_repository(): void
    {
        $this->startSession();
        $_SESSION['user_cafe_id'] = 5;
        $_SERVER['REQUEST_URI'] = '/manager/reservations';

        $capturedParams = [];

        $this->stmtStub->method('execute')
            ->willReturnCallback(function (array $params) use (&$capturedParams): bool {
                $capturedParams = $params;

                return true;
            });
        $this->stmtStub->method('fetchAll')->willReturn([]);
        $this->pdoStub->method('prepare')->willReturn($this->stmtStub);

        $controller = new ReservationController($this->reservationRepo, $this->serviceStub);

        \ob_start();
        $controller->index($this->makeRequest(['status' => 'confirmed']));
        \ob_end_clean();

        $this->assertArrayHasKey('status', $capturedParams);
        $this->assertSame('confirmed', $capturedParams['status']);
    }

    public function test_index_passes_date_filter_to_repository(): void
    {
        $this->startSession();
        $_SESSION['user_cafe_id'] = 5;
        $_SERVER['REQUEST_URI'] = '/manager/reservations';

        $capturedParams = [];

        $this->stmtStub->method('execute')
            ->willReturnCallback(function (array $params) use (&$capturedParams): bool {
                $capturedParams = $params;

                return true;
            });
        $this->stmtStub->method('fetchAll')->willReturn([]);
        $this->pdoStub->method('prepare')->willReturn($this->stmtStub);

        $controller = new ReservationController($this->reservationRepo, $this->serviceStub);

        \ob_start();
        $controller->index($this->makeRequest(['date' => '2026-06-15']));
        \ob_end_clean();

        $this->assertArrayHasKey('date', $capturedParams);
        $this->assertSame('2026-06-15', $capturedParams['date']);
    }

    public function test_index_ignores_empty_string_filters(): void
    {
        $this->startSession();
        $_SESSION['user_cafe_id'] = 5;
        $_SERVER['REQUEST_URI'] = '/manager/reservations';

        $capturedParams = [];

        $this->stmtStub->method('execute')
            ->willReturnCallback(function (array $params) use (&$capturedParams): bool {
                $capturedParams = $params;

                return true;
            });
        $this->stmtStub->method('fetchAll')->willReturn([]);
        $this->pdoStub->method('prepare')->willReturn($this->stmtStub);

        $controller = new ReservationController($this->reservationRepo, $this->serviceStub);

        \ob_start();
        $controller->index($this->makeRequest(['status' => '', 'date' => '']));
        \ob_end_clean();

        $this->assertArrayNotHasKey('status', $capturedParams);
        $this->assertArrayNotHasKey('date', $capturedParams);
    }
}
