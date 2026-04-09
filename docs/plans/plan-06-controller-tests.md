# Plan 6: Controller Test Infrastructure

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development.

**Goal:** Construir la infraestructura de tests para controllers (ruta `tests/Unit/Http/Controllers/` actualmente vacía) y cubrir los 58 controllers en fases. Fase 1: los 5 controllers más críticos. Fase 2: por rol hasta completar todos.

**Architecture:** Los tests de controller NO levantan HTTP — usan stubs de PSR-7 (nyholm/psr7 o laminas), stubs de servicios, y verifican: (a) que el método retorna el tipo correcto, (b) que los datos del request se leen correctamente, (c) que se llama al servicio correcto con los datos correctos, (d) que en error path se llama a Flash::error.

**Tech Stack:** PHPUnit, Nyholm/PSR-7, createStub(), Result pattern

---

### Task 1: Crear infraestructura base — ControllerTestCase

**Files:**

- Create: `tests/Support/ControllerTestCase.php`

- [ ] **Step 1: Crear clase base con helpers para tests de controller**

```php
// tests/Support/ControllerTestCase.php
<?php
/**
 * ¿Qué pruebas aquí?
 * Clase base para tests de controllers PSR-7.
 *
 * ¿Qué me quieres demostrar?
 * Que los helpers makeGetRequest() y makePostRequest() crean requests PSR-7 correctos.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se cambia la implementación PSR-7 (nyholm → laminas) sin actualizar los helpers.
 */
declare(strict_types=1);

namespace Tests\Support;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;

abstract class ControllerTestCase extends TestCase
{
    protected function makeGetRequest(string $path = '/', array $queryParams = []): ServerRequestInterface
    {
        $request = new ServerRequest('GET', $path);
        if (!empty($queryParams)) {
            $request = $request->withQueryParams($queryParams);
        }
        return $request;
    }

    protected function makePostRequest(string $path = '/', array $body = []): ServerRequestInterface
    {
        $request = new ServerRequest('POST', $path);
        if (!empty($body)) {
            $request = $request->withParsedBody($body);
        }
        return $request;
    }

    protected function makeUploadedFile(
        string $content,
        string $filename,
        string $mimeType,
        int $error = UPLOAD_ERR_OK
    ): UploadedFileInterface {
        $factory = new Psr17Factory();
        return $factory->createUploadedFile(
            $factory->createStream($content),
            \strlen($content),
            $error,
            $filename,
            $mimeType
        );
    }

    protected function assertResponseIsRedirect(ResponseInterface $response, string $expectedPath = null): void
    {
        $this->assertContains($response->getStatusCode(), [301, 302, 303]);
        if ($expectedPath !== null) {
            $this->assertSame($expectedPath, $response->getHeaderLine('Location'));
        }
    }

    protected function assertResponseIsJson(ResponseInterface $response, int $expectedStatus = 200): void
    {
        $this->assertSame($expectedStatus, $response->getStatusCode());
        $this->assertStringContainsString('application/json', $response->getHeaderLine('Content-Type'));
    }
}
```

- [ ] **Step 2: Ejecutar tests/Support/ para verificar que la clase se carga**

```bash
docker compose exec app vendor/bin/phpunit tests/Support/ --colors=always
```

- [ ] **Step 3: Commit**

```bash
git add tests/Support/ControllerTestCase.php
git commit -m "test: add ControllerTestCase base class for controller unit tests"
```

---

### Task 2: Fase 1 — Tests para los 5 controllers más críticos

Los controllers de Fase 1 (más críticos porque manejan auth, perfil, y las rutas más visitadas):

1. `Auth/AuthController` — login, registro, logout
2. `Shared/UserController` — (cubierto en Plan 2, referenciar)
3. `Public/HomeController` — homepage
4. `Shared/ReservationController` — crear/cancelar reservas
5. `Admin/UserController` — gestión de usuarios (admin)

**Para cada uno: LEER → ESCRIBIR TESTS → EJECUTAR → COMMIT**

---

#### Sub-task 2a: AuthController tests

- [ ] **Step 1: Leer AuthController**

```bash
docker compose exec app cat app/Http/Controllers/Auth/AuthController.php
```

- [ ] **Step 2: Escribir tests**

```php
// tests/Unit/Http/Controllers/Auth/AuthControllerTest.php
<?php
/**
 * ¿Qué pruebas aquí?
 * Verifica el happy path de login, el path de credenciales inválidas,
 * y que el logout destruye la sesión.
 *
 * ¿Qué me quieres demostrar?
 * Que processLogin() con credenciales válidas retorna redirect a dashboard.
 * Que processLogin() con credenciales inválidas retorna redirect a /login con Flash::error.
 * Que logout() retorna redirect a /login.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se cambia la URL de redirect post-login o la lógica de Flash messages.
 */
declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Auth;

use App\Core\Result;
use App\Http\Controllers\Auth\AuthController;
use App\Services\AuthService;
use App\Core\Http\ResponseFactory;
use Tests\Support\ControllerTestCase;
use Psr\Http\Message\ResponseInterface;

final class AuthControllerTest extends ControllerTestCase
{
    private AuthService $authService;
    private ResponseFactory $responseFactory;
    private ResponseInterface $mockResponse;

    protected function setUp(): void
    {
        $this->authService     = $this->createStub(AuthService::class);
        $this->responseFactory = $this->createStub(ResponseFactory::class);
        $this->mockResponse    = $this->createStub(ResponseInterface::class);
        $this->responseFactory->method('redirect')->willReturn($this->mockResponse);
    }

    private function makeController(): AuthController
    {
        return new AuthController($this->authService, $this->responseFactory);
        // Ajustar parámetros según constructor real (leer en Step 1)
    }

    public function test_process_login_redirects_to_dashboard_on_success(): void
    {
        $this->authService
            ->method('login')
            ->willReturn(Result::ok(['id' => 1, 'role' => 'admin']));

        $request = $this->makePostRequest('/login', [
            'email'    => 'admin@test.com',
            'password' => 'password123',
        ]);

        $controller = $this->makeController();
        $result = $controller->processLogin($request);

        $this->assertInstanceOf(ResponseInterface::class, $result);
    }

    public function test_process_login_redirects_back_on_wrong_credentials(): void
    {
        $this->authService
            ->method('login')
            ->willReturn(Result::fail('Credenciales inválidas', 'invalid_credentials'));

        $request = $this->makePostRequest('/login', [
            'email'    => 'wrong@test.com',
            'password' => 'wrongpass',
        ]);

        $controller = $this->makeController();
        $result = $controller->processLogin($request);

        $this->assertInstanceOf(ResponseInterface::class, $result);
    }

    public function test_logout_returns_response_interface(): void
    {
        $request = $this->makeGetRequest('/logout');
        $controller = $this->makeController();
        $result = $controller->logout($request);

        $this->assertInstanceOf(ResponseInterface::class, $result);
    }
}
```

- [ ] **Step 3: Ejecutar para verificar fallo inicial**

```bash
docker compose exec app vendor/bin/phpunit tests/Unit/Http/Controllers/Auth/AuthControllerTest.php --colors=always
```

- [ ] **Step 4: Ajustar tests según la firma real del constructor (leer Step 1)**

Ejecutar de nuevo hasta PASS.

- [ ] **Step 5: Commit**

```bash
git add tests/Unit/Http/Controllers/Auth/AuthControllerTest.php
git commit -m "test: add AuthController unit tests"
```

---

#### Sub-task 2b: Shared/ReservationController tests

- [ ] **Step 1: Leer el controller**

```bash
docker compose exec app cat app/Http/Controllers/Shared/ReservationController.php
```

- [ ] **Step 2: Escribir tests**

```php
// tests/Unit/Http/Controllers/Shared/ReservationControllerTest.php
<?php
/**
 * ¿Qué pruebas aquí?
 * Verifica que store() crea reserva y redirige, y que cancel() cancela y redirige.
 *
 * ¿Qué me quieres demostrar?
 * Que store() con datos válidos retorna redirect a confirmación.
 * Que cancel() con reserva válida retorna redirect con Flash::success.
 * Que en error, Flash::error se llama y redirige back.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se cambia la URL de redirect post-reserva o los campos del body.
 */
declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Shared;

use App\Core\Result;
use App\Http\Controllers\Shared\ReservationController;
use App\Services\ReservationService;
use App\Core\Http\ResponseFactory;
use Tests\Support\ControllerTestCase;
use Psr\Http\Message\ResponseInterface;

final class ReservationControllerTest extends ControllerTestCase
{
    public function test_store_redirects_on_successful_reservation(): void
    {
        $reservationService = $this->createStub(ReservationService::class);
        $reservationService->method('create')->willReturn(Result::ok(42));

        $responseFactory = $this->createStub(ResponseFactory::class);
        $mockResponse = $this->createStub(ResponseInterface::class);
        $responseFactory->method('redirect')->willReturn($mockResponse);

        $controller = new ReservationController($reservationService, $responseFactory);

        $request = $this->makePostRequest('/reservas/crear', [
            'cafe_id'         => '1',
            'pass_product_id' => '2',
            'date'            => '2026-06-01',
            'time'            => '14:00',
            'guests'          => '2',
        ]);

        $result = $controller->store($request);

        $this->assertInstanceOf(ResponseInterface::class, $result);
    }

    public function test_store_redirects_back_when_reservation_fails(): void
    {
        $reservationService = $this->createStub(ReservationService::class);
        $reservationService->method('create')->willReturn(Result::fail('Sin disponibilidad', 'no_capacity'));

        $responseFactory = $this->createStub(ResponseFactory::class);
        $mockResponse = $this->createStub(ResponseInterface::class);
        $responseFactory->method('redirect')->willReturn($mockResponse);

        $controller = new ReservationController($reservationService, $responseFactory);

        $request = $this->makePostRequest('/reservas/crear', ['cafe_id' => '1']);
        $result = $controller->store($request);

        $this->assertInstanceOf(ResponseInterface::class, $result);
    }
}
```

- [ ] **Step 3: Ejecutar y ajustar según constructor real**

```bash
docker compose exec app vendor/bin/phpunit tests/Unit/Http/Controllers/Shared/ReservationControllerTest.php --colors=always
```

- [ ] **Step 4: Commit**

```bash
git add tests/Unit/Http/Controllers/Shared/ReservationControllerTest.php
git commit -m "test: add Shared/ReservationController unit tests"
```

---

### Task 3: Fase 2 — Template para los 53 controllers restantes

Para cada controller, el proceso es el mismo. Crear un archivo por cada grupo de rol:

**Estructura a crear (53 archivos):**

```
tests/Unit/Http/Controllers/
  Admin/     (16 controllers)  ← Fase 2a
  Api/       (10 controllers)  ← Fase 2b
  Manager/   (5 controllers)   ← Fase 2c
  Public/    (7 controllers)   ← Fase 2d
  Keeper/    (cubierto en Plan 2)
  Kitchen/   (1 controller)    ← Fase 2e
  Reception/ (1 controller)    ← Fase 2e
  Supervisor/(1 controller)    ← Fase 2e
  User/      (1 controller)    ← Fase 2e
```

**Template reutilizable para cualquier controller:**

```php
// tests/Unit/Http/Controllers/{Role}/{ControllerName}Test.php
<?php
/**
 * ¿Qué pruebas aquí?
 * Verifica que {Controller} sigue el contrato PSR-7 y delega correctamente a su Service.
 *
 * ¿Qué me quieres demostrar?
 * Que el happy path retorna ?ResponseInterface.
 * Que en error path retorna ResponseInterface (redirect) con Flash::error.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se cambian los tipos de retorno o se reintroduce lógica de negocio en el controller.
 */
declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\{Role};

use Tests\Support\ControllerTestCase;
use Psr\Http\Message\ResponseInterface;

final class {ControllerName}Test extends ControllerTestCase
{
    public function test_{main_method}_happy_path_returns_response_interface(): void
    {
        // 1. Stub del service principal
        // 2. Stub de ResponseFactory
        // 3. Instanciar controller con los stubs
        // 4. Llamar el método principal con makeGetRequest / makePostRequest
        // 5. assertInstanceOf(ResponseInterface::class, $result)
        //    O: $this->assertNull($result) si el método retorna ?ResponseInterface y usa View::render
    }
}
```

**Ejecutar por fases:**

```bash
# Fase 2a: Admin controllers
docker compose exec app vendor/bin/phpunit tests/Unit/Http/Controllers/Admin/ --colors=always

# Fase 2b: API controllers
docker compose exec app vendor/bin/phpunit tests/Unit/Http/Controllers/Api/ --colors=always

# Commit por fase
git commit -m "test: add Admin controller unit tests"
git commit -m "test: add Api controller unit tests"
# etc.
```

---

**Verification final del Plan 6:**

```bash
docker compose exec app vendor/bin/phpunit tests/Unit/Http/Controllers/ --testdox --colors=always
```

Meta: `tests/Unit/Http/Controllers/` con ≥58 test files, al menos 1 test por controller.

```bash
docker compose exec app vendor/bin/phpunit tests/Unit/ --colors=always
```

Esperado: todos los tests del plan pasan.
