# Plan: Remediación Auditoría Arquitectónica — Komorebi Café

**Creado:** 28 de abril de 2026
**Estado:** 🔵 Pendiente inicio
**Rama sugerida:** `feature/audit-remediation`

---

## TL;DR

Plan en 8 fases para cerrar todos los hallazgos de la auditoría del 28/04/2026.
Orden: quick-wins (F0) → Result pattern cascade (F1) → DTOs faltantes (F2) →
repos sin AbstractRepository (F3) → controladores híbridos (F4) → rutas AnimalCare (F5) →
CRUD gaps Keeper (F6) → índices BD (F7) → tests (F8).

Fases 0, 2, 3, 5, 7 son independientes (paralelas entre sí).
Fase 1 bloquea Fase 4 parcialmente.

---

## Decisiones acordadas

- Admin Services: migración completa Result + actualizar todos los callers
- AnimalCareController: conectar rutas en ops.php bajo FEATURE_KEEPER
- HealthCheck/Incident: solo PUT (edición), sin DELETE — audit records inmutables
- Repositorios: migrar los 18 a AbstractRepository

---

## Métricas objetivo (antes → después)

| Métrica | Antes | Objetivo |
|---|---|---|
| Repositorios sin AbstractRepository | 18 | 0 |
| Servicios sin Result pattern | 3 | 0 |
| Servicios sin interfaz | 1 | 0 |
| DTOs faltantes | 3 | 1 *(Statistics = intencional)* |
| Controllers híbridos SSR+AJAX | 2 | 0 |
| FEATURE_KEEPER sin wrapper propio | sí | no |
| OpsServiceProvider vacío | sí | no |
| Índices BD faltantes | 5 | 0 |
| AnimalCareController sin rutas | sí | no |
| Entidades Keeper sin PUT edit | 2 | 0 |
| Integration tests bloqueados | 14 | 0 |

---

## Fase 0 — Quick Wins *(paralelas, sin dependencias)*

### T0.1 — Aislar FEATURE_KEEPER en ops.php

**Archivo:** `app/routes/ops.php`

Las rutas keeper están actualmente dentro del bloque `if (FEATURE_OPS)`.
Necesitan su propio `if (Env::bool('FEATURE_KEEPER', true))` independiente para que
`FEATURE_OPS=0` + `FEATURE_KEEPER=1` funcione correctamente.

- [ ] Extraer el bloque keeper fuera del if FEATURE_OPS
- [ ] Añadir wrapper `if (Env::bool('FEATURE_KEEPER', true))` propio
- [ ] `php -l` → verificar sintaxis OK
- [ ] `phpunit --filter FeatureFlagsTest` → 9/9

### T0.2 — Rellenar OpsServiceProvider

**Archivos:** `app/Providers/OpsServiceProvider.php`, `bootstrap/container.php`

Mover los registros de `ReceptionService`, `KitchenService` y sus repos desde
`bootstrap/container.php` al provider dedicado que actualmente está completamente vacío.

- [ ] Leer `bootstrap/container.php` para identificar los bindings de Reception y Kitchen
- [ ] Mover bindings a `OpsServiceProvider::register()`
- [ ] Verificar que el provider ya está registrado en `bootstrap/container.php`
- [ ] PHPStan 0 errores

### T0.3 — Crear NavigationServiceInterface

**Archivos a crear/modificar:**

- `app/Services/Contracts/NavigationServiceInterface.php` (crear)
- `app/Services/NavigationService.php` (añadir `implements NavigationServiceInterface`, `#[Override]`)
- `bootstrap/container.php` (cambiar singleton de clase concreta a interfaz)

- [ ] Crear interfaz con todos los métodos públicos de NavigationService
- [ ] `implements NavigationServiceInterface` + `use Override;` + `#[Override]` en cada método
- [ ] Actualizar singleton en container.php
- [ ] PHPStan 0 errores

### T0.4 — Eliminar `new Role()` en Admin\UserController

**Archivo:** `app/Http/Controllers/Admin/UserController.php`

Constructor hace `$this->roleModel = new Role()` sin inyección — violación del patrón DI del proyecto.
Reemplazar con `RoleRepositoryInterface` inyectado como nullable param con fallback `Container::make`.

- [ ] Añadir `private readonly RoleRepositoryInterface $roleModel` como param nullable
- [ ] Eliminar `$this->roleModel = new Role()` del cuerpo del constructor
- [ ] PHPStan 0 errores

### T0.5 — Estandarizar Env en rutas *(estilo)*

**Archivos:** `app/routes/admin.php`, `app/routes/ops.php`

Cambiar `Env::get('FEATURE_*', '1') === '1'` a `Env::bool('FEATURE_*', true)` para
consistencia con `bootstrap/container.php` que ya usa `Env::bool()`.

- [ ] Reemplazar en admin.php
- [ ] Reemplazar en ops.php (aprovechar T0.1)
- [ ] `php -l` en ambos archivos

---

## Fase 1 — Admin Services: Result pattern cascade *(bloquea F4)*

Los servicios `AdminActivityService`, `AdminReportService`, `AdminStatisticsService` devuelven
`array`/`void` en lugar de `Result::ok()` / `Result::fail()`.
Sus interfaces ya existen y están correctamente definidas.

### T1.1 — Migrar AdminActivityService

**Archivos:**

- `app/Services/AdminActivityService.php`
- `app/Services/Contracts/AdminActivityServiceInterface.php`

Métodos a migrar (7): `getRecentReservations`, `getUsersWithRoles`, `getProductsWithCategories`,
`getReservationsWithDetails`, `getRecentActivity`, `getSystemStatus`, `getReservationsChartData`

- [ ] Wrappear cada método en `Result::ok($data)` / `Result::fail($e->getMessage())`
- [ ] Actualizar return types en interfaz: `Result`
- [ ] `use App\Core\Result;` al inicio del archivo
- [ ] PHPStan 0 errores

### T1.2 — Migrar AdminReportService

**Archivo:** `app/Services/AdminReportService.php`

Método: `getReportsSummary(string $dateFrom, string $dateTo): array` → `Result`

- [ ] Wrappear en Result::ok / Result::fail
- [ ] Actualizar interfaz si existe
- [ ] PHPStan 0 errores

### T1.3 — Migrar AdminStatisticsService

**Archivo:** `app/Services/AdminStatisticsService.php`

Métodos (7): `getSystemStatistics`, `getMonthlyStats`, `getCafePerformanceStats`,
`getReservationTrendStats`, `getReservationsByCafeType`, `getUserDistributionByRole`, `getTopCafes`

- [ ] Wrappear cada método en Result::ok/fail; el catch de PDOException ya presente → Result::fail
- [ ] Actualizar interfaz
- [ ] PHPStan 0 errores

### T1.4 — Actualizar callers

**Archivos:**

- `app/Http/Controllers/Admin/DashboardController.php`
- `app/Http/Controllers/Admin/ReportController.php`
- `app/Http/Controllers/Admin/ReservationController.php`
- `app/Http/Controllers/Manager/DashboardController.php`

En cada caller: añadir `if (!$result->ok) { Flash::error($result->getMessage()); return ...; }`,
acceder a datos via `$result->data`.

- [ ] DashboardController actualizado
- [ ] ReportController actualizado
- [ ] Admin\ReservationController actualizado
- [ ] Manager\DashboardController actualizado
- [ ] PHPStan 0 errores
- [ ] PHPUnit 0 failures

---

## Fase 2 — DTOs faltantes *(paralela con F1 y F3)*

### T2.1 — Crear MenuDTO

**Archivos:**

- Crear `app/Domain/DTO/MenuDTO.php`
- Actualizar `app/Repositories/MenuRepository.php`

`final readonly class MenuDTO` con campos: id, name, slug, description, price, category_id,
is_active, stock_quantity, allergens, image_url, created_at.
Implementa `fromArray(array $data): self` + `toViewArray(): array`.

- [ ] Crear MenuDTO.php
- [ ] Actualizar `getAllProducts()` en MenuRepository para devolver `MenuDTO[]`
- [ ] Actualizar `getProductsByCategory()` igual
- [ ] PHPStan 0 errores

### T2.2 — Crear NewsletterSubscriptionDTO

**Archivos:**

- Crear `app/Domain/DTO/NewsletterSubscriptionDTO.php`
- Actualizar `app/Repositories/NewsletterSubscriptionRepository.php`

Campos: id, email, token, confirmed_at, unsubscribed_at, created_at.

- [ ] Crear NewsletterSubscriptionDTO.php
- [ ] Actualizar métodos de consulta en el repo
- [ ] PHPStan 0 errores

> **Nota:** `StatisticsDTO` se marca como intencional — datos de solo lectura analítica,
> no pasan por `View::render()` por lo que no requieren DTO estricto.

---

## Fase 3 — Migrar 18 repositorios a AbstractRepository *(paralela)*

**Patrón de migración para cada repo:**

1. Añadir `extends AbstractRepository`
2. Implementar `getTable(): string` y `getSelectFields(): array` con `#[Override]`
3. Constructor inyecta PDO via `parent::__construct($pdo)`
4. Mantener todos los métodos custom existentes
5. Eliminar métodos CRUD re-implementados que ya provee AbstractRepository

Para repos **read-only** (AuditLog, Statistics): extender AbstractRepository
pero no exponer mutaciones heredadas (hacerlas private/protected finales).

| Tarea | Repositorio | Tipo |
|---|---|---|
| T3.01 | `app/Repositories/AnimalIncidentRepository.php` | R/W |
| T3.02 | `app/Repositories/AuditLogRepository.php` | Read-only |
| T3.03 | `app/Repositories/AuthTokenRepository.php` | R/W |
| T3.04 | `app/Repositories/FavoriteRepository.php` | R/W |
| T3.05 | `app/Repositories/HealthCheckRepository.php` | R/W |
| T3.06 | `app/Repositories/LoyaltyRepository.php` | R/W |
| T3.07 | `app/Repositories/MenuCategoryRepository.php` | R/W |
| T3.08 | `app/Repositories/MenuRepository.php` | R/W *(depende de T2.1)* |
| T3.09 | `app/Repositories/NewsletterSubscriptionRepository.php` | R/W *(depende de T2.2)* |
| T3.10 | `app/Repositories/ReservationItemRepository.php` | R/W |
| T3.11 | `app/Repositories/ReviewRepository.php` | R/W |
| T3.12 | `app/Repositories/RoleRepository.php` | R/W |
| T3.13 | `app/Repositories/StaffShiftRepository.php` | R/W |
| T3.14 | `app/Repositories/StatisticsRepository.php` | Read-only |
| T3.15 | `app/Repositories/SupervisorAssignmentRepository.php` | R/W |
| T3.16 | `app/Repositories/TimeSlotRepository.php` | R/W |
| T3.17 | `app/Repositories/TrackerRepository.php` | R/W |
| T3.18 | `app/Repositories/WaitlistRepository.php` | R/W |

- [ ] T3.01 AnimalIncidentRepository
- [ ] T3.02 AuditLogRepository (read-only)
- [ ] T3.03 AuthTokenRepository
- [ ] T3.04 FavoriteRepository
- [ ] T3.05 HealthCheckRepository
- [ ] T3.06 LoyaltyRepository
- [ ] T3.07 MenuCategoryRepository
- [ ] T3.08 MenuRepository
- [ ] T3.09 NewsletterSubscriptionRepository
- [ ] T3.10 ReservationItemRepository
- [ ] T3.11 ReviewRepository
- [ ] T3.12 RoleRepository
- [ ] T3.13 StaffShiftRepository
- [ ] T3.14 StatisticsRepository (read-only)
- [ ] T3.15 SupervisorAssignmentRepository
- [ ] T3.16 TimeSlotRepository
- [ ] T3.17 TrackerRepository
- [ ] T3.18 WaitlistRepository
- [ ] PHPStan 0 errores
- [ ] PHPUnit 0 failures

---

## Fase 4 — Limpiar controladores híbridos SSR+AJAX *(depende de F1)*

`Admin\AuditLogController` y `Admin\AuthLogController` detectan AJAX via
`$_SERVER['HTTP_X_REQUESTED_WITH']` y devuelven JSON o SSR desde el mismo `index()`.
Esto es redundante: `Api\V1\Admin\LogApiController` ya sirve esos mismos datos.

### T4.1 — Limpiar Admin\AuditLogController

**Archivo:** `app/Http/Controllers/Admin/AuditLogController.php`

- [ ] Eliminar detección `$_SERVER['HTTP_X_REQUESTED_WITH']`
- [ ] Eliminar método privado que generaba el JSON
- [ ] `index()` solo renderiza vista SSR con stats iniciales mínimos
- [ ] Verificar que el JS de la vista apunta a `/api/v1/admin/logs/audit` para datos tabulares

### T4.2 — Limpiar Admin\AuthLogController

**Archivo:** `app/Http/Controllers/Admin/AuthLogController.php`

- [ ] Mismo tratamiento que T4.1
- [ ] `suspiciousCount()` puede quedarse (ya es API-style puro)
- [ ] PHPStan 0 errores

---

## Fase 5 — Conectar rutas AnimalCareController *(paralela)*

`Keeper\AnimalCareController` tiene métodos activos (`recordFeeding`, `recordHealth`,
`logCare`, `uploadPhoto`) sin rutas registradas en `ops.php`.

### T5.1 — Verificar solapamiento con KeeperApiController

- [ ] Leer `app/Http/Controllers/Api/V1/KeeperApiController.php`
- [ ] Comparar métodos: si hay solapamiento, consolidar en la API; si son formularios HTML → AnimalCareController

### T5.2 — Añadir rutas en ops.php bajo FEATURE_KEEPER

**Archivo:** `app/routes/ops.php`

```php
$r->post('/keeper/animals/{id}/feeding',  'Keeper\AnimalCareController@recordFeeding',  [$mw->csrf()]);
$r->post('/keeper/animals/{id}/health',   'Keeper\AnimalCareController@recordHealth',   [$mw->csrf()]);
$r->post('/keeper/animals/{id}/photo',    'Keeper\AnimalCareController@uploadPhoto',    [$mw->csrf()]);
$r->post('/keeper/animals/{id}/care-log', 'Keeper\AnimalCareController@logCare',        [$mw->csrf()]);
```

- [ ] Rutas añadidas
- [ ] PHPUnit `--filter FeatureFlagsTest` → 9/9

---

## Fase 6 — CRUD gaps Keeper: edición *(depende de F5)*

*Decisión: solo PUT (corrección de errores), sin DELETE — audit records inmutables.*

### T6.1 — HealthCheck edit/update

**Archivos:**

- `app/Http/Controllers/Keeper/HealthCheckController.php` — añadir `edit()` y `update()`
- `app/Services/HealthCheckService.php` — añadir `update(int $id, array $data): Result`
- `app/Services/Contracts/HealthCheckServiceInterface.php` — añadir método
- `app/routes/ops.php` — añadir rutas:

```php
$r->get( '/keeper/health-checks/{checkId}/edit',  'Keeper\HealthCheckController@edit');
$r->post('/keeper/health-checks/{checkId}',        'Keeper\HealthCheckController@update', [$mw->csrf()]);
```

- [ ] Servicio actualizado con `update()`
- [ ] Controller con `edit()` y `update()`
- [ ] Rutas añadidas
- [ ] PHPStan 0 errores

### T6.2 — Incident edit/update

**Archivos:**

- `app/Http/Controllers/Keeper/AnimalIncidentController.php` — añadir `edit()` y `update()`
- `app/routes/ops.php`:

```php
$r->get( '/keeper/incidents/{id}/edit', 'Keeper\AnimalIncidentController@edit');
$r->post('/keeper/incidents/{id}',      'Keeper\AnimalIncidentController@update', [$mw->csrf()]);
```

- [ ] Controller actualizado
- [ ] Rutas añadidas
- [ ] PHPStan 0 errores

---

## Fase 7 — Migración BD: índices de rendimiento *(totalmente paralela)*

### T7.1 — Crear migrations/025_performance_indexes.sql

**Archivo a crear:** `migrations/025_performance_indexes.sql`

```sql
-- Productos activos (listado de menú)
ALTER TABLE products ADD INDEX idx_products_active (is_active, deleted_at);

-- Historial de reservas por usuario
ALTER TABLE reservations ADD INDEX idx_reservations_user_status (user_id, status);

-- Timeline de items en reserva
ALTER TABLE order_items ADD INDEX idx_order_items_timeline (reservation_id, started_at DESC);

-- Historial de revisiones de salud animal
ALTER TABLE animal_health_checks ADD INDEX idx_health_check_date (check_date DESC, animal_id);

-- Último sello de fidelización
ALTER TABLE loyalty_cards ADD INDEX idx_loyalty_last_stamp (last_stamp_at DESC);
```

- [ ] Archivo creado
- [ ] `make db-migrate` → 0 errores
- [ ] `EXPLAIN` confirma que los índices se usan en las queries principales afectadas

---

## Fase 8 — Tests *(última fase)*

### T8.1 — FEATURE_KEEPER=0 test

**Archivo:** `tests/Unit/Core/FeatureFlagsTest.php`

Añadir `testKeeperRoutesAbsentWhenFeatureDisabled()`:
verifica que ninguna ruta `/keeper/*` se registra cuando `FEATURE_KEEPER=0`.

- [ ] Test añadido y verde

### T8.2 — Rutas nuevas en RouteRegistrationTest

**Archivo:** `tests/Unit/Http/RouteRegistrationTest.php`

Añadir assertions para los 4 POST de keeper (feeding/health/photo/care-log) y los
2 pares GET+POST de HealthCheck edit y Incident edit.

- [ ] Tests añadidos y verdes

### T8.3 — Tests Admin Services con Result

**Archivos:**

- `tests/Unit/Services/AdminActivityServiceTest.php` (crear o actualizar)
- `tests/Unit/Services/AdminReportServiceTest.php`
- `tests/Unit/Services/AdminStatisticsServiceTest.php`

Verificar que cada método devuelve `Result` y que `$result->ok` es `true` con datos válidos.

- [ ] Tests creados y verdes

### T8.4 — Tests DTOs nuevos

**Archivos:**

- `tests/Unit/Domain/DTO/MenuDTOTest.php` (crear)
- `tests/Unit/Domain/DTO/NewsletterSubscriptionDTOTest.php` (crear)

- [ ] MenuDTOTest verde
- [ ] NewsletterSubscriptionDTOTest verde

### T8.5 — CI: DB container

**Archivo:** `docker-compose.test.yml`

Verificar/añadir servicio MySQL ephemeral para desbloquear los 14 errores de integración
`No se pudo conectar con la base de datos` pre-existentes.

- [ ] docker-compose.test.yml tiene servicio MySQL ephemeral
- [ ] `make test-integration` → 0 errors

---

## Verificación por fase

| Fase | Comando | Criterio de éxito |
|---|---|---|
| F0 | `php -l app/routes/ops.php` + `phpunit --filter FeatureFlagsTest` | Sintaxis OK + 9/9 |
| F1 | `make phpstan` + `phpunit --filter AdminActivityServiceTest` | 0 errores PHPStan + verde |
| F2 | `phpunit --filter MenuDTOTest\|NewsletterSubscriptionDTOTest` | verde |
| F3 | `make phpstan` + `make test-unit` | 0 errores + 0 failures |
| F4 | `curl http://localhost/admin/logs/audit` verifica HTML + consola JS sin errores | 200 HTML + API OK |
| F5/F6 | `phpunit --filter FeatureFlagsTest` + `curl -X POST /keeper routes` | 200/302 |
| F7 | `make db-migrate` + `EXPLAIN SELECT` en queries afectadas | 0 errores + no full scan |
| F8 | `make test-unit` | Failures: 0, Errors: 0 |

---

## Verificación final global

```bash
make phpstan       # 0 errores
make test-unit     # 0 failures, 0 errors
make test-coverage # cobertura no regresa
make cs-check      # 0 violaciones PSR-12
```
