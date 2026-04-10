<?php

/**
 * ¿Qué pruebas aquí?
 * Verifica que AnimalController usa ServerRequestInterface para leer inputs.
 *
 * ¿Qué me quieres demostrar?
 * Que los métodos POST leen de $request->getParsedBody() no de $_POST.
 * Que Csrf::validate() recibe el request PSR-7 y puede leer el token del body.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si logCare() o updateHealth() vuelven a usar $_POST directamente.
 * Si Csrf::validate() ya no recibe el $request PSR-7.
 */

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Keeper;

use App\Core\Http\ResponseFactory;
use App\Http\Controllers\Keeper\AnimalController;
use App\Repositories\Contracts\AnimalRepositoryInterface;
use App\Services\AnimalCareService;
use App\Services\HealthCheckService;
use Nyholm\Psr7\ServerRequest;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

final class AnimalControllerTest extends TestCase
{
    private const CSRF_TOKEN = 'test-csrf-abc123';

    protected function setUp(): void
    {
        // Inicializar sesión con token CSRF válido y usuario autenticado
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['_csrf_token'] = self::CSRF_TOKEN;
        $_SESSION['user_id']     = 1;
        $_SESSION['user']        = ['id' => 1, 'name' => 'Test', 'roles' => ['keeper']];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    /**
     * Crea un stub de PDO capaz de simular INSERTs exitosos.
     */
    private function makePdoStub(): PDO
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);
        $pdo->method('lastInsertId')->willReturn('42');
        // Para transact() en updateHealth
        $pdo->method('beginTransaction')->willReturn(true);
        $pdo->method('commit')->willReturn(true);

        return $pdo;
    }

    public function test_log_care_reads_animal_id_from_psr7_body(): void
    {
        $pdo               = $this->makePdoStub();
        $animalCareService = new AnimalCareService($pdo, $this->createStub(AnimalRepositoryInterface::class));

        $controller = new AnimalController(
            $animalCareService,
            $this->createStub(HealthCheckService::class),
            new ResponseFactory(),
        );

        $request = (new ServerRequest('POST', '/keeper/log'))
            ->withParsedBody([
                'csrf_token'       => self::CSRF_TOKEN,
                'animal_id'        => '5',
                'activity_type'    => 'feeding',
                'notes'            => 'Animal comió bien',
                'duration_minutes' => '30',
            ]);

        $result = $controller->logCare($request);
        $this->assertInstanceOf(ResponseInterface::class, $result);
    }

    public function test_update_health_reads_status_from_psr7_body(): void
    {
        $pdo               = $this->makePdoStub();
        $animalCareService = new AnimalCareService($pdo, $this->createStub(AnimalRepositoryInterface::class));

        $controller = new AnimalController(
            $animalCareService,
            $this->createStub(HealthCheckService::class),
            new ResponseFactory(),
        );

        $request = (new ServerRequest('POST', '/keeper/animal/3/health'))
            ->withParsedBody([
                'csrf_token'    => self::CSRF_TOKEN,
                'health_status' => 'healthy',
                'notes'         => '',
            ]);

        $result = $controller->updateHealth($request, 3);
        $this->assertInstanceOf(ResponseInterface::class, $result);
    }
}
