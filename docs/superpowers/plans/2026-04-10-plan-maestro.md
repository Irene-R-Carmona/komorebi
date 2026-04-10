# Plan Maestro de Corrección e Implementación — Komorebi Café

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Corregir todos los errores en runtime, cerrar brechas de arquitectura y homogeneizar patrones de diseño en el proyecto, basado en la auditoría completa del 10 de abril de 2026.

**Architecture:** Cuatro fases con dependencias explícitas. Fase 0 son hotfixes de crashes en producción (sin dependencias). Fases 1 y 2 son paralelas e independientes. Fase 2 es el desbloqueador arquitectural (PSR-7). Fases 3 y 4 dependen de Fase 2 y consolidan los patrones del proyecto.

**Tech Stack:** PHP 8.4, PSR-7/PSR-15, Result pattern, Repository pattern, DI Container, PHPUnit 12, PHPStan nivel 5.

---

## Mapa de dependencias

```
FASE 0 (Hotfixes runtime)       ← SIN dependencias — HACER PRIMERO
  ├── F0-A Vistas inexistentes
  ├── F0-B FEATURE_BACKOFFICE bug
  ├── F0-C Controladores huérfanos
  ├── F0-D Métodos placeholder
  └── F0-E Migración INT→BIGINT

FASE 1 (Quick wins — paralelo)  ← Después de Fase 0
  ├── Plan 1: Security & Hardening   [2-3 días]
  ├── Plan 4: Reservation→Repos      [1-2 días]
  └── Plan 5: Tests servicios        [3-4 días]

FASE 2 (PSR-7 — desbloqueador)  ← Después de Fase 0
  └── UserController + AnimalController PSR-7 [2-3 días]

FASE 3 (Arquitectura — paralela) ← Después de Fase 2
  ├── Plan 3: DI Services             [3-4 días]
  ├── Plan 6: Controller tests        [5-7 días]
  ├── Plan 7: Keeper SRP split        [1-2 días]
  └── Plan 8: Logging request_id      [2-3 días]

FASE 4 (Consistencia patrones)   ← Entrelaza con Fase 3
  ├── P4-A: Result en servicios       [2-3 días]
  ├── P4-B: Interfaces en container   [1 día]
  └── P4-C: OpenAPI schemas           [~2h]
```

---

## FASE 0 — Hotfixes críticos (runtime crashes)

### F0-A: Corregir vistas referenciadas que no existen

Los siguientes controladores harán crash al navegar porque llaman a vistas con rutas incorrectas:

| Controlador | Línea | Vista incorrecta | Vista correcta / acción |
|---|---|---|---|
| `Admin\ReviewController@index` | L50 | `backoffice/admin/reviews/index` | `admin/reviews/pending` |
| `Admin\AnimalController@index` | L41 | `backoffice/admin/animals/index` | `backoffice/keeper/animals/index` |
| `Admin\AnimalController@create` | L54 | `backoffice/admin/animals/create` | `backoffice/keeper/animals/create` |
| `Admin\AnimalController@edit` | L75 | `backoffice/admin/animals/edit` | `backoffice/keeper/animals/edit` |
| `Admin\ProductController@index` | L62 | `management/products/index` | `admin/products/index` |
| `Admin\ProductController@create` | L76 | `management/products/create` | `admin/products/create` |
| `Admin\ProductController@edit` | L105 | `management/products/edit` | `admin/products/edit` |
| `Admin\CatalogController@index` | L42 | `backoffice/manager/productos` | ⚠️ Ver F0-C |
| `Shared\ReservationController@confirmation` | L193 | `shared/reservas/confirmation` | Crear vista nueva |
| `Shared\ReservationController@userReservations` | L211 | `shared/reservas/lista` | Crear vista nueva |

**Pasos:**

- [ ] Leer `app/Http/Controllers/Admin/ReviewController.php` L50 — cambiar `'backoffice/admin/reviews/index'` por `'admin/reviews/pending'`
- [ ] Leer `app/Http/Controllers/Admin/AnimalController.php` L41, L54, L75 — cambiar prefijo `backoffice/admin/animals/` por `backoffice/keeper/animals/`
- [ ] Leer `app/Http/Controllers/Admin/ProductController.php` L62, L76, L105 — cambiar prefijo `management/products/` por `admin/products/`
- [ ] Crear `resources/views/shared/reservas/confirmation.php` (layout `main`, muestra datos de la reserva confirmada + QR/código)
- [ ] Crear `resources/views/shared/reservas/lista.php` (layout `main`, tabla de reservas del usuario autenticado)
- [ ] Ejecutar `make test-unit` — debe pasar en verde
- [ ] Navegar manualmente a `/admin/reviews`, `/admin/animals`, `/mis-reservas` para verificar
- [ ] Commit: `fix: corregir rutas de vistas incorrectas en controladores Admin y Shared`

### F0-B: Fix FEATURE_BACKOFFICE (comparación de tipo incorrecto)

**Archivo:** `bootstrap/container.php`

`FEATURE_OPS` y `FEATURE_KEEPER` usan `Env::bool()` correctamente. `FEATURE_BACKOFFICE` usa `Env::get() === '1'` (string), lo que falla silenciosamente si el env var es `true` (bool).

- [ ] En `bootstrap/container.php`, cambiar:

  ```php
  // ANTES
  if (Env::get('FEATURE_BACKOFFICE', '1') === '1') {

  // DESPUÉS
  if (Env::bool('FEATURE_BACKOFFICE', true)) {
  ```

- [ ] Ejecutar `make test-unit`
- [ ] Commit: `fix: FEATURE_BACKOFFICE usar Env::bool() consistente con otros flags`

### F0-C: Resolver controladores huérfanos (sin rutas asociadas)

Tres controladores existen pero no tienen ruta en `app/routes.php`. Decisión por cada uno:

- [ ] **`Admin\ManagerController`** — tiene `getMonthlyRevenue()` que retorna `0.0` hardcoded y `getMonthlyStats()` que retorna `[]`. La funcionalidad la cubre `Manager\DashboardController`. **Acción: eliminar el archivo** `app/Http/Controllers/Admin/ManagerController.php`.
- [ ] **`Admin\CatalogController`** — su función de listar/togglear productos la cubre `Admin\MenuController`. **Acción: eliminar** `app/Http/Controllers/Admin/CatalogController.php`.
- [ ] **`Admin\ProductController`** — si `Admin\MenuController` ya cubre CRUD de productos, **eliminar**. Si se necesita como abstracción separada, añadir rutas y vistas. Verificar antes de decidir.
- [ ] Ejecutar `make phpstan` para confirmar que no hay referencias rotas tras la eliminación
- [ ] Commit: `refactor: eliminar controladores Admin huérfanos sin rutas`

### F0-D: Eliminar métodos placeholder de Admin\ManagerController

*(Aplicable solo si F0-C decide mantener el controlador)*

- [ ] Implementar `getMonthlyRevenue()` delegando a `Manager\DashboardService`
- [ ] Implementar `getMonthlyStats()` delegando a `Manager\DashboardService`
- [ ] Añadir ruta `GET /admin/manager/dashboard` en `app/routes.php`
- [ ] Commit: `feat: implementar Admin\ManagerController con datos reales`

### F0-E: Migración supervisor_assignments INT → BIGINT

Todas las tablas del proyecto usan `BIGINT UNSIGNED`. `supervisor_assignments` usa `INT UNSIGNED` — riesgo de overflow y errores en JOINs.

- [ ] Crear `migrations/019_fix_supervisor_assignments_bigint.sql`:

  ```sql
  ALTER TABLE supervisor_assignments
    MODIFY COLUMN id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    MODIFY COLUMN supervisor_id BIGINT UNSIGNED NOT NULL,
    MODIFY COLUMN reservation_id BIGINT UNSIGNED NOT NULL,
    MODIFY COLUMN cafe_id BIGINT UNSIGNED NOT NULL;
  ```

- [ ] Ejecutar `make db-migrate` en entorno de dev
- [ ] Ejecutar `make db-verify`
- [ ] Commit: `fix: supervisor_assignments columnas INT → BIGINT para consistencia de esquema`

---

## FASE 1 — Quick wins en paralelo

### Plan 1: Security & Hardening

**Archivos afectados:** `app/Core/View.php`, `app/Middleware/SecurityHeadersMiddleware.php`, `app/routes.php`, `app/Core/Database.php`, `app/Services/RateLimitingService.php`, ~30 archivos PHP sin `declare(strict_types=1)`

- [ ] Registrar `SecurityHeadersMiddleware` globalmente en `app/routes.php` como primer middleware de la cadena (está implementado pero nunca se aplica)
- [ ] Añadir headers `Content-Security-Policy`, `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin` en `SecurityHeadersMiddleware`
- [ ] En `app/Core/Database.php`, añadir whitelist de charsets válidos: `['utf8mb4', 'utf8', 'ascii']` — rechazar cualquier otro con excepción
- [ ] Migrar `RateLimitingService` de queries SQL a Redis/PSR-16: reemplazar `INSERT INTO rate_limits` con `$cache->increment($key, $ttl)`
- [ ] Buscar con `grep -rn "^<?php$" app/ --include="*.php"` los archivos sin strict_types y añadirlo
- [ ] Escribir/actualizar tests `RateLimitingServiceTest` con stub de cache Redis
- [ ] `make ci` — debe pasar completo
- [ ] Commit: `security: headers CSP, charset whitelist, RateLimiting→Redis, strict_types`

### Plan 4: ReservationService → delegación a repositorios

**Archivos afectados:** `app/Repositories/CafeRepository.php`, `app/Repositories/ProductRepository.php`, `app/Services/ReservationService.php`, tests

- [ ] Escribir test rojo: `CafeRepositoryTest::testFindAvailableForReservation` — verifica que el método existe y retorna array
- [ ] Añadir `findAvailableForReservation(int $cafeId, string $date): array` a `CafeRepository`
- [ ] Añadir `existsAndActive(int $cafeId): bool` a `CafeRepository`
- [ ] Ejecutar tests — deben pasar en verde
- [ ] Escribir test rojo: `ProductRepositoryTest::testFindAvailablePasses`
- [ ] Añadir `findAvailablePasses(int $cafeId): array` a `ProductRepository`
- [ ] Añadir `findItemsByIds(array $ids): array` a `ProductRepository`
- [ ] En `ReservationService`, reemplazar todos los `$this->db->prepare(...)` que consultan `cafes` y `products` con las llamadas a repositorio. El servicio ya recibe repos via constructor.
- [ ] `make test-unit` en verde
- [ ] Commit: `refactor: ReservationService delega queries de cafes/products a repos`

### Plan 5: Tests de servicios con cobertura insuficiente

**Archivos afectados:** `tests/Unit/Services/*.php` — ampliar o crear 12+ archivos

Para cada servicio, el flujo es siempre: leer servicio → docblock → test rojo → test pasa (código ya existe) → commit.

- [ ] `AccountDeletionServiceTest` — cubrir flujo GDPR: `deleteAccount()`, rollback si falla FK, `Result::ok` devuelto
- [ ] `SettingsServiceTest` — cubrir `get()`, `set()`, `getGroup()`, valores por defecto
- [ ] `UserManagementServiceTest` — cubrir CRUD usuarios, validación de rol, `Result` en mutaciones
- [ ] `HolidayServiceTest` — cubrir festivos configurables, rango de años, cache hit/miss
- [ ] `AnimalCareServiceTest` — ampliar hasta ~80%: logCare, incidents, health summaries
- [ ] `WaitlistServiceTest` — ampliar hasta ~70%: join, confirm, promote, position
- [ ] `AuthServiceTest` — ampliar hasta ~70%: logout, revokeSession, getAuthHistory (actualmente ~52%)
- [ ] `ReservationServiceTest` — ampliar hasta ~75%: edge cases de capacidad, cancelación, reembolso
- [ ] `ReviewServiceTest` — ampliar hasta ~70%: approve, reject, rating calculation
- [ ] `InvoicePDFServiceTest` — mock TCPDF, verificar que genera PDF con datos correctos
- [ ] `AllergenServiceTest` — cubrir CRUD completo
- [ ] `AdminServiceTest` — cubrir queries de dashboard, estadísticas
- [ ] `make test-unit` — todos en verde
- [ ] Commit: `test: ampliar cobertura servicios AccountDeletion, Settings, UserMgmt, Holiday, etc.`

---

## FASE 2 — PSR-7 Migration (desbloqueador)

**Prerequisito:** Fase 0 completa.
**Desbloquea:** Fase 3 (especialmente Plan 6: controller tests).

`UserController` y `AnimalController` aún usan `$_POST`, `$_FILES`, `header()` y `exit` directamente. Esto impide escribir tests unitarios para ellos y viola el contrato PSR-7/PSR-15 del proyecto.

**Archivos afectados:** `app/Http/Controllers/Shared/UserController.php`, `app/Http/Controllers/Keeper/AnimalController.php`, tests nuevos

**Patrón de referencia** (igual que el resto de controladores del proyecto):

```php
public function updateProfile(ServerRequestInterface $request): ResponseInterface
{
    $body = $request->getParsedBody();
    $name = $body['name'] ?? '';
    // ...
    return $this->response->redirect('/profile');
}
```

- [ ] Crear `tests/Unit/Http/Controllers/Shared/UserControllerTest.php` con docblock y primer test rojo: `profileReturnsNullForAuthenticatedUser`
- [ ] Migrar `UserController@profile` — firma `(ServerRequestInterface $request): ?ResponseInterface`, datos desde `$request->getAttribute('user')`
- [ ] Ejecutar test — debe pasar en verde
- [ ] Migrar `UserController@updateProfile` — leer `$request->getParsedBody()` en lugar de `$_POST`
- [ ] Migrar `UserController@updateAvatar` — leer `$request->getUploadedFiles()` en lugar de `$_FILES`
- [ ] Migrar los 5 métodos restantes de `UserController` al mismo patrón
- [ ] Verificar que no quedan `$_POST`, `$_FILES`, `header()`, `exit` en el archivo
- [ ] Crear `tests/Unit/Http/Controllers/Keeper/AnimalControllerTest.php`
- [ ] Migrar `Keeper\AnimalController` al mismo patrón PSR-7
- [ ] `make test-unit && make phpstan` — verde y sin errores
- [ ] Commit: `refactor: UserController y AnimalController migrados a PSR-7 completo`

---

## FASE 3 — Mejoras de arquitectura

**Prerequisito:** Fase 2 completa. Los planes de esta fase son independientes entre sí y pueden ejecutarse en paralelo.

### Plan 3: ContextService y NavigationService → inyectables (DI)

**Contexto:** `ContextService` ya tiene `ContextServiceInstance` como versión inyectable marcada como la "nueva" forma. `ContextService` con métodos estáticos está marcado `@deprecated`. El plan completa la transición.

**Archivos afectados:** `bootstrap/container.php`, todos los controllers que llaman `ContextService::`, `app/Services/NavigationService.php`, `app/Services/ContextService.php`

- [ ] Verificar que `ContextServiceInterface → ContextServiceInstance` está registrado en `bootstrap/container.php`. Si no, añadirlo.
- [ ] Buscar con `grep -rn "ContextService::"` todos los controladores que usan la versión estática
- [ ] Para cada controlador encontrado, añadir `private ContextServiceInterface $context` al constructor e inyectar via container
- [ ] Reemplazar las llamadas estáticas `ContextService::getCafeId()` por `$this->context->getCafeId()`
- [ ] Repetir el proceso para `NavigationService` — convertir métodos estáticos frecuentes a métodos de instancia, registrar en container
- [ ] Mantener los wrappers `@deprecated` en `ContextService.php` una iteración más para backward-compat
- [ ] Actualizar tests afectados (los que mockean `ContextService`)
- [ ] `make phpstan && make test-unit` — verde
- [ ] Commit: `refactor: ContextService y NavigationService migrados a DI inyectable`

### Plan 6: Infraestructura de tests para controladores

**Contexto:** 58 controladores sin tests. Primero construir la infraestructura, luego usar en fases.

**Archivos afectados:** `tests/Support/ControllerTestCase.php` (nuevo), `tests/Unit/Http/Controllers/**/*Test.php`

- [ ] Crear `tests/Support/ControllerTestCase.php` extendiendo `TestCase` con helpers:
  - `makeGetRequest(string $uri, array $attrs = []): ServerRequestInterface`
  - `makePostRequest(string $uri, array $body, array $attrs = []): ServerRequestInterface`
  - `withAuthUser(int $userId = 1, string $role = 'user'): static` — inyecta user en atributos del request
  - `assertResponseIsRedirect(ResponseInterface $response, string $location): void`
  - `assertResponseIsOk(?ResponseInterface $response): void`
- [ ] Fase 1 — 5 controladores críticos:
  - [ ] `tests/Unit/Http/Controllers/Auth/AuthControllerTest.php` — login válido, login inválido, logout
  - [ ] `tests/Unit/Http/Controllers/Shared/UserControllerTest.php` — completar (post-Fase 2)
  - [ ] `tests/Unit/Http/Controllers/Public/HomeControllerTest.php` — index retorna null (view echo)
  - [ ] `tests/Unit/Http/Controllers/Shared/ReservationControllerTest.php` — index, cancel
  - [ ] `tests/Unit/Http/Controllers/Admin/UserControllerTest.php` — index, store, delete
- [ ] `make test-unit` — verde
- [ ] Commit: `test: ControllerTestCase infrastructure y primeros 5 controller tests`
- [ ] Fase 2 — resto por grupos: Admin/, Api/, Manager/, Keeper/, Reception/, Kitchen/

### Plan 7: Keeper/AnimalController SRP split

**Contexto:** Los controladores ya existen (`AnimalDashboardController`, `AnimalCareController`, `HealthCheckController`). El `AnimalController` legacy puede eliminarse si las rutas ya apuntan a los nuevos.

- [ ] Verificar en `app/routes.php` qué rutas `/keeper/*` apuntan a `AnimalController` vs los nuevos
- [ ] Para cada ruta que aún apunte a `AnimalController`, redirigirla al controlador específico correcto
- [ ] Verificar que `AnimalDashboardController@index` y `@show` están completos
- [ ] Verificar que `AnimalCareController@recordFeeding` y `@recordHealth` están completos
- [ ] Si `AnimalController` no tiene rutas activas, eliminarlo o marcarlo `@deprecated`
- [ ] Crear/completar tests en `tests/Unit/Http/Controllers/Keeper/`
- [ ] `make phpstan && make test-unit` — verde
- [ ] Commit: `refactor: Keeper SRP split completo, AnimalController legacy eliminado`

### Plan 8: Logging con request_id y canales

**Archivos afectados:** `app/Core/LogContext.php` (nuevo), `app/Middleware/RequestLogMiddleware.php` (nuevo), `app/Core/Logger.php`, `app/routes.php`

*(Ver también `docs/superpowers/plans/2026-04-09-observability.md` — puede haber trabajo previo aquí)*

- [ ] Crear `app/Core/LogContext.php` — registry estático `set(string $key, mixed $value)`, `all(): array`, `reset(): void`
- [ ] Crear `app/Http/Middleware/RequestLogMiddleware.php` PSR-15:
  - Genera `request_id = uniqid('req_', true)` al inicio del request
  - Almacena en `LogContext::set('request_id', ...)`
  - Al final (response), llama `LogContext::reset()`
- [ ] Registrar `RequestLogMiddleware` como el primer middleware global en `app/routes.php`
- [ ] Actualizar `app/Core/Logger.php` para que cada línea incluya `LogContext::all()` como contexto extra
- [ ] Fix `ExceptionLogger` — reemplazar método privado `writeToLog()` con `Logger::error()`
- [ ] Escribir tests: `LogContextTest`, `RequestLogMiddlewareTest`
- [ ] `make test-unit` — verde
- [ ] Commit: `feat: LogContext + RequestLogMiddleware para trazabilidad por request_id`

---

## FASE 4 — Consistencia de patrones

**Puede ejecutarse en paralelo con Fase 3.**

### P4-A: Homogeneizar patrón Result en servicios

Los servicios de mutación deben retornar `Result`, no lanzar excepciones ni retornar `bool`.

**Patrón de referencia:**

```php
// ❌ Antes — lanzar excepción
throw new BusinessRuleException('Reserva ya confirmada');

// ✅ Después — retornar Result::fail
return Result::fail('Reserva ya confirmada', 'already_confirmed');

// En el controlador:
if (!$result->ok) {
    Flash::error($result->getMessage());
    return $this->response->redirect('/back');
}
```

Prioridad por impacto:

- [ ] `ReservationService` — reemplazar `throw new BusinessRuleException(...)` (L445, L474, L484...) por `return Result::fail(...)`
- [ ] `ReceptionService` — `checkIn()` y `checkOut()` lanzan excepciones → `Result::fail()`
- [ ] `ContextServiceInstance` — `RuntimeException` en L142 → `Result::fail()`
- [ ] `NewsletterService::subscribe()` — retorna `bool` → retornar `Result`
- [ ] Actualizar controladores que consumen estos servicios: cambiar `try/catch` por `if (!$result->ok)`
- [ ] Actualizar tests de los servicios modificados
- [ ] `make test-unit` — verde
- [ ] Commit: `refactor: Result pattern en ReservationService, ReceptionService, ContextServiceInstance, NewsletterService`

### P4-B: Vincular interfaces de servicios al container

Las interfaces existen en `app/Services/Contracts/` pero no están registradas, impidiendo injetar mocks en los tests.

- [ ] En `bootstrap/container.php`, registrar las interfaces no vinculadas:

  ```php
  Container::singleton(EmailServiceInterface::class,
      fn() => Container::make(EmailService::class));
  Container::singleton(RateLimitingServiceInterface::class,
      fn() => Container::make(RateLimitingService::class));
  Container::singleton(StaffShiftServiceInterface::class,
      fn() => Container::make(StaffShiftService::class));
  Container::singleton(InvoicePDFServiceInterface::class,
      fn() => Container::make(InvoicePDFService::class));
  ```

- [ ] Actualizar controladores que hacen `new EmailService(...)` directamente para recibirlo via constructor con la interfaz
- [ ] Actualizar tests afectados para que usen `createStub(EmailServiceInterface::class)`
- [ ] `make phpstan && make test-unit` — verde
- [ ] Commit: `refactor: vincular interfaces de servicio al DI container`

### P4-C: Completar schemas de OpenAPI

**Archivo:** `docs/openapi.yaml`

- [ ] Completar schema `WaitlistStatus`: documentar `estimated_wait_minutes` con descripción del algoritmo (posición × tiempo promedio por turno)
- [ ] Completar schema `DashboardMetrics`: añadir todos los campos que realmente retorna `Api\V1\ManagerController@stats`
- [ ] Cambiar tipo `TimeSlot.occupancy` de `string` a `integer` (porcentaje 0-100) con `minimum: 0`, `maximum: 100`
- [ ] Validar el YAML con una herramienta de spec (Swagger Validator, `make audit`)
- [ ] Commit: `docs: completar schemas OpenAPI WaitlistStatus, DashboardMetrics, TimeSlot`

---

## Verificación al finalizar cada fase

```bash
make test-unit          # Tests unitarios en verde
make test-integration   # Tests de integración en verde
make phpstan            # Nivel 5, sin errores fuera de baseline
make cs-check           # PSR-12 limpio
make ci                 # Pipeline completo (todo lo anterior)
```

---

## Decisiones pendientes antes de implementar Fase 0

Estas decisiones deben tomarse antes de proceder con F0-A y F0-C:

1. **Controladores huérfanos (F0-C):** ¿`Admin\CatalogController`, `Admin\ManagerController` y `Admin\ProductController` se eliminan (recomendado, duplican funcionalidad) o se mantienen con nuevas rutas y vistas?
2. **Vistas de reservas (F0-A):** ¿Las vistas `shared/reservas/confirmation.php` y `shared/reservas/lista.php` se crean con diseño simple (tabla + datos), o hay un wireframe/mockup de referencia?
3. **Ritmo de Plan 5:** ¿Los 12 servicios de cobertura baja se abordan en un único PR o por módulo?
