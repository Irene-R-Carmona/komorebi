# Cobertura de tests al 85% — Komorebi Café (v2 — scope refinado)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans.

**Goal:** Alcanzar ≥85% de cobertura sobre el scope limpio (solo código con lógica de negocio real).

**Prerequisito:** El plan `2026-04-20-fix-controller-design-bugs.md` debe estar completado antes de ejecutar la Fase 0.5 y Fase 4.

---

## Scope real tras exclusiones

| Categoría | Stmts raw | Excluir? | Razón |
|-----------|-----------|----------|-------|
| app/Core/Seeders | ~2,014 | ✅ Excluir | Scripts de datos |
| app/Events | ~50 | ✅ Excluir | Data bags readonly (solo constructores) |
| app/Domain/DTO | ~300 | ✅ Excluir | fromArray/toViewArray triviales, solo type-casts |
| app/Listeners | ~120 | ✅ Excluir | Thin wrappers: log o Queue::push, sin lógica |
| app/Jobs/JobInterface.php | ~10 | ✅ Excluir | Interface pura |
| app/Jobs/SendTelegramNotificationJob.php | ~25 | ✅ Excluir | Solo delega a TelegramService |
| app/Http/Controllers/Api/AbstractApiController.php | ~100 | ✅ Excluir | Solo helpers de respuesta JSON (success, notFound, collection) |
| app/Services/Contracts/ | ~0 | ✅ Excluir | Interfaces (sin statements ejecutables) |
| app/Repositories/Contracts/ | ~0 | ✅ Excluir | Interfaces puras |
| app/Models/Contracts/ | ~0 | ✅ Excluir | Interfaces puras |
| **Todo lo demás** | ~15,600 | Incluir | Lógica de negocio real |

**Scope limpio estimado: ~15,600 statements**
**Cobertura actual real: 3,710 / 15,600 = ~24%**
**Objetivo 85%: ~13,260 statements cubiertos**
**Gap a cubrir: ~9,550 statements más**

---

## Reglas de escritura de tests (sin deuda técnica)

1. Docblock obligatorio (3 preguntas) en todo archivo de test
2. Nunca `createStub(FinalClass::class)` — instanciar real con `new FinalModel($pdoMock)`
3. `createStub()` para dependencias sin aserciones; `createMock()` solo al verificar llamadas
4. Cada test prueba UN comportamiento; nombre describe el resultado esperado
5. API de Result: `$result->ok`, `$result->error`, `$result->data` — NUNCA `$result->getMessage()`
6. `#[TestDox('...')]` en cada test method
7. Clases de test: siempre `final class`
8. Para tests de controllers: preparar `$_SESSION` en setUp() si el controller usa Session
9. Models son `final` → `new ModelClass($pdoMock)` con `$pdoMock->method('prepare')->willReturn($stmtMock)`

---

## FASE 0 — Scope limpio + errores (Día 1) → ~24%

### 0.1 Exclusiones en phpunit.xml (sección `<exclude>`)

- [x] Añadir `<directory suffix=".php">app/Core/Seeders</directory>`
- [x] Añadir `<directory suffix=".php">app/Events</directory>`
- [x] Añadir `<directory suffix=".php">app/Domain/DTO</directory>`
- [x] Añadir `<directory suffix=".php">app/Listeners</directory>`
- [x] Añadir `<file>app/Jobs/JobInterface.php</file>`
- [x] Añadir `<file>app/Jobs/SendTelegramNotificationJob.php</file>`
- [x] Añadir `<file>app/Http/Controllers/Api/AbstractApiController.php</file>`
- [x] Añadir `<directory suffix=".php">app/Services/Contracts</directory>`
- [x] Añadir `<directory suffix=".php">app/Repositories/Contracts</directory>`
- [x] Añadir `<directory suffix=".php">app/Models/Contracts</directory>`

### 0.2 Env var faltante en phpunit.xml

- [x] `APP_URL` ya presente en phpunit.xml — no requería cambio

### 0.3 Fix TransactionalServiceTest (2 tests)

- [x] Ya usa `$result->error` — no requería cambio

### 0.4 Fix WaitlistServiceTest (9 tests)

- [x] Ya pasa 9/9 — fix aplicado en sesión anterior

### 0.5 Fix controllers no inyectables (requiere plan fix-controller-design-bugs completado)

- [x] Plan fix-controller-design-bugs completado — prerequisito cumplido

### 0.6 Verificación

- [x] 856 / 856 tests pasan, 0 errores
- [x] Cobertura base real: **15.97%** (2,525 / 15,806 líneas) tras exclusiones

---

## FASE 1 — Profundidad en servicios existentes (Días 2-5) → ~44% — 🟡 EN IMPLEMENTACIÓN

> 40 archivos de test existen pero cubren ~1.4 métodos por servicio. Cada servicio tiene 8-12 métodos públicos.
> **Referencia**: `tests/Unit/Services/ReservationServiceTest.php`

### 1.1 Auth y cuenta

- [x] Expandir `AuthServiceTest` — login inválido, password incorrecto, logout, register duplicado, register exitoso, user bloqueado
- [ ] Expandir `AuthTokenServiceTest` — token expirado, token inválido, refresh exitoso
- [x] Expandir `AccountDeletionServiceTest` — con reservas activas (falla), exitoso con cleanup
- [ ] Expandir `SessionManagementServiceTest` — sesión expirada, regeneración de ID, concurrente

### 1.2 Reservas y waitlist

- [x] Expandir `ReservationServiceTest` — cancel con penalización, reschedule, validación capacidad, solapamiento
- [x] Expandir `ReservationTimeSlotServiceTest` — todos los métodos, paths de error DB
- [ ] Expandir `WaitlistServiceTest` (post-fix Fase 0) — join exitoso, promoteNext, confirmPromotion, cancel, getPosition, history, expireTokens

### 1.3 Reviews

- [x] Expandir `ReviewServiceTest` — review duplicada, rating fuera de rango, get con filtros

### 1.4 Loyalty, gamification, carta

- [ ] Expandir `LoyaltyServiceTest` — puntos insuficientes, acumular, historial, caducidad
- [ ] Expandir `GamificationServiceTest` — todos los achievements, puntos por acción
- [x] Expandir `ProductServiceTest` — CRUD completo + validaciones
- [ ] Expandir `MenuServiceTest`, `AllergenServiceTest` — CRUD completo + validaciones

### 1.5 Staff y operaciones

- [x] Expandir `StaffShiftServiceTest` — solapamiento de turnos, turno sin café asignado, turno pasado
- [x] Expandir `SupervisorAssignmentServiceTest` — asignación duplicada, removeFail
- [ ] Expandir `KitchenServiceTest`, `HealthCheckServiceTest`

### 1.6 Cache y servicios externos

- [ ] Expandir `CacheServiceTest` — TTL expirado, invalidación por patrón, cache miss
- [x] Expandir `WeatherServiceTest` — HTTP failure, respuesta malformada, cache hit
- [ ] Expandir `ClimaContextoService`, `MicroestacionesService` — HTTP failure, respuesta malformada, cache hit

### 1.7 Nuevos archivos (8 servicios sin tests)

Crear en `tests/Unit/Services/`:

- [x] `EmailVerificationServiceTest.php` — enviar, verificar, expirado, reenviar
- [x] `PasswordResetServiceTest.php` — solicitar, validar token, cambiar password, expirado
- [x] `ReviewModerationServiceTest.php` — aprobar, rechazar, spam, estadísticas
- [x] `ReviewQueryServiceTest.php` — buscar por café/usuario, paginación, filtros
- [ ] `TimeSlotServiceTest.php` — getAvailable, getById, generar slots, bloquear slot
- [x] `UserAccountServiceTest.php` — cambiar password, actualizar perfil, eliminar cuenta
- [x] `UserPreferenceServiceTest.php` — get/set/delete preferencias
- [x] `UserProfileServiceTest.php` — get, actualizar, validaciones

> **Estado a 21-04-2026:** 804/804 tests OK, 0 warnings, 0 notices. 7/8 nuevos servicios creados. Pendiente: AuthTokenService, SessionManagement, WaitlistService, LoyaltyService, GamificationService, MenuService, AllergenService, KitchenService, HealthCheckService, CacheService, ClimaContextoService, MicroestacionesService, TimeSlotServiceTest.

**Verificación Fase 1**: `make test-coverage` → App\Services ≥ 78%

---

## FASE 2 — Tests de modelos (Días 6-9) → ~57%

> Todos `final`. Patrón: `$pdoMock = $this->createStub(PDO::class)`, `$pdoMock->method('prepare')->willReturn($stmtMock)`, luego `new ModelClass($pdoMock)`
> Para métodos que retornan Result: asertir `$result->ok`, `$result->error`, `$result->data`
> Para métodos que retornan `?array` o `int`: asertir directamente

Crear en `tests/Unit/Models/`:

- [ ] `UserTest.php` — findByEmail, findById, create, updatePassword, softDelete, findByUuid, roles, isLocked
- [ ] `TimeSlotTest.php` — findAvailable, findById, findByIdForUpdate, hasAvailability, decrementSpots, incrementSpots, blockSlot, create, generateSlots, getOccupancyStats
- [ ] `ProductTest.php` — findAll, findById, create, update, delete, findByCategory, updateStock
- [ ] `CafeTest.php` — findAll, findById, findBySlug, create, update, softDelete, findActive
- [ ] `ReservationTest.php` — findById, create, update, cancel, findByUser, findByTimeSlot
- [ ] `ReviewTest.php` — findById, create, findByCafe, findByUser, getAverageRating, pending
- [ ] `WaitlistTest.php` — findByToken, findByUserAndSlot, create, update, delete, findActive
- [ ] `LoyaltyCardTest.php` — findByUser, create, addPoints, redeemReward
- [ ] `LoyaltyRewardCatalogTest.php` — findAll, findById, findActive, create
- [ ] `AnimalTest.php` — findAll, findById, create, update, softDelete, findActive
- [ ] `MenuCategoryTest.php` — findAll, findById, create, update, delete
- [ ] `SettingTest.php` — get, set, getAll, delete
- [ ] `AllergenTest.php` — findAll, findById, create, update
- [ ] `RoleTest.php` — findAll, findByName, assignToUser, removeFromUser
- [ ] `PermissionTest.php` — findAll, findByRole
- [ ] `TrackerTest.php` — record, findByUser
- [ ] `FavoriteTest.php` — add, remove, findByUser, exists
- [ ] `AuditLogTest.php` — create, findByUser, findByAction
- [ ] `AuthAuditLogTest.php` — create, findByUser, findByIp
- [ ] `ReservationItemTest.php` — create, findByReservation
- [ ] `Traits/HasUuidTest.php` — genera UUID en create, no sobreescribe UUID existente
- [ ] `Traits/ValidatesDataTest.php` — validateRequired, validateEmail, validateRange

**Verificación Fase 2**: App\Models ≥ 78%; cobertura global ≥ 55%

---

## FASE 3 — Tests de repositorios faltantes (Días 10-11) → ~63%

**Referencia**: `tests/Unit/Repositories/UserRepositoryTest.php`

Crear en `tests/Unit/Repositories/`:

- [ ] `TimeSlotRepositoryTest.php` — findAvailableByDate, findByTimeRange, updateCapacity, softDelete
- [ ] `StaffShiftRepositoryTest.php` — findByStaff, findByCafe, findByDateRange, create, update
- [ ] `SupervisorAssignmentRepositoryTest.php` — findByCafe, findBySupervisor, assign, remove
- [ ] `HealthCheckRepositoryTest.php` — findByAnimal, findByDate, create, update
- [ ] `LoyaltyCardRepositoryTest.php` — findByUser, create, updatePoints, findExpired
- [ ] `LoyaltyRewardRepositoryTest.php` — findByUser, create, findPending
- [ ] `TrackerRepositoryTest.php` — findByUser, create, findByAction

Expandir repositorios existentes:

- [ ] `ReservationRepositoryTest` — findByStatus, findByDateRange, cancel, métodos custom
- [ ] `UserRepositoryTest` — findByRole, findInactive, softDelete, updateLastLogin
- [ ] `ReviewRepositoryTest` — findPending, findByCafe, getAverages

**Verificación Fase 3**: App\Repositories ≥ 72%; cobertura global ≥ 61%

---

## FASE 4 — Tests unitarios de controllers (Días 12-15) → ~73%

> Patrón:
>
> - Inyectar interfaces en constructor (no concretos)
> - `createStub(ServerRequestInterface::class)` para request, con `method('getParsedBody')->willReturn([...])` si es POST
> - Preparar `$_SESSION` en setUp() si el controller lee Session::get()
> - Controller retorna `null` (View::render) o `ResponseInterface` (redirect/json)

**Referencia**: `tests/Unit/Controllers/Manager/CafeControllerTest.php`

### Auth (`tests/Unit/Controllers/Auth/`)

- [ ] `AuthControllerTest.php` — showLogin (null), processLogin ok (redirect), processLogin fail (null), showRegister (null), processRegister ok (redirect), processRegister fail (null), logout (redirect)
- [ ] `AccountControllerTest.php` — showProfile, updateProfile ok/fail, changePassword ok/fail, deleteAccount ok/fail
- [ ] `PasswordResetControllerTest.php` — showRequestForm, processRequest ok/fail, showResetForm, processReset ok/fail

### Public (`tests/Unit/Controllers/Public/`)
>
> Estos requieren el fix de plan fix-controller-design-bugs para ser inyectables

- [ ] `HomeControllerTest.php` — index retorna null; datos pasados a View (cafes, ratings, featured)
- [ ] `CafeControllerTest.php` — index, show encontrado, show no encontrado
- [ ] `MenuControllerTest.php` — show, byAllergen, search
- [ ] `NewsletterControllerTest.php` — subscribe ok, duplicado, email inválido; unsubscribe ok, token inválido
- [ ] `PageControllerTest.php` — páginas estáticas existentes y no existentes
- [ ] `LoyaltyControllerTest.php` — dashboard (requiere $_SESSION), redeem ok/puntos insuf.

### Admin (`tests/Unit/Controllers/Admin/`)

- [ ] `UserControllerTest.php` — index, show, create, store ok/fail, edit, update ok/fail, destroy ok/fail
- [ ] `CafeControllerTest.php` — CRUD completo (mismo patrón)
- [ ] `MenuControllerTest.php` — CRUD + categorías
- [ ] `ReservationControllerTest.php` — list, show, cancel ok/fail, report
- [ ] `ReviewControllerTest.php` — index, approve, reject, delete
- [ ] `AnimalControllerTest.php` — CRUD completo
- [ ] `SettingsControllerTest.php` — index, update, bulkUpdate
- [ ] `DashboardControllerTest.php` — index con stats

### Shared y otros

- [ ] `Shared/UserControllerTest.php` — updateProfile, changePassword
- [ ] `Shared/ReviewControllerTest.php` — create, update, delete
- [ ] `Kitchen/KitchenControllerTest.php` — dashboard (post fix plan bugs), updateOrderStatus
- [ ] `Reception/ReceptionControllerTest.php` — dashboard (post fix plan bugs), checkin, checkout
- [ ] `Supervisor/SupervisorControllerTest.php` — dashboard, assignShift

**Verificación Fase 4**: Controllers ≥ 68%; cobertura global ≥ 71%

---

## FASE 5 — Tests de integración flujos críticos (Días 16-17) → ~76%

> Requieren stack Docker corriendo. Testean el HTTP stack completo con BD real.

- [ ] Expandir `AuthIntegrationTest.php` — login → sesión → logout; rate limiting; lockout tras N intentos
- [ ] Expandir `ReservationIntegrationTest.php` — create → confirm → cancel; occupancy correcta; rollback en fallo
- [ ] Crear/expandir `WaitlistIntegrationTest.php` — join → promote → confirm; expiración de tokens; cancel y re-promoción
- [ ] Crear `ReviewModerationIntegrationTest.php` — submit → pending → approve; reject flow
- [ ] Expandir `ProductIntegrationTest.php` — stock decrement en reserva, restore en cancel

**Verificación Fase 5**: `make test-integration` sin errores

---

## FASE 6 — Core, Jobs, Domain, cierre de brecha (Días 18-20) → ~87%

### Core (los testables: Queue, CircuitBreaker, ImageProcessor, TransactionalService)

- [ ] Expandir `CacheTest.php` — deletePattern(), stats(), all(), invalidación por tags
- [ ] Expandir `RouterTest.php` — grupos anidados, middlewares en cadena, route not found 404
- [ ] Expandir `ContainerTest.php` (post fix env) — auto-wiring, singleton, unknown class fail
- [ ] Crear `EnvTest.php` — get, int, bool, require presente/ausente, SecretLoader
- [ ] Expandir `QueueTest.php` — push, pop, retry, dead-letter
- [ ] Expandir `CircuitBreakerTest.php` — open, half-open, reset, threshold exacto

### Jobs con lógica real (`tests/Unit/Jobs/`)

- [ ] Expandir/crear `SendEmailJobTest.php` — handle exitoso, retry on failure, dead-letter
- [ ] Crear `ProcessImageJobTest.php` — MIME válido, MIME inválido, getimagesize falla
- [ ] Crear `RewardUnlockedJobTest.php` — milestone alcanzado, email enviado
- [ ] Crear `WaitlistPromotionJobTest.php` — token vigente, token expirado, promotion

### Domain y ValueObjects

- [ ] `Domain/ValueObjects/EmailTest.php` — formato inválido, demasiado largo, lowercase normalization
- [ ] Crear tests para todos los ValueObjects faltantes: `SlugTest`, `PasswordTest`, `DateStringTest`, `TimeStringTest`
- [ ] Crear `Domain/ReservationStateMachineTest.php` — todas las transiciones válidas, todas las inválidas

### Cierre de brecha con datos reales

- [ ] Abrir `tests/reports/coverage/index.html` para identificar clases con >50 stmts sin cubrir y <40% cobertura
- [ ] Priorizar las 10 clases con mayor gap y añadir tests de edge cases: null inputs, empty arrays, DB connection failure
- [ ] Revisar que todos los métodos de AbstractRepository tengan tests vía algún repositorio concreto

**Verificación Fase 6**: `make test-coverage` → global ≥ 85% sobre scope limpio

---

## FASE 7 — Enforcement en CI (Día 21)

- [ ] Añadir script PHP de verificación post-coverage en `Makefile` target `test-coverage`:

  ```makefile
  @php -r "$$xml = simplexml_load_file('tests/reports/coverage.xml'); $$m = $$xml->project->metrics; $$pct = (int)$$m['coveredstatements'] / (int)$$m['statements'] * 100; if ($$pct < 85) { fwrite(STDERR, 'Coverage ' . round($$pct,1) . '% < 85% requerido\n'); exit(1); }"
  ```

- [ ] Verificar que `make ci` incorpora verificación de coverage mínima

---

## Archivos relevantes

- [phpunit.xml](../../phpunit.xml) — exclusiones, env vars, coverage config
- [tests/Unit/Services/WaitlistServiceTest.php](../Unit/Services/WaitlistServiceTest.php) — fix Fase 0.4
- [tests/Unit/Core/TransactionalServiceTest.php](../Unit/Core/TransactionalServiceTest.php) — fix Fase 0.3
- [tests/Unit/Core/ContainerTest.php](../Unit/Core/ContainerTest.php) — fix Fase 0.2
- [app/Http/Controllers/Public/HomeController.php](../../app/Http/Controllers/Public/HomeController.php) — fix Fase 0.5
- [app/Http/Controllers/Reception/ReceptionController.php](../../app/Http/Controllers/Reception/ReceptionController.php) — fix Fase 0.6
- [app/Http/Controllers/Kitchen/KitchenController.php](../../app/Http/Controllers/Kitchen/KitchenController.php) — fix Fase 0.6
- [app/routes.php](../../app/routes.php) — verificar middleware groups post-fix 0.6
- [app/Core/Result.php](../../app/Core/Result.php) — API: `$result->ok`, `$result->error`, `$result->data`
- [app/Repositories/AbstractRepository.php](../../app/Repositories/AbstractRepository.php) — patrón CRUD base
- [tests/Unit/Services/ReservationServiceTest.php](../Unit/Services/ReservationServiceTest.php) — mejor referencia servicio
- [tests/Unit/Repositories/UserRepositoryTest.php](../Unit/Repositories/UserRepositoryTest.php) — referencia repositorio

---

## Progresión esperada

| Fase | Contenido | Cobertura (scope limpio) |
|------|-----------|--------------------------|
| Baseline | — | ~24% |
| 0 | Scope limpio + fixes | ~24% (denominador cae, numerador sube) |
| 1 | Servicios en profundidad | ~44% |
| 2 | Modelos | ~57% |
| 3 | Repositorios | ~63% |
| 4 | Controllers unit | ~73% |
| 5 | Integration flows | ~76% |
| 6 | Core/Jobs/Domain + cierre | **~87%** |
| 7 | Enforcement CI | ≥85% bloqueante |

---

## Decisiones y exclusiones justificadas

| Categoría excluida | Justificación técnica |
|---|---|
| `app/Events/` | `final readonly` con solo constructor. 0 lógica ejecutable. |
| `app/Domain/DTO/` | `fromArray()` = solo type-casts; `toViewArray()` = return array. Sin validaciones ni bifurcaciones. |
| `app/Listeners/` | Cada listener tiene 1 método con 1-2 líneas: `Logger::info(...)` o `Queue::push(...)`. Sin lógica propia. |
| `app/Core/Seeders/` | Scripts de datos SQL/fixtures. No hay lógica de negocio testeable. |
| `app/Jobs/JobInterface.php` | Interface PHP pura. 0 statements ejecutables. |
| `app/Jobs/SendTelegramNotificationJob.php` | Delega 100% a `TelegramService::sendMessage()`. El servicio ya tiene test. |
| `app/Http/Controllers/Api/AbstractApiController.php` | Solo métodos helper de respuesta JSON (wrappers de ResponseFactory). La lógica está en ResponseFactory. |
| `*/Contracts/` | Interfaces PHP. 0 statements ejecutables por definición. |

**No excluidos intencionalmente:**

- `AbstractRepository.php` — tiene lógica CRUD real (findById, create, update, delete, softDelete)
- `app/Core/Result.php` — tiene computed properties y factory methods con bifurcaciones
- `app/Core/BaseService.php` — helpers de logging usados por servicios
- `app/Middleware/SecurityHeadersMiddleware.php` — lógica CSP nonce compleja (10+ directives)
