# Plan: Optimizar tests para cobertura 85% post-FrankenPHP (22-04-2026)

## TL;DR

El plan de FrankenPHP está 100% implementado (Fases 0-6.2). Introduce 4 superficies nuevas
que requieren tests. El gap principal a 85% está en: 11 modelos sin test, 19 repositorios
sin test, y 8 controllers públicos/usuario faltantes. Prioridad: FrankenPHP gaps → Modelos
→ Repositorios → Controladores → Integración → CI.

---

## Estado actual (inventario confirmado por file listing)

### Tests existentes

| Área                         | Archivos | Estado                                      |
|------------------------------|----------|---------------------------------------------|
| Services                     | 51       | Todos existen; profundidad variable          |
| Models                       | 11/22    | 11 faltan (ver lista)                       |
| Repositories                 | 29/29    | ✅ Fase C COMPLETADA (13/13 creados)        |
| Controllers/Admin            | 13       | Completo                                    |
| Controllers/Manager          | 6        | Completo                                    |
| Controllers/Auth             | 3        | Completo                                    |
| Controllers/Api (no V1)      | 7        | Completo (CartController incluido)          |
| Controllers/Api/V1           | 4        | Parcial (falta TimeSlot, Waitlist V1)       |
| Controllers/Keeper           | 4        | Completo                                    |
| Controllers/Kitchen          | 1        | Completo                                    |
| Controllers/Reception        | 1        | Completo                                    |
| Controllers/Shared           | 3        | Completo                                    |
| Controllers/Supervisor       | 1        | Completo                                    |
| Controllers/User             | 2/3      | Falta WaitlistControllerTest               |
| Controllers/Public           | 1/8      | Solo HomeControllerTest — 7 faltan          |
| Core                         | 28+      | Sustancial; faltan métodos TagAware         |
| Jobs                         | 2/5      | ProcessImage, RewardUnlocked, WaitlistPromotionJob faltan |
| Domain                       | 2        | ReservationStateMachine, AllergenCodeGenerator |
| Http/Middleware               | 4        | Completo                                    |
| Http/Requests                | 1        | FormRequestTest                             |

### Nuevas superficies de FrankenPHP que requieren tests (PRIORIDAD INMEDIATA)

| Código nuevo                                     | Test necesario                                  |
|--------------------------------------------------|-------------------------------------------------|
| `Cache::invalidateTags()`                        | Expandir `tests/Unit/Core/CacheTest.php`        |
| `Cache::setWithTags()`                           | Expandir `tests/Unit/Core/CacheTest.php`        |
| `Cache::computeIfAbsent()`                       | Expandir `tests/Unit/Core/CacheTest.php`        |
| `Container::enableCompilation()`                 | Expandir `tests/Unit/Core/ContainerTest.php`    |
| `LoyaltyRepository::getLeaderboard()`            | ✅ HECHO — `tests/Unit/Repositories/LoyaltyRepositoryTest.php` |
| `MercurePublisherService::publish()`             | Crear `tests/Unit/Services/MercurePublisherServiceTest.php` |

---

## Fases del plan

### Fase A — FrankenPHP gaps (INMEDIATO, ~1 día)

**A.1 CacheTest.php — 3 métodos TagAware nuevos**

- `testInvalidateTagsReturnsTrueOnSuccess()` — setWithTags primero, luego invalidateTags, verificar que get() retorna null
- `testInvalidateTagsReturnsFalseWhenTagAdapterNull()` — usar fallback ArrayAdapter (Redis no disponible)
- `testSetWithTagsStoresValueAndIsRetrievable()` — set → get happy path
- `testSetWithTagsReturnsFalseOnException()` — stub TagAwareAdapter que lanza Throwable
- `testComputeIfAbsentExecutesFnOnCacheMiss()` — callback invocado cuando key no existe
- `testComputeIfAbsentReturnsCachedOnHit()` — callback NO invocado cuando key ya existe
- `testComputeIfAbsentWithTagsStoresTaggedItem()` — tags en computeIfAbsent
- Archivo: `tests/Unit/Core/CacheTest.php`

**A.2 ContainerTest.php — enableCompilation path**

- `testEnableCompilationSetsPath()` — el compilado se activa si se llama enableCompilation antes de make()
- Nota: en tests, el path temporal debe limpiarse en tearDown
- Archivo: `tests/Unit/Core/ContainerTest.php`

**A.3 LoyaltyRepositoryTest.php ✅ HECHO**

- `#[CoversClass(LoyaltyRepository::class)]`
- LoyaltyRepository no extiende AbstractRepository — inyecta PDO directamente
- `testGetLeaderboardReturnsRankedArray()` — stub PDO+stmt, fetchAll retorna rows con 'rank' key
- `testGetLeaderboardWithCustomLimit()` — limit 5 pasa el valor correcto al stmt execute
- `testGetLeaderboardReturnsEmptyArrayWhenNoData()` — fetchAll devuelve []
- Patrón: `$pdoMock = $this->createStub(PDO::class)`, `$stmtMock = $this->createStub(PDOStatement::class)`, `$stmtMock->method('fetchAll')->willReturn([...])`, `$pdoMock->method('prepare')->willReturn($stmtMock)`
- Archivo: `tests/Unit/Repositories/LoyaltyRepositoryTest.php`

**A.4 MercurePublisherServiceTest.php (NUEVO)**

- `MercurePublisherService` es estática, usa `$_ENV['MERCURE_JWT_SECRET']` y `file_get_contents()`
- `testPublishReturnsFalseWhenSecretNotSet()` — sin MERCURE_JWT_SECRET → false sin excepción
- `testPublishReturnsTrueWhenHubResponds()` — difícil sin mockear file_get_contents; usar `@runInSeparateProcess` + `$_ENV`
- `testPublishReturnsFalseWhenHubNotAvailable()` — file_get_contents retorna false
- Nota: como usa `file_get_contents` nativo, el único test 100% seguro sin infrastructure es `testPublishReturnsFalseWhenSecretNotSet()`
- Archivo: `tests/Unit/Services/MercurePublisherServiceTest.php`

### Fase B — Modelos faltantes (11 archivos, ~2 días)

Patrón estándar (igual que ProductTest.php, UserTest.php):

```
$pdoMock = $this->createStub(PDO::class);
$stmtMock = $this->createStub(PDOStatement::class);
$pdoMock->method('prepare')->willReturn($stmtMock);
$model = new XxxModel($pdoMock);
```

Crear en `tests/Unit/Models/`:

1. `CafeTest.php` — findAll, findById, findBySlug, create, update, softDelete, findActive
2. `AllergenTest.php` — findAll, findById, create, update
3. `MenuCategoryTest.php` — findAll, findById, create, update, delete
4. `LoyaltyCardTest.php` — findByUser, create, addPoints (si existe), getByUserId
5. `LoyaltyRewardCatalogTest.php` — findAll, findById, findActive, create
6. `PermissionTest.php` — findAll, findByRole
7. `TrackerTest.php` — record, findByUser
8. `FavoriteTest.php` — add, remove, findByUser, exists
9. `ReservationItemTest.php` — create, findByReservation
10. `Traits/HasUuidTest.php` — generar UUID en create, no sobreescribir UUID existente
11. `Traits/ValidatesDataTest.php` — validateRequired, validateEmail, validateRange

### Fase C — Repositorios faltantes ✅ COMPLETADA (22-04-2026, cobertura: 43.76%)

Todos extienden AbstractRepository excepto LoyaltyRepository (PDO directo).
Referencia: `tests/Unit/Repositories/UserRepositoryTest.php`
*(Adicionalmente creados fuera del plan: `ProductRepositoryTest.php`, `UserRepositoryTest.php`, `ProductRepositoryStockTest.php`)*

Alta lógica (prioridad máxima):

1. ~~`LoyaltyRepositoryTest.php`~~ ✅ HECHO
2. `TimeSlotRepositoryTest.php` — findAvailableByDate, findByTimeRange, updateCapacity, softDelete
3. `SessionRepositoryTest.php` — create, find, delete, cleanup
4. `StaffShiftRepositoryTest.php` — findByStaff, findByCafe, findByDateRange, create, update
5. ~~`StatisticsRepositoryTest.php`~~ ✅ HECHO

Lógica media:
6. `AuthTokenRepositoryTest.php` — findByToken, create, delete, findExpired
7. `ApiTokenRepositoryTest.php` — findByToken, create, revoke
8. `SupervisorAssignmentRepositoryTest.php` — findByCafe, findBySupervisor, assign, remove
9. ~~`HealthCheckRepositoryTest.php`~~ ✅ HECHO
10. ~~`AuditLogRepositoryTest.php`~~ ✅ HECHO
11. ~~`AuthLogRepositoryTest.php`~~ ✅ HECHO
12. `SettingRepositoryTest.php` — get, set, getAll, delete (SettingRepository — CRUD)
13. ~~`RoleRepositoryTest.php`~~ ✅ HECHO

Menor lógica:
14. `MenuCategoryRepositoryTest.php` — findAll, findById, create, update
15. `NewsletterSubscriptionRepositoryTest.php` — subscribe, unsubscribe, isSubscribed, findAll
16. `TrackerRepositoryTest.php` — findByUser, create, findByAction
17. `FavoriteRepositoryTest.php` — add, remove, findByUser, exists
18. `ReservationItemRepositoryTest.php` — create, findByReservation
19. `AnimalIncidentRepositoryTest.php` — findByAnimal, create, update, close

### Fase D — Profundidad en servicios con tests superficiales (~1 día)

Servicios con ≤2 tests que tienen lógica real:

- `TimeSlotService` (2 tests) → añadir null cafeId, null guests, repository throws PDOException
- `AdminReportService` (1 test) → añadir paths de error
- `AdminStatisticsService` (5 tests) → verificar todos los métodos públicos
- `MercurePublisherService` (0 → Fase A.4)
- Revisar: LoyaltyService, GamificationService, CacheService (ya tienen tests, verificar profundidad)

### Fase E — Controladores faltantes (~1 día)

Controllers Public (7 pendientes) — `tests/Unit/Http/Controllers/Public/`:

1. `CafeControllerTest.php` — index (null), show encontrado (null), show 404
2. `MenuControllerTest.php` — show (null), byAllergen, search
3. `NewsletterControllerTest.php` — subscribe ok (redirect), duplicado, email inválido; unsubscribe ok/token inválido
4. `PageControllerTest.php` — páginas existentes/no existentes
5. `LoyaltyControllerTest.php` — dashboard con $_SESSION, redeem ok/puntos insuficientes
6. `WaitlistViewControllerTest.php` — index, show
7. `QuizControllerTest.php` — show (null)

Controllers User (1 pendiente) — `tests/Unit/Http/Controllers/User/`:
8. `WaitlistControllerTest.php` — join ok/fail, cancel ok/fail

Controllers Api/V1 (pendientes):
9. `TimeSlotControllerTest.php`
10. `WaitlistControllerTest.php`

### Fase F — Tests de integración ✅ COMPLETADA (archivos pre-existentes con escenarios completos)

- `AuthIntegrationTest.php` ✅ — 6 tests: register, login, hash, duplicados, wrong password
- `ReservationIntegrationTest.php` ✅ — 8 tests: create, cancel, getByUser, inactive cafe, past date
- `WaitlistIntegrationTest.php` ✅ — 5 tests: join, promote, status, ordering, expiration
- `ReviewIntegrationTest.php` ✅ — 4 tests: create, moderate, getByCase, averageRating
- Tests requieren `make test-integration` (docker-compose.test.yml + komorebi_test DB)

### Fase G — CI enforcement ✅ COMPLETADA (28-04-2026)

- `scripts/check-coverage.php` — lee Clover XML, verifica umbral configurable (default 85%), exit 1 si falla
- `make test-coverage` — genera HTML + Clover XML y ejecuta check-coverage.php 85 al final
- `make coverage-check` — target standalone para verificar XML ya generado

---

## Archivos clave de referencia

- `tests/Unit/Repositories/UserRepositoryTest.php` — patrón repositorio
- `tests/Unit/Models/UserTest.php` — patrón modelo
- `tests/Unit/Services/ReservationServiceTest.php` — patrón servicio profundo
- `tests/Unit/Http/Controllers/Manager/CafeControllerTest.php` — patrón controller
- `tests/Unit/Core/CacheTest.php` — para expandir TagAware (línea 1+)
- `app/Core/Cache.php` líneas 300+ — implementación invalidateTags/setWithTags/computeIfAbsent
- `app/Repositories/LoyaltyRepository.php` — getLeaderboard() usa PDO directo + RANK OVER
- `app/Services/MercurePublisherService.php` — publish() usa $_ENV + file_get_contents

---

## Reglas anti-regresión (mantener en toda nueva escritura)

1. `executionOrder="default"` en phpunit.xml — NUNCA cambiar
2. NUNCA `@depends` ni `#[Depends]` — rompe descubrimiento a escala
3. Namespace exacto: `tests/Unit/Repositories/` → `namespace Tests\Unit\Repositories;`
4. `#[CoversClass(ConcreteClass::class)]` — nunca interfaz ni abstracta
5. Protocolo por archivo: `php -l` → test aislado → `--list-tests | grep -c`
6. Checkpoint cada 5 archivos: `--list-tests tests/Unit/ | wc -l` debe crecer

---

## Progresión esperada

## Baseline real (22-04-2026)

- **Lines: 25.39%** (4154/16358)
- **Methods: 23.45%** (436/1859)
- **Classes: 11.11%** (26/234)

## Estado confirmado post-Fases A.3 + C parcial (22-04-2026)

- **Statements: 40.44%** (6613/16353) — gap restante a 85%: **7288 stmts**
- **Tests: 1703** pasando (206 de repositorios); suite OK con avisos menores
- **Repos con tests: 16/29** (6 de 19 Fase C hechos ✅; ProductRepositoryTest + UserRepositoryTest + ProductRepositoryStockTest extra)

El estimado previo de 45-50% era incorrecto. La diferencia de ~20 pp se explica porque
los tests usan stubs/mocks intensivamente, lo que no ejecuta las líneas reales de
los servicios, repos y modelos bajo test.

---

## Progresión esperada (recalibrada)

| Fase | Tests nuevos (est.) | Cobertura esperada |
| Baseline real (22-04-2026) | — | **25.39%** |
| A (FrankenPHP gaps) | +12 | ~27-29% |
| B (11 modelos) | +120 | ~38-45% |
| **→ REAL (22-04-2026, 1703 tests)** | — | **40.44% ✅** |
| C restante (13/19 repos pendientes) | +130 | ~53-60% |
| D (service depth) | +40 | ~63-68% |
| E (8 controllers) | +80 | ~71-76% |
| F (integración) | +30 | ~79-83% |
| G (CI) | +0 | 85%+ confirmado |

**IMPORTANTE**: Baseline real = 25.39% líneas. Gap a cubrir: ~60 pp.

---

## Primer paso de ejecución

```bash
# 1. Verificar que todos los tests siguen pasando
docker compose exec app php vendor/bin/phpunit --no-coverage 2>&1 | tail -5

# 2. Obtener baseline real de cobertura
make test-coverage

# 3. Verificar inventario — checkpoint inicial
docker compose exec app php vendor/bin/phpunit --no-coverage --list-tests tests/Unit/ | wc -l
```
