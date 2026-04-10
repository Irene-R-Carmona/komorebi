# Fase 2 — PSR-7 Migration: UserController + AnimalController

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps marked ✅ son confirmaciones de estado — NO re-implementar lo que ya está hecho.

**Goal:** Migrar `Admin\UserController` y `Admin\AnimalController` al contrato PSR-7 del proyecto: todos los métodos reciben `ServerRequestInterface $request`, retornan `?ResponseInterface`, y no acceden a superglobales (`$_POST`, `$_GET`) directamente. Son los únicos dos controladores Admin sin este contrato — desbloquean Fase 3 y 4.

**Architecture:** La referencia correcta del proyecto es `Auth\AuthController` y `Keeper\AnimalController` (PSR-7 completos). El router PSR-15 ya inyecta `$request` automáticamente como primer argumento de cada método de controlador — solo falta declararlo en la firma. `$_POST` se reemplaza con `(array) $request->getParsedBody()`, `$_GET` con `$request->getQueryParams()`. `index()` en `UserController` retorna `void` en lugar de `?ResponseInterface` — también se corrige.

**Tech Stack:** PHP 8.4, PSR-7 `ServerRequestInterface`, `ResponseFactory`, PHPUnit 12 `createStub()`.

---

## Estado confirmado por auditoría (NO re-implementar)

| Item | Estado | Evidencia |
|---|---|---|
| Router PSR-15 inyecta `$request` automáticamente | ✅ | `public/index.php` + `app/Core/Router.php` — pipeline PSR-15 |
| `Keeper\AnimalController` PSR-7 correcto | ✅ | Referencia: recibe `$request`, usa `getParsedBody()` |
| `Auth\AuthController` PSR-7 correcto | ✅ | Referencia: recibe `$request`, usa `getParsedBody()` |
| `tests/Unit/Controllers/Admin/` existente | ❌ | No existe — crear directorio y tests nuevos |
| Superglobales en `UserController` | ❌ | `$_POST` en L105–109 (`create`) y L142–149 (`update`) |
| Superglobales en `AnimalController` | ❌ | `$_POST` en L61–68, L105–114, L130; `$_GET` en L84 |

---

## Mapa de archivos afectados

| Grupo | Archivo | Acción |
|---|---|---|
| A | `app/Http/Controllers/Admin/UserController.php` | Migrar 6 métodos a PSR-7 |
| B | `app/Http/Controllers/Admin/AnimalController.php` | Migrar 6 métodos a PSR-7 |
| C | `tests/Unit/Controllers/Admin/UserControllerTest.php` | CREAR — tests con request mock |
| D | `tests/Unit/Controllers/Admin/AnimalControllerTest.php` | CREAR — tests con request mock |

> **Fuera de alcance:** `Admin\CafeController` tiene el mismo problema (`index()` retorna `void`, `create()` usa `$_POST`) pero no está en Fase 2 — se puede incluir en un plan separado si se desea.

---

## Grupo A — Migrar `Admin\UserController`

### A1: Leer el archivo completo antes de editar

- [ ] Leer `app/Http/Controllers/Admin/UserController.php` completo — confirmar líneas exactas de `$_POST` y firmas de métodos

### A2: Editar los 6 métodos uno a uno

- [ ] `index()` — cambiar firma de `public function index(): void` → `public function index(ServerRequestInterface $request): ?ResponseInterface`; al final del método añadir `return null;` si termina con `View::render()`
- [ ] `getUsersList()` — añadir `ServerRequestInterface $request` como primer parámetro
- [ ] `create()` — añadir `ServerRequestInterface $request`; reemplazar bloque `$_POST`:

  ```php
  // ANTES:
  $data = ['name' => isset($_POST['name']) ? ... $_POST['email'] ...];
  // DESPUÉS:
  $body = (array) $request->getParsedBody();
  $data = ['name' => isset($body['name']) ? trim($body['name']) : '', 'email' => $body['email'] ?? '', ...];
  ```

- [ ] `update(int $userId)` — cambiar firma a `update(ServerRequestInterface $request, int $userId)`; reemplazar `$_POST` en L142–149 con `$body = (array) $request->getParsedBody()`
- [ ] `delete(int $userId)` — cambiar firma a `delete(ServerRequestInterface $request, int $userId)`
- [ ] `toggleActive(int $userId)` — cambiar firma a `toggleActive(ServerRequestInterface $request, int $userId)`
- [ ] Añadir `use Psr\Http\Message\ServerRequestInterface;` al bloque de `use` si no existe

- [ ] Ejecutar `make phpstan` — sin nuevos errores
- [ ] Ejecutar `make test-unit` — verde
- [ ] Commit: `refactor: migrar Admin\UserController a PSR-7 (ServerRequestInterface + eliminar $_POST)`

---

## Grupo B — Migrar `Admin\AnimalController`

### B1: Leer el archivo completo antes de editar

- [ ] Leer `app/Http/Controllers/Admin/AnimalController.php` completo — confirmar líneas exactas de `$_POST`, `$_GET` y firmas

### B2: Editar los 6 métodos uno a uno

- [ ] `index()` — añadir `ServerRequestInterface $request` como primer parámetro (ya retorna `?ResponseInterface`: ✅)
- [ ] `create()` — añadir `ServerRequestInterface $request`
- [ ] `store()` — añadir `ServerRequestInterface $request`; reemplazar bloque `$_POST` en L61–68:

  ```php
  // ANTES:
  $data = ['name' => $_POST['name'], 'species' => $_POST['species'], ...];
  // DESPUÉS:
  $body = (array) $request->getParsedBody();
  $data = ['name' => $body['name'] ?? '', 'species' => $body['species'] ?? '', ...];
  ```

- [ ] `edit()` — añadir `ServerRequestInterface $request`; reemplazar `$_GET` en L84:

  ```php
  // ANTES:
  $id = (int) ($_GET['id'] ?? 0);
  // DESPUÉS:
  $query = $request->getQueryParams();
  $id = (int) ($query['id'] ?? 0);
  ```

- [ ] `update()` — añadir `ServerRequestInterface $request`; reemplazar `$_POST` en L105–114:

  ```php
  $body = (array) $request->getParsedBody();
  $id = (int) ($body['id'] ?? 0);
  ```

- [ ] `delete()` — añadir `ServerRequestInterface $request`; reemplazar `$_POST` en L130:

  ```php
  $body = (array) $request->getParsedBody();
  $id = (int) ($body['id'] ?? 0);
  ```

- [ ] Añadir `use Psr\Http\Message\ServerRequestInterface;` si no existe

- [ ] Ejecutar `make phpstan` — sin nuevos errores
- [ ] Ejecutar `make test-unit` — verde
- [ ] Commit: `refactor: migrar Admin\AnimalController a PSR-7 (ServerRequestInterface + eliminar $_POST/$_GET)`

---

## Grupo C — Crear `UserControllerTest`

### C1: Preparar el setUp() con stubs

**Docblock requerido:**

```php
/**
 * ¿Qué pruebas aquí?
 * Métodos de Admin\UserController: responses, redirects y acceso a request data.
 * ¿Qué me quieres demostrar?
 * Que el controlador retorna ResponseInterface correcta y delega al servicio sin tocar superglobales.
 * ¿Qué va a fallar en este test si se cambia el código?
 * Cambios en el tipo de retorno, las rutas de redirect o la firma de los métodos.
 */
```

**setUp():**

```php
protected function setUp(): void
{
    parent::setUp();
    $this->userManagementService = $this->createStub(\App\Services\UserManagementService::class);
    $this->response              = $this->createStub(\App\Core\Http\ResponseFactory::class);
    $this->request               = $this->createStub(\Psr\Http\Message\ServerRequestInterface::class);

    $this->controller = new UserController($this->userManagementService, $this->response);
}
```

**Tests mínimos a implementar:**

- [ ] `testIndexReturnsNull` — `index($request)` retorna `null` (path View::render)
- [ ] `testCreateWithValidDataRedirects` — stub `getParsedBody()` con datos válidos; stub `UserManagementService::create()` retorna `Result::ok()`; afirmar retorno es `ResponseInterface`
- [ ] `testCreateWithInvalidDataRedirects` — stub `UserManagementService::create()` retorna `Result::fail()`; afirmar redirect con error
- [ ] `testDeleteReturnRedirect` — stub `UserManagementService::delete()` retorna `Result::ok()`; afirmar `ResponseInterface`
- [ ] `testToggleActiveReturnsRedirect` — ídem para toggle

- [ ] Ejecutar `vendor/bin/phpunit tests/Unit/Controllers/Admin/UserControllerTest.php --testdox`
- [ ] Commit: `test: añadir UserControllerTest para Admin`

---

## Grupo D — Crear `AnimalControllerTest`

### D1: Preparar setUp() con stubs

Misma estructura — stub `AnimalCareService` (o el servicio que use `AnimalController`), `ResponseFactory`, `ServerRequestInterface`.

**Docblock requerido:** (mismo patrón que Grupo C, adaptando el nombre del controlador)

**Tests mínimos:**

- [ ] `testIndexReturnsNull`
- [ ] `testStoreWithValidDataRedirects`
- [ ] `testStoreWithInvalidDataRedirects`
- [ ] `testUpdateReturnRedirect`
- [ ] `testDeleteReturnRedirect`

- [ ] Ejecutar `vendor/bin/phpunit tests/Unit/Controllers/Admin/AnimalControllerTest.php --testdox`
- [ ] Commit: `test: añadir AnimalControllerTest para Admin`

---

## Comandos de verificación

```bash
# PHPStan tras cada grupo
make phpstan

# Tests del controlador editado
docker compose exec app vendor/bin/phpunit tests/Unit/Controllers/Admin/UserControllerTest.php --testdox
docker compose exec app vendor/bin/phpunit tests/Unit/Controllers/Admin/AnimalControllerTest.php --testdox

# Suite completa
make test-unit
make ci
```

---

## Commits sugeridos

```
refactor: migrar Admin\UserController a PSR-7 (ServerRequestInterface + eliminar $_POST)
refactor: migrar Admin\AnimalController a PSR-7 (ServerRequestInterface + eliminar $_POST/$_GET)
test: añadir UserControllerTest para Admin
test: añadir AnimalControllerTest para Admin
```

---

## Siguiente plan

**Plan 3 — DI Services (Fase 3):** Depende de Fase 2 completada. Objetivo: eliminar el patrón `?? new` de los controladores restantes y registrar todos los servicios correctamente en el container.
