# Centralización de Errores, Excepciones y Validaciones

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Aplicar el contrato de tres capas para errores en toda la codebase: los métodos públicos de Service siempre retornan `Result`, nunca lanzan excepciones de dominio. Eliminar código muerto de excepciones y helpers. Uniformizar la serialización de errores de la API a RFC 9457. Total: 5 fases independientes y ordenadas.

**Architecture — La regla de tres capas:**

```
[HTTP Layer]         FormRequest → throws ValidationException → controller catches immediately
                     Controllers → can throw domain exceptions → ErrorHandlerMiddleware handles
[Service PUBLIC]     Always returns Result. Never throws domain exceptions.
[Service PRIVATE]    May throw. Public method does try/catch boundary → Result.
[Infrastructure]     DatabaseException, ConfigurationException — always thrown
```

**Lo que NO cambia:** `FormRequest→ValidationException`, `ErrorHandlerMiddleware` + `ExceptionHandler::register()` (son complementarios, no duplicados), `ReservationService` private throws, todas las `@throws DatabaseException` en servicios (son infraestructura).

**Tech Stack:** PHP 8.4, `Result::ok($data)` / `Result::fail($msg, $code)`, `ResponseFactory::problem(Result $r, int $status)` (RFC 9457), PHPUnit 11.

---

## File Map

### Fase 1 — UserService → Result

| Fichero | Acción |
|---|---|
| `app/Services/UserService.php` | MODIFICAR — `getProfile()` y `getCurrentProfile()` retornan `Result` |
| `app/Http/Controllers/Shared/UserController.php` | MODIFICAR — 6 call sites de `getProfile()` + 1 de `getCurrentProfile()` |
| `app/Services/ReservationService.php` | VERIFICAR — ya tiene try/catch en su call site, simplificar |

### Fase 2 — ProductService → Result

| Fichero | Acción |
|---|---|
| `app/Services/ProductService.php` | MODIFICAR — `create()` y `update()` retornan `Result` |
| `app/Http/Controllers/Admin/ProductController.php` | MODIFICAR — 2 call sites |
| `app/Http/Controllers/Admin/MenuController.php` | MODIFICAR — 2 call sites con catch/re-throw (eliminar el catch) |

### Fase 3 — Eliminar código muerto

| Fichero | Acción |
|---|---|
| `app/Core/BaseService.php` | MODIFICAR — eliminar 4 métodos `assert*` (mantener `logInfo`/`logError`) |
| `app/Exceptions/BusinessRuleException.php` | MODIFICAR — eliminar 5 factory methods sin uso |

### Fase 4 — Documentar contrato en AGENTS.md

| Fichero | Acción |
|---|---|
| `AGENTS.md` | MODIFICAR — añadir sección "Error Handling Contract" en Critical Patterns |

### Fase 5 — RFC 9457 en ExceptionRenderers y AbstractApiController

| Fichero | Acción |
|---|---|
| `app/Http/ExceptionRenderers/AuthenticationExceptionRenderer.php` | MODIFICAR |
| `app/Http/ExceptionRenderers/AuthorizationExceptionRenderer.php` | MODIFICAR |
| `app/Http/ExceptionRenderers/NotFoundExceptionRenderer.php` | MODIFICAR |
| `app/Http/ExceptionRenderers/BusinessRuleExceptionRenderer.php` | MODIFICAR |
| `app/Http/ExceptionRenderers/ValidationExceptionRenderer.php` | MODIFICAR (ver nota) |
| `app/Http/ExceptionRenderers/FallbackExceptionRenderer.php` | MODIFICAR |
| `app/Http/Controllers/Api/AbstractApiController.php` | MODIFICAR — `error()` → `problem()` |
| `app/Http/Controllers/Api/*.php` (9 call sites de `$this->error()`) | MODIFICAR |

---

## Tasks

### Fase 1 — UserService: getProfile / getCurrentProfile → Result

**Contexto:**

- `getProfile(int $userId): array` — lanza `NotFoundException::user($userId)` si no existe
- `getCurrentProfile(): array` — lanza `AuthenticationException::notAuthenticated()` + delega a `getProfile()`
- 6 call sites de `getProfile()` en `UserController` — ninguno envuelve en try/catch
- 1 call site en `ReservationService` — sí envuelve en try/catch

- [ ] **1.1 — Modificar UserService::getProfile():** Cambiar firma a `public function getProfile(int $userId): Result` y cuerpo:

  ```php
  // Antes:
  if (!$user) { throw NotFoundException::user($userId); }
  return [ 'id' => ..., ... ];

  // Después:
  if (!$user) { return Result::fail('Usuario no encontrado', 'not_found'); }
  return Result::ok([
      'id' => (int) ($user['id'] ?? 0),
      // ... mismos campos
  ]);
  ```

- [ ] **1.2 — Modificar UserService::getCurrentProfile():** Cambiar firma a `public function getCurrentProfile(): Result` y cuerpo:

  ```php
  // Antes:
  $userId = Session::userId();
  if ($userId === null) { throw AuthenticationException::notAuthenticated(); }
  return $this->getProfile($userId);

  // Después:
  $userId = Session::userId();
  if ($userId === null) { return Result::fail('No autenticado', 'unauthenticated'); }
  return $this->getProfile($userId);
  ```

  > El Result de `getProfile()` se propaga directamente (ya es `Result`).

- [ ] **1.3 — Eliminar `use` innecesarios en UserService:** Si tras el cambio ya no se usan `NotFoundException` ni `AuthenticationException` en métodos públicos, eliminar los `use` correspondientes del archivo.

- [ ] **1.4 — Actualizar call sites en UserController:** Para cada uno de los 6 call sites de `getProfile()` y 1 de `getCurrentProfile()`:
  - **Patrón para métodos HTML** (la mayoría): reemplazar `$profile = $this->users->getProfile($userId);` por:

    ```php
    $result = $this->users->getProfile($userId);
    if (!$result->ok) {
        Flash::error('Usuario no encontrado.');
        return $this->response->redirect('/');
    }
    $profile = $result->data;
    ```

  - **Patrón para métodos JSON** (`uploadAvatar`, `deleteAvatar`): reemplazar por:

    ```php
    $result = $this->users->getProfile($userId);
    if (!$result->ok) {
        return $this->response->json(['success' => false, 'message' => 'Usuario no encontrado.'], 404);
    }
    $profile = $result->data;
    ```

  - Eliminar `use App\Exceptions\NotFoundException;` del controller si ya no se usa.

  > **Líneas afectadas:** 61, 110, 228, 252, 271, 297 (getProfile), ~50 (getCurrentProfile indirectamente vía profile()). Verificar una a una.

- [ ] **1.5 — Actualizar call site en ReservationService:** Buscar el try/catch que envuelve `getProfile()`. Simplificar a:

  ```php
  $result = $this->userService->getProfile($userId);
  if (!$result->ok) {
      return Result::fail($result->getMessage(), $result->code ?? 'not_found');
  }
  $profile = $result->data;
  ```

- [ ] **1.6 — Actualizar @throws en UserController:** Eliminar `@throws NotFoundException` de los docblocks de los métodos afectados en `UserController`.

- [ ] **1.7 — Ejecutar tests unitarios:** `make test-unit` → todos pasan. Si hay tests de `UserService` que esperan `NotFoundException`, actualizarlos para comprobar `$result->ok === false` y `$result->code === 'not_found'`.

---

### Fase 2 — ProductService: create / update → Result

**Contexto:**

- `create(array $data): int` — lanza `ValidationException::multipleRequired(...)` si faltan campos
- `update(int $id, array $data): bool` — ídem
- `ProductController` usa ambos directamente sin try/catch (delega al renderer via `@throws`)
- `MenuController` usa ambos con `catch (ValidationException $e) { throw ValidationException::withMessage(...); }` — catch/re-throw sintomático

- [ ] **2.1 — Modificar ProductService::create():** Cambiar firma a `public function create(array $data): Result` y cuerpo:

  ```php
  // Antes:
  if (empty($data['name']) || empty($data['slug']) || empty($data['category_id'])) {
      throw ValidationException::multipleRequired(['name', 'slug', 'category_id']);
  }
  // ... try { $productId = ...; return $productId; }

  // Después:
  if (empty($data['name']) || empty($data['slug']) || empty($data['category_id'])) {
      return Result::fail(
          'Los campos nombre, slug y categoría son obligatorios.',
          'validation_error'
      );
  }
  // ... try { $productId = ...; return Result::ok($productId); }
  // catch (PDOException $e) { throw DatabaseException::fromPDOException($e); }  ← MANTENER
  ```

- [ ] **2.2 — Modificar ProductService::update():** Igual que 2.1 pero retorna `Result::ok(true)` en éxito y `Result::fail(...)` en validación.

- [ ] **2.3 — Actualizar ProductController::store():** Reemplazar:

  ```php
  // Antes:
  $productId = $this->productService->create($_POST);
  // ... (flujo de éxito continúa directo)

  // Después:
  $result = $this->productService->create($_POST);
  if (!$result->ok) {
      return $this->response->json(['ok' => false, 'error' => $result->getMessage()], 422);
  }
  $productId = $result->data;
  ```

  Eliminar `use App\Exceptions\ValidationException;` si ya no se usa en el archivo.

- [ ] **2.4 — Actualizar ProductController::update():** Reemplazar:

  ```php
  // Antes:
  $this->productService->update($id, $_POST);

  // Después:
  $result = $this->productService->update($id, $_POST);
  if (!$result->ok) {
      return $this->response->json(['ok' => false, 'error' => $result->getMessage()], 422);
  }
  ```

- [ ] **2.5 — Actualizar MenuController::create():** Eliminar el try/catch alrededor de la llamada; reemplazar por check de Result:

  ```php
  // Antes:
  try {
      $productId = $this->productService->create($_POST);
      return $this->response->json([...]);
  } catch (ValidationException $e) {
      throw ValidationException::withMessage($e->getMessage(), 422);
  }

  // Después:
  $result = $this->productService->create($_POST);
  if (!$result->ok) {
      return $this->response->json(['ok' => false, 'error' => $result->getMessage()], 422);
  }
  $productId = $result->data;
  return $this->response->json([...]);
  ```

- [ ] **2.6 — Actualizar MenuController::update():** Mismo patrón que 2.5. Eliminar `use App\Exceptions\ValidationException;` si ya no se usa.

- [ ] **2.7 — Ejecutar tests unitarios:** `make test-unit` → todos pasan. Si hay tests de `ProductService` que esperan `ValidationException`, actualizarlos a `$result->ok === false`.

---

### Fase 3 — Eliminar código muerto

**Contexto:**

- `BaseService::assertNotBlank/assertMaxLength/assertRange/assertOneOf` — confirmado 0 usos en producción
- `BusinessRuleException::insufficientCapacity/invalidGuestCount/emailAlreadyExists/resourceLimitReached/insufficientStock` — confirmado 0 usos en producción (grep no encontró matches)
- `NotFoundException::forResource()` y `ValidationException::fromArray()` — SÍ tienen usos activos (controllers) — **NO eliminar**

- [ ] **3.1 — Eliminar assert* de BaseService:** En `app/Core/BaseService.php`, eliminar los cuatro métodos:
  - `assertNotBlank(string $value, string $field): void`
  - `assertMaxLength(string $value, int $max, string $field): void`
  - `assertRange(int|float $value, int|float $min, int|float $max, string $field): void`
  - `assertOneOf(mixed $value, array $allowed, string $field): void`
  - Eliminar también `use App\Exceptions\ValidationException;` ya que sólo se usaba en estos métodos.
  - Mantener `logInfo()` y `logError()` — son heredados por múltiples services.

- [ ] **3.2 — Eliminar factory methods muertos de BusinessRuleException:** En `app/Exceptions/BusinessRuleException.php`, eliminar:
  - `insufficientCapacity(int $requested, int $available): self` (~línea 154)
  - `invalidGuestCount(int $guests, int $min, ?int $max = null): self` (~línea 172)
  - `emailAlreadyExists(): self` (~línea 260)
  - `resourceLimitReached(string $resource, int $limit): self` (~línea 276)
  - `insufficientStock(int $requested, int $available): self` (~línea 242)

  > **Verificación previa:** Antes de eliminar cada uno, ejecutar grep en `app/` Y `tests/` para confirmar que no tienen usos. Si hay tests que los llaman, actualizar los tests primero o eliminar esos tests si son de métodos dead.

- [ ] **3.3 — Ejecutar tests y análisis estático:**
  - `make test-unit` → todos pasan
  - `make phpstan` → sin errores nuevos (la baseline se actualiza si hace falta)

---

### Fase 4 — Documentar el contrato en AGENTS.md

- [ ] **4.1 — Añadir sección en AGENTS.md:** Después de la sección "Result pattern", añadir:

  ```markdown
  **Error Handling Contract** — tres capas:

  | Capa | Regla |
  |------|-------|
  | Métodos `public` de Service | Siempre retornan `Result`. Nunca lanzan excepciones de dominio. |
  | Métodos `private` de Service | Pueden lanzar. El método `public` hace try/catch y convierte a `Result`. |
  | Controllers | Pueden lanzar (el `ErrorHandlerMiddleware` los captura y renderiza). |
  | Infraestructura | `DatabaseException`, `ConfigurationException` — siempre lanzadas. |

  `FormRequest::validate()` lanza `ValidationException` — correcto: es la frontera HTTP, el controller la captura inmediatamente.
  `NotFoundException::forResource()` y `ValidationException::fromArray()` — se usan en controllers (capa HTTP), no en Services.
  ```

---

### Fase 5 — Uniformizar serialización de errores API a RFC 9457

**Contexto:**

- `ResponseFactory::problem(Result $result, int $status): ResponseInterface` — ya existe, devuelve `Content-Type: application/problem+json` con body `{type, title, status, detail, code}` (RFC 9457).
- Los renderers actualmente usan `$this->response->json([...])` (envelope custom) — inconsistente con los helpers `notFound()`/`forbidden()` de `AbstractApiController` que ya usan `problem()`.
- `AbstractApiController::error()` devuelve `{ok: false, error: string, code: string}` — 9 call sites totales.
- **Nota sobre ValidationExceptionRenderer:** Tiene `getErrors()` (errores por campo). `ProblemDetails` no incluye ese campo. Solución: pasar como campo `errors` en el body directamente (el renderer construye el json manualmente con `Content-Type: application/problem+json`). Ver paso 5.1.

- [ ] **5.1 — Migrar ValidationExceptionRenderer:** Para requests API, construir manualmente la respuesta RFC 9457 extendida con `errors`:

  ```php
  // Antes:
  return $this->response->json([
      'error'  => $e->getMessage(),
      'errors' => $e->getErrors(),
  ], $e->getHttpCode());

  // Después:
  $body = \json_encode([
      'type'   => 'https://komorebi.cafe/errors/validation',
      'title'  => 'Validation Error',
      'status' => $e->getHttpCode(),
      'detail' => $e->getMessage(),
      'errors' => $e->getErrors(),
  ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

  $response = $this->response->html('', $e->getHttpCode())
      ->withHeader('Content-Type', 'application/problem+json');
  $response->getBody()->write($body);
  return $response;
  ```

  > Alternativamente, si `ResponseFactory` expone `createResponse(int $status)`, usarlo directamente. Comprobar qué métodos de `ResponseFactory` están disponibles.

- [ ] **5.2 — Migrar AuthenticationExceptionRenderer:** Para requests API:

  ```php
  // Antes:
  return $this->response->json(['error' => '...', 'code' => '...'], 401);
  // Después:
  return $this->response->problem(Result::fail($e->getMessage(), 'unauthenticated'), 401);
  ```

- [ ] **5.3 — Migrar AuthorizationExceptionRenderer:** Para requests API:

  ```php
  return $this->response->problem(Result::fail($e->getMessage(), 'forbidden'), 403);
  ```

- [ ] **5.4 — Migrar NotFoundExceptionRenderer:** Para requests API:

  ```php
  return $this->response->problem(Result::fail($e->getMessage(), 'not_found'), 404);
  ```

- [ ] **5.5 — Migrar BusinessRuleExceptionRenderer:** Para requests API:

  ```php
  $code = $e->getRuleCode() ?? 'business_rule_violation';
  return $this->response->problem(Result::fail($e->getMessage(), $code), 422);
  ```

  > Verificar que `BusinessRuleException` tiene `getRuleCode()` o equivalente. Si no, usar `'business_rule_violation'` como fallback.

- [ ] **5.6 — Migrar FallbackExceptionRenderer:** Para requests API:

  ```php
  return $this->response->problem(Result::fail('Error interno del servidor.', 'internal_error'), 500);
  ```

- [ ] **5.7 — Migrar AbstractApiController::error():** Los 9 call sites de `$this->error(...)` en controllers API deben migrarse al helper semánticamente más apropiado:
  - Errores de autenticación (401) → `$this->unauthorized($detail, $code)`
  - Errores de validación / input (400/422) → `$this->response->problem(Result::fail($msg, $code), $status)`
  - Errores de negocio (4xx) → `$this->response->problem(Result::fail($msg, $code), $status)`
  - Una vez que todos los call sites estén migrados, **eliminar el método `error()`** de `AbstractApiController`.

  Call sites a migrar:
  - `MenuController::addToCart()` line 60 → `$this->response->problem(Result::fail('product_id requerido', 'missing_product_id'), 400)`
  - `ReservationController` line 98 → `$this->response->problem(Result::fail($result->getMessage(), 'reservation_error'), 422)`
  - `LoyaltyController` line 35 → `$this->unauthorized('No autenticado')`
  - `LoyaltyController` lines 42, 48, 67, 73, 88, 94 → `$this->response->problem(Result::fail(...), 400/422)`

- [ ] **5.8 — Ejecutar tests y análisis estático:**
  - `make test-unit` → todos pasan
  - `make phpstan` → sin errores nuevos
  - `make cs-check` → PSR-12 OK

---

## Checklist de verificación final

- [ ] `make test-unit` → todos los tests pasan (ningún test espera las excepciones eliminadas)
- [ ] `make phpstan` → sin errores nuevos respecto a la baseline
- [ ] `make cs-check` → PSR-12 OK
- [ ] Grep de confirmación: `grep -r "NotFoundException::user" app/` → 0 resultados
- [ ] Grep de confirmación: `grep -r "ValidationException::multipleRequired" app/` → 0 resultados
- [ ] Grep de confirmación: `grep -r "assertNotBlank\|assertMaxLength\|assertRange\|assertOneOf" app/` → 0 resultados
- [ ] Grep de confirmación: `grep -r "->error(" app/Http/Controllers/Api/` → 0 resultados (tras Fase 5)

---

## Scope y decisiones

**Incluido:**

- `UserService::getProfile()` + `getCurrentProfile()` → `Result` + actualización de todos los call sites
- `ProductService::create()` + `update()` → `Result` + actualización de todos los call sites
- Eliminación de `BaseService::assert*` (0 usos confirmados)
- Eliminación de 5 factory methods muertos de `BusinessRuleException`
- Documetación del contrato en `AGENTS.md`
- 6 ExceptionRenderers → RFC 9457 via `response->problem()`
- `AbstractApiController::error()` → eliminado tras migración de 9 call sites

**NO cambia (out of scope):**

- `FormRequest::validate()` → sigue lanzando `ValidationException` (frontera HTTP correcta)
- `NotFoundException::forResource()` — sí tiene usos activos en controllers (HTML layer)
- `ValidationException::fromArray()` — sí tiene usos activos en controllers (HTML layer)
- `ReservationService` y otros services que ya retornan `Result` correctamente
- `ErrorHandlerMiddleware` + `ExceptionHandler::register()` (complementarios, correctos)
- `DatabaseException` en servicios (es infraestructura, no dominio)
- `DatabaseExceptionRenderer` y `RateLimitExceptionRenderer` (tienen lógica específica)
