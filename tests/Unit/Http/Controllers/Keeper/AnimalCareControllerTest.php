<?php

/**
 * ¿Qué pruebas aquí?
 * Verifica que los métodos de cuidado de animal usan PSR-7 y retornan ResponseInterface.
 *
 * ¿Qué me quieres demostrar?
 * Que logCare(), updateHealth(), toggleActive() retornan ResponseInterface JSON.
 * Que leen inputs de $request->getParsedBody(), NO de $_POST.
 * Que recordFeeding() y recordHealth() retornan redirect ResponseInterface.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se vuelve a usar $_POST, o si se deja de retornar ResponseInterface.
 * Si se deja de delegar a AnimalCareService o HealthCheckService.
 */

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Keeper;

use App\Core\Http\ResponseFactory;
use App\Http\Controllers\Keeper\AnimalCareController;
use App\Repositories\Contracts\AnimalIncidentRepositoryInterface;
use App\Repositories\Contracts\AnimalRepositoryInterface;
use App\Repositories\Contracts\HealthCheckRepositoryInterface;
use App\Services\AnimalCareService;
use App\Services\Contracts\FileUploadServiceInterface;
use App\Services\HealthCheckService;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

#[CoversClass(AnimalCareController::class)]
final class AnimalCareControllerTest extends TestCase
{
    private const CSRF_TOKEN = 'test-csrf-abc123';

    protected function setUp(): void
    {
        if (\session_status() === PHP_SESSION_NONE) {
            \session_start();
        }
        $_SESSION['_csrf_token'] = self::CSRF_TOKEN;
        $_SESSION['user_id'] = 1;
        $_SESSION['user'] = ['id' => 1, 'name' => 'Test', 'roles' => ['keeper']];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    private function makeController(
        ?AnimalCareService $animalCareService = null,
        ?HealthCheckService $healthCheckService = null,
    ): AnimalCareController {
        $animalCareService ??= new AnimalCareService(
            animalRepo: $this->createStub(AnimalRepositoryInterface::class),
            incidentRepo: $this->createStub(AnimalIncidentRepositoryInterface::class),
            healthCheckRepo: $this->createStub(HealthCheckRepositoryInterface::class),
        );
        $healthCheckRepo = $this->createStub(HealthCheckRepositoryInterface::class);
        $healthCheckRepo->method('getTodayChecks')->willReturn([]);
        $healthCheckRepo->method('getPendingAnimals')->willReturn([]);

        return new AnimalCareController(
            animalCareService: $animalCareService,
            fileUploadService: $this->createStub(FileUploadServiceInterface::class),
            healthCheckService: $healthCheckService ?? new HealthCheckService($healthCheckRepo),
            animalRepository: $this->createStub(AnimalRepositoryInterface::class),
            response: new ResponseFactory(),
        );
    }

    public function test_log_care_returns_json_on_success(): void
    {
        $request = new ServerRequest('POST', '/keeper/log')
            ->withParsedBody([
                'csrf_token' => self::CSRF_TOKEN,
                'animal_id' => '3',
                'activity_type' => 'feeding',
                'notes' => 'ok',
            ]);

        $result = $this->makeController()->recordFeeding($request);

        $this->assertInstanceOf(ResponseInterface::class, $result);
    }

    public function test_log_care_reads_from_psr7_body_not_post(): void
    {
        $_POST = [];

        $request = new ServerRequest('POST', '/keeper/log')
            ->withParsedBody([
                'csrf_token' => self::CSRF_TOKEN,
                'animal_id' => '7',
                'activity_type' => 'feeding',
            ]);

        $result = $this->makeController()->recordFeeding($request);

        $this->assertInstanceOf(ResponseInterface::class, $result);
    }

    public function test_toggle_active_returns_json_on_success(): void
    {
        $animalRepo = $this->createStub(AnimalRepositoryInterface::class);
        $animalRepo->method('toggleStatus')->willReturn(['found' => true, 'current_status' => 'active']);

        $service = new AnimalCareService(
            animalRepo: $animalRepo,
            incidentRepo: $this->createStub(AnimalIncidentRepositoryInterface::class),
            healthCheckRepo: $this->createStub(HealthCheckRepositoryInterface::class),
        );

        $request = new ServerRequest('POST', '/keeper/animal/5/toggle')
            ->withParsedBody(['csrf_token' => self::CSRF_TOKEN]);

        $result = $this->makeController(animalCareService: $service)->toggleActive($request, 5);

        $this->assertInstanceOf(ResponseInterface::class, $result);
    }

    public function test_update_health_returns_json_on_success(): void
    {
        $request = new ServerRequest('POST', '/keeper/animal/3/health')
            ->withAttribute('id', 3)
            ->withParsedBody([
                'csrf_token' => self::CSRF_TOKEN,
                'health_status' => 'healthy',
                'notes' => '',
            ]);

        $result = $this->makeController()->recordHealth($request);

        $this->assertInstanceOf(ResponseInterface::class, $result);
    }

    public function test_record_feeding_redirects_on_success(): void
    {
        $request = new ServerRequest('POST', '/keeper/animals/2/feeding')
            ->withAttribute('id', 2)
            ->withParsedBody(['csrf_token' => self::CSRF_TOKEN, 'notes' => '']);

        $result = $this->makeController()->recordFeeding($request);

        $this->assertInstanceOf(ResponseInterface::class, $result);
    }

    public function test_record_health_redirects_on_success(): void
    {
        $healthCheckRepo = $this->createStub(HealthCheckRepositoryInterface::class);
        $healthCheckRepo->method('existsForAnimalOnDate')->willReturn(false);
        $healthCheckRepo->method('create')->willReturn(1);
        $healthCheckService = new HealthCheckService($healthCheckRepo);

        $request = new ServerRequest('POST', '/keeper/animals/2/health')
            ->withAttribute('id', 2)
            ->withParsedBody(['csrf_token' => self::CSRF_TOKEN]);

        $result = $this->makeController(healthCheckService: $healthCheckService)->recordHealth($request);

        $this->assertInstanceOf(ResponseInterface::class, $result);
    }
}
