<?php

/**
 * ¿Qué pruebas aquí?
 * Verifica que AnimalIncidentController gestiona el flujo web de incidentes:
 * listado, crear, store y resolver.
 *
 * ¿Qué me quieres demostrar?
 * Que index() y create() renderizan vistas, que store() valida CSRF y llama al servicio,
 * que resolve() valida CSRF y llama resolveIncident(), y que ambas mutaciones redirigen.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se deja de usar CSRF, si se abandona el patrón Result, o si se cambia el destino del redirect.
 */

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Keeper;

use App\Core\Http\ResponseFactory;
use App\Core\Result;
use App\Http\Controllers\Keeper\AnimalIncidentController;
use App\Repositories\Contracts\AnimalIncidentRepositoryInterface;
use App\Repositories\Contracts\AnimalRepositoryInterface;
use App\Repositories\Contracts\HealthCheckRepositoryInterface;
use App\Services\AnimalCareService;
use App\Services\Contracts\AnimalCareServiceInterface;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

#[CoversClass(AnimalIncidentController::class)]
final class AnimalIncidentControllerTest extends TestCase
{
    private const CSRF_TOKEN = 'test-incident-csrf-xyz';

    protected function setUp(): void
    {
        if (\session_status() === PHP_SESSION_NONE) {
            \session_start();
        }
        $_SESSION['_csrf_token'] = self::CSRF_TOKEN;
        $_SESSION['user_id'] = 1;
        $_SESSION['user'] = ['id' => 1, 'name' => 'Keeper Test', 'roles' => ['keeper']];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    private function makeController(?AnimalCareServiceInterface $service = null): AnimalIncidentController
    {
        $animalRepo = $this->createStub(AnimalRepositoryInterface::class);
        $service ??= new AnimalCareService(
            animalRepo: $animalRepo,
            incidentRepo: $this->createStub(AnimalIncidentRepositoryInterface::class),
            healthCheckRepo: $this->createStub(HealthCheckRepositoryInterface::class),
        );

        return new AnimalIncidentController(
            $service,
            new ResponseFactory(),
            $animalRepo,
        );
    }

    // ─────────────────────────────────────────────────────────────
    // index
    // ─────────────────────────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_index_renders_view_and_returns_null(): void
    {
        $incidentRepo = $this->createStub(AnimalIncidentRepositoryInterface::class);
        $incidentRepo->method('getActiveIncidents')->willReturn([
            ['id' => 1, 'animal_name' => 'Leo', 'severity' => 'high', 'description' => 'Injury', 'status' => 'open', 'created_at' => '2024-01-15 10:30:00'],
        ]);

        $service = new AnimalCareService(
            animalRepo: $this->createStub(AnimalRepositoryInterface::class),
            incidentRepo: $incidentRepo,
            healthCheckRepo: $this->createStub(HealthCheckRepositoryInterface::class),
        );

        \ob_start();
        $result = $this->makeController($service)->index(new ServerRequest('GET', '/keeper/incidents'));
        \ob_end_clean();

        $this->assertNull($result);
    }

    // ─────────────────────────────────────────────────────────────
    // create
    // ─────────────────────────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_create_renders_form_and_returns_null(): void
    {
        \ob_start();
        $result = $this->makeController()->create(new ServerRequest('GET', '/keeper/incidents/create'));
        \ob_end_clean();

        $this->assertNull($result);
    }

    // ─────────────────────────────────────────────────────────────
    // store
    // ─────────────────────────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_store_creates_incident_and_redirects(): void
    {
        $service = $this->createStub(AnimalCareServiceInterface::class);
        $service->method('createIncident')->willReturn(Result::ok('Incidente reportado correctamente'));

        $request = new ServerRequest('POST', '/keeper/incidents')
            ->withParsedBody([
                'csrf_token' => self::CSRF_TOKEN,
                'animal_id' => '3',
                'severity' => 'high',
                'description' => 'Animal herido en el recinto trasero, requiere atención.',
            ]);

        $response = $this->makeController($service)->store($request);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString('/keeper/incidents', $response->getHeaderLine('Location'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_store_throws_on_invalid_csrf(): void
    {
        $request = new ServerRequest('POST', '/keeper/incidents')
            ->withParsedBody([
                'csrf_token' => 'invalid-token',
                'animal_id' => '3',
                'severity' => 'high',
                'description' => 'Animal herido en el recinto trasero.',
            ]);

        $this->expectException(\App\Exceptions\ValidationException::class);
        $this->makeController()->store($request);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_store_redirects_with_error_on_service_failure(): void
    {
        $service = $this->createStub(AnimalCareServiceInterface::class);
        $service->method('createIncident')->willReturn(Result::fail('Descripción demasiado corta'));

        $request = new ServerRequest('POST', '/keeper/incidents')
            ->withParsedBody([
                'csrf_token' => self::CSRF_TOKEN,
                'animal_id' => '3',
                'severity' => 'high',
                'description' => 'Corto',
            ]);

        $response = $this->makeController($service)->store($request);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame(302, $response->getStatusCode());
    }

    // ─────────────────────────────────────────────────────────────
    // show
    // ─────────────────────────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_show_renders_view_and_returns_null(): void
    {
        $service = $this->createStub(AnimalCareServiceInterface::class);
        $service->method('getIncidentById')->willReturn([
            'id' => 5,
            'animal_name' => 'Mochi',
            'severity' => 'medium',
            'description' => 'Descripción del incidente',
            'status' => 'open',
            'created_at' => '2024-01-15 10:30:00',
        ]);

        \ob_start();
        $result = $this->makeController($service)->show(
            new ServerRequest('GET', '/keeper/incidents/5'),
            5
        );
        \ob_end_clean();

        $this->assertNull($result);
    }

    // ─────────────────────────────────────────────────────────────
    // resolve
    // ─────────────────────────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_resolve_resolves_incident_and_redirects(): void
    {
        $service = $this->createStub(AnimalCareServiceInterface::class);
        $service->method('resolveIncident')->willReturn(Result::ok('Incidente resuelto correctamente'));

        $request = new ServerRequest('POST', '/keeper/incidents/5/resolve')
            ->withParsedBody([
                'csrf_token' => self::CSRF_TOKEN,
                'resolution' => 'Se trató al animal y quedó estable.',
            ]);

        $response = $this->makeController($service)->resolve($request, 5);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString('/keeper/incidents', $response->getHeaderLine('Location'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_resolve_throws_on_invalid_csrf(): void
    {
        $request = new ServerRequest('POST', '/keeper/incidents/5/resolve')
            ->withParsedBody([
                'csrf_token' => 'bad-token',
                'resolution' => 'Se trató al animal.',
            ]);

        $this->expectException(\App\Exceptions\ValidationException::class);
        $this->makeController()->resolve($request, 5);
    }
}
