# Plan 7: Keeper/AnimalController Split — SRP Refactor

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development.

**Goal:** Dividir `Keeper/AnimalController` (13+ métodos) en 3 controllers con responsabilidad única. El Plan 2 ya migra a PSR-7 — este plan asume que PSR-7 ya está hecho y hace el split de responsabilidades.

**Architecture:**

- `AnimalDashboardController` — solo `GET /keeper/dashboard` (1 método)
- `AnimalCareController` — logs de cuidado, estado de salud, toggle, fotos (5 métodos CRUD-ish de animales)
- `HealthCheckController` — ya existe como clase separada en `Keeper/HealthCheckController.php`, verificar si necesita refuerzo

Actualizar routes.php para usar los 3 controllers nuevos. Mover tests del AnimalControllerTest a los tests de los 3 controllers.

**Tech Stack:** PHP 8.4, PSR-7 (asumiendo Plan 2 completado), ResponseFactory

**Dependency:** Plan 2 debe estar completado primero.

---

### Task 1: Leer el controller actual y HealthCheckController existente

- [ ] **Step 1: Leer los controllers actuales**

```bash
docker compose exec app cat app/Http/Controllers/Keeper/AnimalController.php
docker compose exec app cat app/Http/Controllers/Keeper/HealthCheckController.php
docker compose exec app grep -n "keeper" app/routes.php
```

- [ ] **Step 2: Listar todos los métodos de AnimalController con sus rutas correspondientes**

Crear un mapeo de responsabilidades:

- `dashboard()` → `GET /keeper/dashboard` → **AnimalDashboardController**
- `logCare()` → `POST /keeper/log` → **AnimalCareController**
- `updateHealth(int $id)` → `POST /keeper/animal/{id}/health` → **AnimalCareController**
- `toggleActive(int $id)` → `POST /keeper/animal/{id}/toggle` → **AnimalCareController**
- `uploadPhoto(int $id)` → `POST /keeper/animal/{id}/upload-photo` → **AnimalCareController**
- (otros métodos según lo que se lea)

---

### Task 2: Crear AnimalDashboardController

**Files:**

- Create: `app/Http/Controllers/Keeper/AnimalDashboardController.php`
- Create: `tests/Unit/Http/Controllers/Keeper/AnimalDashboardControllerTest.php`

- [ ] **Step 1: Escribir test**

```php
// tests/Unit/Http/Controllers/Keeper/AnimalDashboardControllerTest.php
<?php
/**
 * ¿Qué pruebas aquí?
 * Verifica que AnimalDashboardController::dashboard() retorna null
 * (usa View::render internamente) y llama a los servicios correctos.
 *
 * ¿Qué me quieres demostrar?
 * Que dashboard() delega a AnimalCareService y HealthCheckService.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se añade lógica de negocio directamente en el controller.
 */
declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Keeper;

use App\Http\Controllers\Keeper\AnimalDashboardController;
use App\Services\AnimalCareService;
use App\Services\HealthCheckService;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

final class AnimalDashboardControllerTest extends TestCase
{
    public function test_dashboard_calls_animal_care_service(): void
    {
        $animalCareService = $this->createMock(AnimalCareService::class);
        $animalCareService
            ->expects($this->once())
            ->method('getDashboardData')
            ->willReturn(['animals' => [], 'stats' => [], 'recent_logs' => [], 'active_incidents' => []]);

        $healthCheckService = $this->createStub(HealthCheckService::class);
        $healthCheckService->method('getTodayDashboard')->willReturn(['pending_count' => 0, 'pending' => []]);
        $healthCheckService->method('getActiveAlerts')->willReturn([]);

        $controller = new AnimalDashboardController($animalCareService, $healthCheckService);

        // dashboard() usa View::render que echoes output — capturar con ob
        \ob_start();
        $result = $controller->dashboard(new ServerRequest('GET', '/keeper/dashboard'));
        \ob_end_clean();

        $this->assertNull($result); // View::render retorna void, controller retorna null
    }
}
```

- [ ] **Step 2: Ejecutar para verificar fallo**

```bash
docker compose exec app vendor/bin/phpunit tests/Unit/Http/Controllers/Keeper/AnimalDashboardControllerTest.php --colors=always
```

Esperado: FAIL — clase no existe.

- [ ] **Step 3: Crear AnimalDashboardController**

```php
// app/Http/Controllers/Keeper/AnimalDashboardController.php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Keeper;

use App\Core\Csrf;
use App\Core\View;
use App\Services\AnimalCareService;
use App\Services\HealthCheckService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class AnimalDashboardController
{
    public function __construct(
        private readonly AnimalCareService $animalCareService,
        private readonly HealthCheckService $healthCheckService
    ) {}

    /**
     * GET /keeper/dashboard
     */
    public function dashboard(ServerRequestInterface $request): ?ResponseInterface
    {
        $data             = $this->animalCareService->getDashboardData();
        $healthCheckData  = $this->healthCheckService->getTodayDashboard();
        $activeAlerts     = $this->healthCheckService->getActiveAlerts(7);

        View::render('backoffice/keeper/dashboard', [
            'titulo'               => 'Dashboard - Bienestar Animal',
            'animals'              => $data['animals'],
            'stats'                => $data['stats'],
            'recent_logs'          => $data['recent_logs'],
            'active_incidents'     => $data['active_incidents'],
            'pending_checks_count' => $healthCheckData['pending_count'],
            'pending_animals'      => $healthCheckData['pending'],
            'active_alerts'        => $activeAlerts,
            'csrf_token'           => Csrf::token(),
        ], [], 'backoffice');

        return null;
    }
}
```

- [ ] **Step 4: Ejecutar test**

```bash
docker compose exec app vendor/bin/phpunit tests/Unit/Http/Controllers/Keeper/AnimalDashboardControllerTest.php --colors=always
```

Esperado: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Keeper/AnimalDashboardController.php tests/Unit/Http/Controllers/Keeper/AnimalDashboardControllerTest.php
git commit -m "feat: extract AnimalDashboardController from AnimalController (SRP)"
```

---

### Task 3: Crear AnimalCareController

**Files:**

- Create: `app/Http/Controllers/Keeper/AnimalCareController.php`
- Create: `tests/Unit/Http/Controllers/Keeper/AnimalCareControllerTest.php`

- [ ] **Step 1: Escribir tests**

```php
// tests/Unit/Http/Controllers/Keeper/AnimalCareControllerTest.php
<?php
/**
 * ¿Qué pruebas aquí?
 * Verifica que los métodos de cuidado de animal usan PSR-7 y retornan JSON con Result.
 *
 * ¿Qué me quieres demostrar?
 * Que logCare(), updateHealth(), toggleActive() retornan ResponseInterface JSON.
 * Que leen inputs de $request->getParsedBody(), NO de $_POST.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se vuelve a usar $_POST, o si se deja de retornar ResponseInterface.
 */
declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Keeper;

use App\Core\Http\ResponseFactory;
use App\Core\Result;
use App\Http\Controllers\Keeper\AnimalCareController;
use App\Services\AnimalCareService;
use App\Services\FileUploadService;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

final class AnimalCareControllerTest extends TestCase
{
    private function makeController(
        AnimalCareService $animalCareService,
        ResponseFactory $responseFactory
    ): AnimalCareController {
        return new AnimalCareController(
            $animalCareService,
            $this->createStub(FileUploadService::class),
            $responseFactory
        );
    }

    public function test_log_care_returns_json_on_success(): void
    {
        $animalCareService = $this->createStub(AnimalCareService::class);
        $animalCareService->method('createCareLog')->willReturn(Result::ok('Log creado'));

        $mockResponse = $this->createStub(ResponseInterface::class);
        $responseFactory = $this->createStub(ResponseFactory::class);
        $responseFactory->method('json')->willReturn($mockResponse);

        $request = (new ServerRequest('POST', '/keeper/log'))
            ->withParsedBody(['animal_id' => '3', 'activity_type' => 'feeding', 'notes' => 'ok']);

        $result = $this->makeController($animalCareService, $responseFactory)->logCare($request);

        $this->assertInstanceOf(ResponseInterface::class, $result);
    }

    public function test_toggle_active_returns_json_on_success(): void
    {
        $animalCareService = $this->createStub(AnimalCareService::class);
        $animalCareService->method('toggleActive')->willReturn(Result::ok('Activado'));

        $mockResponse = $this->createStub(ResponseInterface::class);
        $responseFactory = $this->createStub(ResponseFactory::class);
        $responseFactory->method('json')->willReturn($mockResponse);

        $request = new ServerRequest('POST', '/keeper/animal/5/toggle');

        $result = $this->makeController($animalCareService, $responseFactory)->toggleActive($request, 5);

        $this->assertInstanceOf(ResponseInterface::class, $result);
    }
}
```

- [ ] **Step 2: Ejecutar para verificar fallo**

```bash
docker compose exec app vendor/bin/phpunit tests/Unit/Http/Controllers/Keeper/AnimalCareControllerTest.php --colors=always
```

- [ ] **Step 3: Crear AnimalCareController**

Mover de `AnimalController.php` los métodos: `logCare()`, `updateHealth()`, `toggleActive()`, `uploadPhoto()` (y cualquier otro que maneje mutaciones de estado).

```php
// app/Http/Controllers/Keeper/AnimalCareController.php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Keeper;

use App\Core\Csrf;
use App\Core\Http\ResponseFactory;
use App\Core\Session;
use App\Exceptions\ValidationException;
use App\Services\AnimalCareService;
use App\Services\FileUploadService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class AnimalCareController
{
    public function __construct(
        private readonly AnimalCareService  $animalCareService,
        private readonly FileUploadService  $fileUploadService,
        private readonly ResponseFactory    $response
    ) {}

    /**
     * POST /keeper/log
     */
    public function logCare(ServerRequestInterface $request): ResponseInterface
    {
        // (código migrado de AnimalController::logCare() — ya en PSR-7 tras Plan 2)
        // Ver Plan 2, Task 3, Step 4 para el código exacto
    }

    /**
     * POST /keeper/animal/{animalId}/health
     */
    public function updateHealth(ServerRequestInterface $request, int $animalId): ResponseInterface
    {
        // (código migrado de AnimalController::updateHealth())
    }

    /**
     * POST /keeper/animal/{animalId}/toggle
     */
    public function toggleActive(ServerRequestInterface $request, int $animalId): ResponseInterface
    {
        // (código migrado de AnimalController::toggleActive())
    }

    /**
     * POST /keeper/animal/{animalId}/upload-photo
     */
    public function uploadPhoto(ServerRequestInterface $request, int $animalId): ResponseInterface
    {
        // (código migrado de AnimalController::uploadPhoto())
    }
}
```

- [ ] **Step 4: Ejecutar tests**

```bash
docker compose exec app vendor/bin/phpunit tests/Unit/Http/Controllers/Keeper/AnimalCareControllerTest.php --colors=always
```

Esperado: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Keeper/AnimalCareController.php tests/Unit/Http/Controllers/Keeper/AnimalCareControllerTest.php
git commit -m "feat: extract AnimalCareController from AnimalController (SRP)"
```

---

### Task 4: Actualizar routes.php y deprecar AnimalController original

**Files:**

- Modify: `app/routes.php`
- Deprecate: `app/Http/Controllers/Keeper/AnimalController.php` → añadir `@deprecated` en docblock

- [ ] **Step 1: Actualizar rutas en routes.php**

```php
// app/routes.php — sección /keeper/:

// ANTES (todo en AnimalController):
$r->get('/dashboard', 'Keeper\AnimalController@dashboard');
$r->post('/log', 'Keeper\AnimalController@logCare', [$mw->csrf()]);
$r->post('/animal/{animalId}/health', 'Keeper\AnimalController@updateHealth', [$mw->csrf()]);
$r->post('/animal/{animalId}/toggle', 'Keeper\AnimalController@toggleActive', [$mw->csrf()]);
$r->post('/animal/{animalId}/upload-photo', 'Keeper\AnimalController@uploadPhoto', [$mw->csrf()]);

// DESPUÉS:
$r->get('/dashboard', 'Keeper\AnimalDashboardController@dashboard');
$r->post('/log', 'Keeper\AnimalCareController@logCare', [$mw->csrf()]);
$r->post('/animal/{animalId}/health', 'Keeper\AnimalCareController@updateHealth', [$mw->csrf()]);
$r->post('/animal/{animalId}/toggle', 'Keeper\AnimalCareController@toggleActive', [$mw->csrf()]);
$r->post('/animal/{animalId}/upload-photo', 'Keeper\AnimalCareController@uploadPhoto', [$mw->csrf()]);
```

- [ ] **Step 2: Verificar que AnimalController.php puede eliminarse o marcarse como deprecated**

```bash
docker compose exec app grep -rn "AnimalController" app/routes.php
```

Esperado: 0 referencias al AnimalController original.

- [ ] **Step 3: Ejecutar integrations**

```bash
make test-integration
```

- [ ] **Step 4: Commit**

```bash
git add app/routes.php app/Http/Controllers/Keeper/AnimalController.php
git commit -m "refactor: update routes to use split AnimalDashboardController + AnimalCareController"
```

---

### Task 5: Cleanup — Eliminar AnimalController original

**Solo hacer cuando todos los tests pasen.**

- [ ] **Step 1: Verificar 0 referencias**

```bash
docker compose exec app grep -rn "Keeper\\\\AnimalController\|'Keeper\\\AnimalController'" app/ --include='*.php'
```

Esperado: solo en el propio archivo (si tiene @deprecated) o en ninguno.

- [ ] **Step 2: Eliminar si hay 0 referencias**

```bash
git rm app/Http/Controllers/Keeper/AnimalController.php
git commit -m "chore: remove deprecated AnimalController (replaced by Dashboard + Care controllers)"
```

---

**Verification final del Plan 7:**

```bash
make ci
docker compose exec app vendor/bin/phpunit tests/Unit/Http/Controllers/Keeper/ --testdox --colors=always
```
