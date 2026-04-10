# Plan 6 — Controller DI: Eliminar `?? new` en controladores

**Goal:** Eliminar las ~72 instancias del patrón `?? new` en `app/Http/Controllers/` haciendo los constructores con parámetros requeridos (sin `?`) para que el DI container los resuelva automáticamente.
**Architecture:** Los servicios deben estar registrados en el container antes de que los controladores los pidan. Prerequisito directo: Plan 3 completado.
**Tech Stack:** PHP 8.4, DI Container singleton, PSR-7, PHPUnit 12, `ControllerTestCase` (ya existe).

---

## Estado confirmado (pre-implementación)

| Elemento | Estado |
|---|---|
| `tests/Support/ControllerTestCase.php` | ✅ existe (makeGetRequest, makePostRequest, makeUploadedFile, assertResponseIsRedirect, assertResponseIsJson) |
| `tests/Unit/Http/Controllers/` — 25 test files | ✅ todos existen |
| `withAuthUser()` helper en ControllerTestCase | ❌ falta añadir |
| `assertResponseIsOk()` helper en ControllerTestCase | ❌ falta añadir |
| `Auth/PasswordResetController` — ResponseFactory hardcoded | ⚠️ bug (no inyectable) |
| `Admin/ReviewController` — ResponseFactory hardcoded | ⚠️ bug (no inyectable) |
| Plan 3 (servicios en container) | ❌ prerequisito — ejecutar antes de Fases 1-4 |

---

## Mapa de archivos afectados

| Módulo | Controladores con `?? new` | Grupo |
|---|---|---|
| Auth | AuthController, AccountController, PasswordResetController, AuthLogController | 1 |
| Admin | DashboardController, CafeController, MenuController, ReportController, ReservationController, ReviewController, RoleController, SystemController, UserController, AuditLogController | 2 |
| Manager | CafeController, DashboardController, ReportController, ReviewController, ReservationController, StaffController | 3 |
| Shared | ReservationController, UserController, ReviewController | 3 |
| Keeper | AnimalCareController, AnimalController, AnimalDashboardController, HealthCheckController | 4 |
| Kitchen | KitchenController | 4 |
| Reception | ReceptionController | 4 |
| Supervisor | SupervisorController | 4 |

---

## Fase 0 — Completar ControllerTestCase + bugs inmediatos

*(Sin prerequisitos — puede hacerse ahora mismo, en paralelo con Plan 3)*

### Tarea 0.1 — Añadir helpers a `tests/Support/ControllerTestCase.php`

- [ ] Añadir `withAuthUser(int $userId = 1, string $role = 'user'): ServerRequestInterface`:

```php
protected function withAuthUser(
    ServerRequestInterface $request,
    int $userId = 1,
    string $role = 'user'
): ServerRequestInterface {
    return $request->withAttribute('user', ['id' => $userId, 'role' => $role]);
}
```

- [ ] Añadir `assertResponseIsOk(?ResponseInterface $response): void`:

```php
protected function assertResponseIsOk(?ResponseInterface $response): void
{
    $this->assertNull($response, 'Controllers that call View::render() must return null');
}
```

### Tarea 0.2 — Fix bugs inmediatos (ResponseFactory no inyectable)

**`app/Http/Controllers/Auth/PasswordResetController.php`:**

- [ ] Cambiar constructor de:

  ```php
  public function __construct(?AuthService $authService = null)
  {
      $this->authService = $authService ?? new AuthService();
      $this->response = new ResponseFactory();  // ← bug
  }
  ```

  a:

  ```php
  public function __construct(
      ?AuthService $authService = null,
      ?ResponseFactory $response = null
  ) {
      $this->authService = $authService ?? new AuthService();
      $this->response = $response ?? new ResponseFactory();
  }
  ```

**`app/Http/Controllers/Admin/ReviewController.php`:**

- [ ] Mismo patrón: añadir `?ResponseFactory $response = null` al constructor

- [ ] `make test-unit` — tests existentes deben seguir pasando

---

## Fase 1 — Módulo Auth

*(Prerequisito: Plan 3 Grupo A — `AuthService`, `AccountDeletionService`, `FileUploadService`, `UserService` registrados en container)*

### Tarea 1.1 — `Auth/AuthController`

- [ ] Eliminar `?` de los parámetros del constructor:

  ```php
  // ANTES
  public function __construct(?AuthService $authService = null, ?ResponseFactory $response = null)

  // DESPUÉS
  public function __construct(AuthService $authService, ResponseFactory $response)
  ```

- [ ] Eliminar los `?? new` del cuerpo del constructor

### Tarea 1.2 — `Auth/AccountController` (5 dependencias)

- [ ] Requerir: `AccountDeletionService, AuthService, FileUploadService, UserService, ResponseFactory`
- [ ] Eliminar todos los `?? new` del constructor

### Tarea 1.3 — `Auth/PasswordResetController`

- [ ] Requerir: `AuthService, ResponseFactory`

### Tarea 1.4 — `Auth/AuthLogController`

- [ ] Auditar constructor y hacer parámetros requeridos

### Tarea 1.5 — Actualizar tests Auth

- [ ] Ajustar `tests/Unit/Http/Controllers/Auth/AuthControllerTest.php` — ahora debe inyectar todos los stubs (no solo `response:`)
- [ ] Ajustar `tests/Unit/Http/Controllers/Auth/AccountControllerTest.php`
- [ ] Ajustar `tests/Unit/Http/Controllers/Auth/PasswordResetControllerTest.php`
- [ ] `make test-unit` — verde

---

## Fase 2 — Módulo Admin

*(Prerequisito: Plan 3 Grupos B/C — todos los servicios Admin registrados en container)*

### Tarea 2.1 — Corregir 10 constructores

Para cada controlador, hacer parámetros requeridos (eliminar `?` y `?? new`):

- [ ] `Admin/DashboardController` — requerir `AdminService`
- [ ] `Admin/CafeController` — auditar y requerir sus dependencias
- [ ] `Admin/MenuController` — auditar y requerir sus dependencias
- [ ] `Admin/ReportController` — auditar y requerir sus dependencias
- [ ] `Admin/ReservationController` — auditar y requerir sus dependencias
- [ ] `Admin/ReviewController` — requerir `ReviewService, ResponseFactory`
- [ ] `Admin/RoleController` — auditar y requerir sus dependencias
- [ ] `Admin/SystemController` — auditar y requerir sus dependencias
- [ ] `Admin/UserController` — requerir `UserManagementService, UserRepository, ResponseFactory`
- [ ] `Admin/AuditLogController` — auditar y requerir sus dependencias

### Tarea 2.2 — Actualizar tests Admin

- [ ] Verificar que los 6 tests en `tests/Unit/Http/Controllers/Admin/` ya inyectan stubs — ajustar firmas de constructor en los tests
- [ ] `make test-unit` — verde

---

## Fase 3 — Módulos Manager y Shared

*(Prerequisito: Plan 3 completo)*

### Tarea 3.1 — Manager (6 controladores)

- [ ] `Manager/DashboardController` — requerir `CafeService, DashboardService`
- [ ] `Manager/CafeController` — auditar y requerir
- [ ] `Manager/ReportController` — auditar y requerir
- [ ] `Manager/ReviewController` — auditar y requerir
- [ ] `Manager/ReservationController` — auditar y requerir
- [ ] `Manager/StaffController` — auditar y requerir

### Tarea 3.2 — Shared (3 controladores)

- [ ] `Shared/ReservationController` — requerir: `CartService, ReservationService, Reservation, ClimaContextoService, FestivosJaponesesService, ResponseFactory`
- [ ] `Shared/UserController` — post-Fase2-PSR7 (ya migrado); auditar constructor
- [ ] `Shared/ReviewController` — requerir `ReviewService, Cafe`

### Tarea 3.3 — Actualizar tests Manager y Shared

- [ ] Ajustar `tests/Unit/Http/Controllers/Manager/*`
- [ ] Ajustar `tests/Unit/Http/Controllers/Shared/*`
- [ ] `make test-unit` — verde

---

## Fase 4 — Módulos Keeper, Kitchen, Reception, Supervisor

*(Prerequisito: Plan 3 completo; para Keeper también Fase 2 PSR-7)*

### Tarea 4.1 — Keeper (4 controladores)

- [ ] `Keeper/AnimalCareController` — auditar y requerir
- [ ] `Keeper/AnimalController` — post-Fase2-PSR7 (ya migrado); auditar
- [ ] `Keeper/AnimalDashboardController` — auditar y requerir
- [ ] `Keeper/HealthCheckController` — auditar y requerir

### Tarea 4.2 — Kitchen, Reception, Supervisor

- [ ] `Kitchen/KitchenController` — requerir `KitchenService, ResponseFactory`
  - **Nota:** Mantener `Middleware::auth()` en el constructor (es una utilidad de sesión estática, no un objeto DI)
- [ ] `Reception/ReceptionController` — requerir `ReceptionService, ResponseFactory`
  - **Nota:** Mantener `Middleware::auth()` en el constructor
- [ ] `Supervisor/SupervisorController` — auditar y requerir

### Tarea 4.3 — Actualizar tests Keeper / Kitchen / Reception

- [ ] Ajustar `tests/Unit/Http/Controllers/Keeper/*`
- [ ] Ajustar `tests/Unit/Http/Controllers/Kitchen/*`
- [ ] Ajustar `tests/Unit/Http/Controllers/Reception/*`
- [ ] `make test-unit` — verde

---

## Fase 5 — Verificación global

- [ ] Ejecutar:

  ```bash
  docker compose exec app grep -r "?? new" app/Http/Controllers/ --include="*.php"
  ```

  → Resultado esperado: **0 líneas**

- [ ] `make test-unit` — todos los 25 tests de controladores en verde
- [ ] `make phpstan` — sin errores nuevos respecto al baseline

---

## Commits

```
fix: PasswordResetController y Admin/ReviewController ResponseFactory inyectable
test: añadir withAuthUser y assertResponseIsOk a ControllerTestCase
refactor: Auth controllers constructores requeridos + tests actualizados
refactor: Admin controllers constructores requeridos + tests actualizados
refactor: Manager/Shared controllers constructores requeridos + tests actualizados
refactor: Keeper/Kitchen/Reception/Supervisor constructores requeridos + tests actualizados
```

---

## Decisiones tomadas

| Decisión | Razonamiento |
|---|---|
| `Middleware::auth()` en constructor Kitchen/Reception se mantiene | Es un call estático a sesión PHP, no un objeto inyectable. Moverlo a middleware chain es scope de Fase 2 PSR-7, no de este plan. |
| `Cafe` model sin interfaz en `Shared/ReviewController` | Los modelos Eloquent-style no tienen interfaz. Inyección directa es correcta. |
| `ControllerTestCase` tiene 2 helpers nuevos no 5 | `withAuthUser` y `assertResponseIsOk` son los únicos faltantes; los 3 restantes ya existen. |
| Tests usan named arguments | Patrón establecido en los 25 tests existentes — mantener consistencia. |

---

## Prerequisitos explícitos

1. **Plan 3 completo** — todos los servicios registrados en container (`AuthService`, `ReviewService`, `CafeService`, `MenuService`, `ProductService`, `AnimalCareService`, `WaitlistService`, `UserService`)
2. **Fase 2 PSR-7 completa** — `UserController` y `AnimalController` migrados a PSR-7

## Siguiente plan

**Plan 7 — Keeper SRP split**: Auditar rutas que apuntan a `AnimalController` legacy vs. los nuevos controladores especializados (`AnimalDashboardController`, `AnimalCareController`, `HealthCheckController`) y limpiar el legacy.
