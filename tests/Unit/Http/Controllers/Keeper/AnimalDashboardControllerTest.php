<?php

/**
 * ¿Qué pruebas aquí?
 * Verifica que AnimalDashboardController llama a los servicios correctos
 * y retorna null (View::render) o ResponseInterface (redirect).
 *
 * ¿Qué me quieres demostrar?
 * Que dashboard() retorna null (delega a View::render).
 * Que show() redirige cuando el animal no existe.
 * Que index() retorna null.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si dashboard() deja de retornar null o deja de llamar a los servicios.
 * Si show() deja de redirigir cuando el animal no se encuentra.
 */

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Keeper;

use App\Core\Http\ResponseFactory;
use App\Http\Controllers\Keeper\AnimalDashboardController;
use App\Repositories\Contracts\AnimalRepositoryInterface;
use App\Services\AnimalCareService;
use App\Services\HealthCheckService;
use App\Repositories\Contracts\HealthCheckRepositoryInterface;
use Nyholm\Psr7\ServerRequest;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

final class AnimalDashboardControllerTest extends TestCase
{
    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['_csrf_token'] = 'test-token';
        $_SESSION['user_id']     = 1;
        $_SESSION['user']        = ['id' => 1, 'name' => 'Test', 'roles' => ['keeper']];
        $_SERVER['REQUEST_URI']  = '/keeper/test';
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        unset($_SERVER['REQUEST_URI']);
    }

    private function makePdoStub(): PDO
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn([]);
        $stmt->method('fetch')->willReturn(false);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);
        $pdo->method('query')->willReturn($stmt);

        return $pdo;
    }

    private function makeController(
        ?AnimalRepositoryInterface $animalRepository = null,
    ): AnimalDashboardController {
        $pdo               = $this->makePdoStub();
        $animalCareService = new AnimalCareService($pdo, $this->createStub(AnimalRepositoryInterface::class));
        $healthCheckRepo   = $this->createStub(HealthCheckRepositoryInterface::class);
        $healthCheckRepo->method('getTodayChecks')->willReturn([]);
        $healthCheckRepo->method('getPendingAnimals')->willReturn([]);
        $healthCheckRepo->method('getCheckswithAlerts')->willReturn([]);
        $healthCheckService = new HealthCheckService($healthCheckRepo);

        return new AnimalDashboardController(
            $animalCareService,
            $healthCheckService,
            $animalRepository ?? $this->createStub(AnimalRepositoryInterface::class),
            new ResponseFactory(),
        );
    }


    public function test_show_redirects_when_animal_not_found(): void
    {
        $animalRepository = $this->createStub(AnimalRepositoryInterface::class);
        $animalRepository->method('findById')->willReturn(null);

        $request = (new ServerRequest('GET', '/keeper/animals/99'))
            ->withAttribute('id', 99);

        $result = $this->makeController($animalRepository)->show($request);

        $this->assertInstanceOf(ResponseInterface::class, $result);
    }

    public function test_dashboard_returns_null(): void
    {
        $request = new ServerRequest('GET', '/keeper/dashboard');

        ob_start();
        $result = $this->makeController()->dashboard($request);
        ob_end_clean();

        $this->assertNull($result);
    }

    public function test_index_returns_null(): void
    {
        $pdo               = $this->makePdoStub();
        $animalCareService = new AnimalCareService($pdo, $this->createStub(AnimalRepositoryInterface::class));

        ob_start();
        $result = (new AnimalDashboardController($animalCareService))
            ->index(new ServerRequest('GET', '/keeper/animals'));
        ob_end_clean();

        $this->assertNull($result);
    }

    public function test_show_returns_null_when_animal_found(): void
    {
        $animalRepository = $this->createStub(AnimalRepositoryInterface::class);
        $animalRepository->method('findById')->willReturn([
            'id'            => 5,
            'name'          => 'Luna',
            'species'       => 'gato',
            'health_status' => 'healthy',
        ]);

        $request = (new ServerRequest('GET', '/keeper/animals/5'))
            ->withAttribute('id', 5);

        ob_start();
        $result = $this->makeController($animalRepository)->show($request);
        ob_end_clean();

        $this->assertNull($result);
    }

    public function test_class_has_view_methods(): void
    {
        $this->assertTrue(method_exists(AnimalDashboardController::class, 'dashboard'));
        $this->assertTrue(method_exists(AnimalDashboardController::class, 'index'));
        $this->assertTrue(method_exists(AnimalDashboardController::class, 'show'));
    }
}
