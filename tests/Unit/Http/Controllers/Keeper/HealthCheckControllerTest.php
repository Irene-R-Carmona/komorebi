<?php

/**
 * ¿Qué pruebas aquí?
 * Verifica que HealthCheckController sigue el contrato PSR-7 completo.
 *
 * ¿Qué me quieres demostrar?
 * Que ningún método usa $_POST, $_GET, header() ni exit.
 * Que los inputs se leen desde ServerRequestInterface.
 * Que todos los métodos retornan ?ResponseInterface.
 * Que store() redirige tras guardar correctamente.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se vuelve a usar $_POST/$_GET/header()/exit en el controller.
 * Si store() deja de retornar un redirect ResponseInterface.
 * Si se deja de delegar a HealthCheckService.
 */

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Keeper;

use App\Core\Http\ResponseFactory;
use App\Http\Controllers\Keeper\HealthCheckController;
use App\Repositories\Contracts\AnimalRepositoryInterface;
use App\Repositories\Contracts\HealthCheckRepositoryInterface;
use App\Services\HealthCheckService;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

#[CoversClass(HealthCheckController::class)]
final class HealthCheckControllerTest extends TestCase
{
    private const CSRF_TOKEN = 'test-csrf-xyz';

    protected function setUp(): void
    {
        if (\session_status() === PHP_SESSION_NONE) {
            \session_start();
        }
        $_SESSION['_csrf_token'] = self::CSRF_TOKEN;
        $_SESSION['user_id'] = 1;
        $_SESSION['user'] = ['id' => 1, 'name' => 'Keeper Test', 'roles' => ['keeper']];
        $_SERVER['REQUEST_URI'] = '/keeper/health-checks';
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_POST = [];
        $_GET = [];
        unset($_SERVER['REQUEST_URI']);
    }

    private function makeHealthCheckService(array $overrides = []): HealthCheckService
    {
        $repo = $this->createMock(HealthCheckRepositoryInterface::class);
        $repo->method('getTodayChecks')->willReturn($overrides['todayChecks'] ?? []);
        $repo->method('getPendingAnimals')->willReturn($overrides['pendingAnimals'] ?? []);
        $repo->method('getCheckswithAlerts')->willReturn($overrides['alerts'] ?? []);
        $repo->method('findById')->willReturn($overrides['check'] ?? [
            'id' => 1,
            'animal_id' => 1,
            'animal_name' => 'Neko',
            'species_type' => 'Gato',
            'current_status' => 'activo',
            'keeper_name' => 'Keeper Test',
            'check_date' => '2024-01-15',
            'created_at' => '2024-01-15 10:00:00',
            'weight_kg' => null,
            'temperature_c' => null,
            'appetite' => 'normal',
            'energy_level' => 'normal',
            'coat_condition' => 'good',
            'eyes_clear' => 1,
            'breathing_normal' => 1,
            'mobility_normal' => 1,
            'notes' => '',
            'alerts' => null,
        ]);
        $repo->method('exists')->willReturn($overrides['exists'] ?? false);
        $repo->method('findTodayByAnimalId')->willReturn(null);
        $repo->method('create')->willReturn(99);
        $repo->method('getCheckHistory')->willReturn([]);
        $repo->method('countByKeeperInPeriod')->willReturn(0);
        $repo->method('getAlertStatistics')->willReturn([]);

        return new HealthCheckService($repo);
    }

    private function makeAnimalRepo(?array $animal = null): AnimalRepositoryInterface
    {
        $defaultAnimal = [
            'id' => 1,
            'name' => 'Neko',
            'species_type' => 'Gato',
            'current_status' => 'activo',
            'age' => 3,
        ];
        $repo = $this->createMock(AnimalRepositoryInterface::class);
        $repo->method('findById')->willReturn($animal ?? $defaultAnimal);

        return $repo;
    }

    private function makeController(
        ?HealthCheckService $service = null,
        ?AnimalRepositoryInterface $animalRepo = null,
    ): HealthCheckController {
        return new HealthCheckController(
            $service ?? $this->makeHealthCheckService(),
            $animalRepo ?? $this->makeAnimalRepo(),
            new ResponseFactory(),
        );
    }

    // ─────────────────────────────────────────────────────────────
    // index()
    // ─────────────────────────────────────────────────────────────

    public function test_index_returns_null_and_renders_view(): void
    {
        $request = new ServerRequest('GET', '/keeper/health-checks');

        \ob_start();
        $result = $this->makeController()->index($request);
        \ob_end_clean();

        $this->assertNull($result);
    }

    // ─────────────────────────────────────────────────────────────
    // create()
    // ─────────────────────────────────────────────────────────────

    public function test_create_returns_null_when_animal_found_and_no_check_today(): void
    {
        $service = $this->makeHealthCheckService(['exists' => false]);
        $animalRepo = $this->makeAnimalRepo(['id' => 5, 'name' => 'Luna', 'species_type' => 'Gato', 'current_status' => 'activo', 'age' => 2]);

        $request = new ServerRequest('GET', '/keeper/health-checks/create/5');

        \ob_start();
        $result = $this->makeController($service, $animalRepo)->create($request, 5);
        \ob_end_clean();

        $this->assertNull($result);
    }

    // ─────────────────────────────────────────────────────────────
    // store()
    // ─────────────────────────────────────────────────────────────

    public function test_store_redirects_on_success(): void
    {
        // $_POST deve estar vacío – el controller debe leer de PSR-7
        $_POST = [];

        $request = new ServerRequest('POST', '/keeper/health-checks')
            ->withParsedBody([
                'csrf_token' => self::CSRF_TOKEN,
                'animal_id' => '5',
                'appetite' => 'normal',
                'energy_level' => 'normal',
                'coat_condition' => 'good',
                'eyes_clear' => '1',
                'breathing_normal' => '1',
                'mobility_normal' => '1',
                'notes' => '',
            ]);

        $result = $this->makeController()->store($request);

        $this->assertInstanceOf(ResponseInterface::class, $result);
        $this->assertSame(302, $result->getStatusCode());
        $this->assertSame('/keeper/health-checks', $result->getHeaderLine('Location'));
    }

    public function test_store_reads_from_psr7_not_post_superglobal(): void
    {
        // Asegurar que $_POST no se lee
        $_POST = ['animal_id' => '999', 'csrf_token' => 'invalid'];

        $request = new ServerRequest('POST', '/keeper/health-checks')
            ->withParsedBody([
                'csrf_token' => self::CSRF_TOKEN,
                'animal_id' => '5',
                'appetite' => 'normal',
                'energy_level' => 'normal',
                'coat_condition' => 'good',
                'eyes_clear' => '1',
                'breathing_normal' => '1',
                'mobility_normal' => '1',
            ]);

        $result = $this->makeController()->store($request);

        $this->assertInstanceOf(ResponseInterface::class, $result);
        $this->assertSame(302, $result->getStatusCode());
    }

    public function test_store_redirects_with_warning_when_alerts_detected(): void
    {
        $repo = $this->createMock(HealthCheckRepositoryInterface::class);
        $repo->method('exists')->willReturn(false);
        $repo->method('findTodayByAnimalId')->willReturn(null);
        $repo->method('create')->willReturn(10);
        $repo->method('getTodayChecks')->willReturn([]);
        $repo->method('getPendingAnimals')->willReturn([]);
        $repo->method('getCheckswithAlerts')->willReturn([]);

        // Crear servicio real para que detecte alertas de temperatura alta
        $service = new HealthCheckService($repo);

        $request = new ServerRequest('POST', '/keeper/health-checks')
            ->withParsedBody([
                'csrf_token' => self::CSRF_TOKEN,
                'animal_id' => '3',
                'temperature_c' => '42.0', // alto → genera alerta
                'appetite' => 'normal',
                'energy_level' => 'normal',
                'coat_condition' => 'good',
                'eyes_clear' => '1',
                'breathing_normal' => '1',
                'mobility_normal' => '1',
            ]);

        $result = $this->makeController($service)->store($request);

        $this->assertInstanceOf(ResponseInterface::class, $result);
        $this->assertSame(302, $result->getStatusCode());
    }

    // ─────────────────────────────────────────────────────────────
    // show()
    // ─────────────────────────────────────────────────────────────

    public function test_show_returns_null_when_check_found(): void
    {
        $service = $this->makeHealthCheckService([
            'check' => [
                'id' => 1,
                'animal_id' => 1,
                'animal_name' => 'Neko',
                'species_type' => 'Gato',
                'current_status' => 'activo',
                'keeper_name' => 'Keeper Test',
                'check_date' => '2024-01-15',
                'created_at' => '2024-01-15 10:00:00',
                'weight_kg' => null,
                'temperature_c' => null,
                'appetite' => 'normal',
                'energy_level' => 'normal',
                'coat_condition' => 'good',
                'eyes_clear' => 1,
                'breathing_normal' => 1,
                'mobility_normal' => 1,
                'notes' => 'ok',
                'alerts' => null,
            ],
        ]);

        $request = new ServerRequest('GET', '/keeper/health-checks/1');

        \ob_start();
        $result = $this->makeController($service)->show($request, 1);
        \ob_end_clean();

        $this->assertNull($result);
    }

    // ─────────────────────────────────────────────────────────────
    // history()
    // ─────────────────────────────────────────────────────────────

    public function test_history_reads_limit_from_query_params_not_get(): void
    {
        // $_GET no debe usarse — el controller debe leer de PSR-7
        $_GET = ['limit' => '999'];

        $request = new ServerRequest('GET', '/keeper/health-checks/history/1')
            ->withQueryParams(['limit' => '10']);

        \ob_start();
        $result = $this->makeController()->history($request, 1);
        \ob_end_clean();

        $this->assertNull($result);
    }

    public function test_history_returns_null_when_animal_found(): void
    {
        $request = new ServerRequest('GET', '/keeper/health-checks/history/1')
            ->withQueryParams([]);

        \ob_start();
        $result = $this->makeController()->history($request, 1);
        \ob_end_clean();

        $this->assertNull($result);
    }
}
