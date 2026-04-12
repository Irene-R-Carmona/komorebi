# Plan Fase 0 — Arquitectura Fundacional Definitiva

# Komorebi Café · 12/04/2026

# Estado: Definitivo — sin gaps, sin legacy, sin deprecated

---

## TL;DR

Fase 0 establece todos los contratos de arquitectura e implementa los cambios estructurales de los que dependen las Fases 1 (Observabilidad) y 2 (UI/UX). Se organiza en 5 streams paralelizables donde sea posible. Genera: DTOs tipados, Value Objects, interfaces para los 52 servicios, splits completos de 5 servicios que violan SRP, corrección de seguridad en repositorios, migración completa de rutas API a v1, adopción de Transformers en todos los controllers, y documentación definitiva.

**No hay decisiones abiertas. Todo lo que requiere código tiene firma exacta definida en este plan.**

---

## STREAMS (organización de dependencias)

```
Stream 1 (Domain Layer)  ──────────────────────────────► bloquea Stream 2
Stream 3 (Repo Security) ──────────────────────────────► bloquea Stream 4
                          Stream 2 (Service Layer) ──────► bloquea Stream 4
                                                   Stream 4 (HTTP Layer) ──► Stream 5
                                                                        Stream 5 (Docs + Quality)
```

Streams 1 y 3 son completamente independientes → ejecución paralela posible.
Stream 2 requiere que Stream 1 esté completo (los interfaces referencian DTOs).
Stream 4 requiere que Stream 2 y 3 estén completos.
Stream 5 requiere todo lo anterior.

---

## STREAM 1 — Domain Layer: DTOs y Value Objects

**Directorio: `app/Domain/DTO/` y `app/Domain/ValueObjects/`**
*(app/Domain/ ya existe con ReservationStateMachine.php)*

### Paso 1.1 — Contrato base: DomainTransferObject

**Archivo nuevo:** `app/Domain/DTO/DomainTransferObject.php`

```php
interface DomainTransferObject
{
    public static function fromArray(array $data): static;
    public function toViewArray(): array;
}
```

### Paso 1.2 — DTOs de Presentación (8 clases)

Cada DTO es `final readonly class` que implementa `DomainTransferObject`.
Campos exactos definidos a continuación:

**`app/Domain/DTO/UserDTO.php`**

```
id: int, uuid: string, name: string, email: string, avatar: ?string,
roles: array, is_active: bool, cafe_id: ?int, created_at: string
```

Excluye: password, last_ip_address, locked_until, login_attempts, deleted_at,
email_verified_at, anonymized_at, last_login, last_password_change

**`app/Domain/DTO/CafeDTO.php`**

```
id: int, slug: string, name: string, japanese_name: ?string, description: ?string,
location: string, category: string, animal_type: string, price_per_hour: float,
capacity_max: int, rating_avg: float, opening_time: string, closing_time: string,
timezone: string, is_active: bool, image_url: ?string
```

**`app/Domain/DTO/ReservationDTO.php`**

```
id: int, uuid: string, cafe_id: int, user_id: int, date: string, time: string,
guest_count: int, status: string, pass_name: ?string, check_in_at: ?string,
check_out_at: ?string, final_amount: ?float, payment_status: ?string,
payment_method: ?string, notes: ?string
```

Excluye: tracker_id, current_zone_id, protocol_hygiene, protocol_briefing,
protocol_shoes, payment_notes, internal flags

**`app/Domain/DTO/ProductDTO.php`**

```
id: int, name: string, slug: string, description: ?string, price: float,
category_id: int, category_name: string, allergens: array, is_available: bool,
image_url: ?string
```

Excluye: recipe_steps, ingredients_list, station, critical_check

**`app/Domain/DTO/ReviewDTO.php`**

```
id: int, cafe_id: int, cafe_name: string, user_name: string, rating: int,
title: string, body: string, status: string, created_at: string
```

Excluye: user_id (dado por seguridad), ip_address, flagged_at

**`app/Domain/DTO/AnimalDTO.php`**

```
id: int, cafe_id: int, name: string, species: string, description: ?string,
image_url: ?string, is_active: bool
```

**`app/Domain/DTO/WaitlistEntryDTO.php`**

```
id: int, token: string, status: string, position: ?int, slot_date: string,
slot_time: string, cafe_name: string, guest_count: int, contact_email: string,
expires_at: ?string
```

**`app/Domain/DTO/LoyaltyDTO.php`**

```
user_id: int, points_balance: int, tier_name: string, tier_level: int,
stamps_count: int, next_reward_at: ?int
```

### Paso 1.3 — Value Objects (2 clases)

**`app/Domain/ValueObjects/Email.php`**

```php
final readonly class Email {
    private function __construct(public readonly string $value) {}
    public static function fromString(string $email): self  // throws InvalidArgumentException si no es RFC 5321
    public function __toString(): string
}
```

Validación: `filter_var($email, FILTER_VALIDATE_EMAIL)` + longitud máx 254 chars.

**`app/Domain/ValueObjects/Slug.php`**

```php
final readonly class Slug {
    private function __construct(public readonly string $value) {}
    public static function fromString(string $slug): self  // throws InvalidArgumentException
    public function __toString(): string
}
```

Validación: regex `^[a-z0-9][a-z0-9-]{0,98}[a-z0-9]$` (min 2, max 100 chars, solo minúsculas/dígitos/guiones).

---

## STREAM 3 — Repository Security: Campos Sensibles

*(Independiente de Stream 1, paralelo)*

### Paso 3.1 — UserRepository: corrección crítica

**Archivo:** `app/Repositories/UserRepository.php`

**`getSelectFields()` queda con SOLO:**

```
id, uuid, name, email, avatar, created_at, is_active, deleted_at,
email_verified_at, cafe_id, preferences
```

**Nuevo método para autenticación:**

```php
public function findByEmailWithCredentials(string $email): ?array
// SELECT id, uuid, email, password, login_attempts, locked_until, last_ip_address, is_active, email_verified_at
// WHERE email = :email AND deleted_at IS NULL
```

**Nuevo método para operaciones de seguridad:**

```php
public function findByIdForSecurity(int $id): ?array
// SELECT id, uuid, email, password, login_attempts, locked_until, last_ip_address
```

**Actualizar inmediatamente:** `AuthService::login()` para usar `findByEmailWithCredentials()` en lugar de cualquier llamada que actualmente obtenga password.

### Paso 3.2 — ReservationRepository: campos operativos

**Archivo:** `app/Repositories/ReservationRepository.php`

`getSelectFields()` excluye: `tracker_id`, `current_zone_id`, `protocol_hygiene`,
`protocol_briefing`, `protocol_shoes`, `payment_notes`

**Nuevo método:**

```php
public function findWithOperationalData(int $id): ?array
// Incluye todos los campos incluyendo tracker_id, zone, protocols, payment_notes
// Usado únicamente desde KitchenService, ReceptionService, y controllers de staff
```

### Paso 3.3 — ProductRepository: campos de receta

**Archivo:** `app/Repositories/ProductRepository.php`

`getSelectFields()` excluye: `recipe_steps`, `ingredients_list`, `station`, `critical_check`

**Nuevo método:**

```php
public function findWithRecipe(int $id): ?array
// Incluye todos los campos, usado solo desde KitchenService / KDS controllers
```

### Paso 3.4 — Convención documentada en AbstractRepository

Añadir docblock en `getSelectFields()`:

```php
/**
 * Returns ONLY presentation-safe fields: NO passwords, IPs, credentials,
 * operational/internal fields, or recipe data.
 * For sensitive field access, define explicit named methods in the concrete repository.
 */
abstract protected function getSelectFields(): array;
```

---

## STREAM 2 — Service Layer: Interfaces + SRP Splits

*(Depende de Stream 1 — los interfaces referencian tipos DTO)*

### Paso 2.1 — Directorio de contratos

Crear: `app/Services/Contracts/`

Todos los interfaces de servicios van aquí, con namespace `App\Services\Contracts`.

### Paso 2.2 — Split: AuthService (15 → 5 métodos)

**Mantener en `AuthService`:** `login()`, `register()`, `logout()`, `check()`, `user()`

**Crear `PasswordResetService`** (nuevo, `app/Services/PasswordResetService.php`):

```php
public function requestPasswordReset(string $email, string $ipAddress, ?string $userAgent = null): Result
public function validatePasswordResetToken(string $token): Result
public function resetPasswordWithToken(string $token, string $newPassword, string $confirmPassword): Result
```

**Crear `EmailVerificationService`** (nuevo, `app/Services/EmailVerificationService.php`):

```php
public function sendVerificationEmail(int $userId): Result
public function verifyEmailToken(string $token): Result
```

**`SessionManagementService`** (ya existe): `getActiveSessions()`, `revokeSession()`, `revokeAllOtherSessions()`, `getAuthHistory()` — sin cambios en lógica.

**Interfaces a crear:**

- `app/Services/Contracts/AuthServiceInterface.php` — 5 métodos
- `app/Services/Contracts/PasswordResetServiceInterface.php` — 3 métodos
- `app/Services/Contracts/EmailVerificationServiceInterface.php` — 2 métodos
- `app/Services/Contracts/SessionManagementServiceInterface.php` — 4 métodos (ya existe clase)

**Actualizar `bootstrap/container.php` y `AuthServiceProvider.php`** para registrar nuevos servicios:

```php
Container::singleton(PasswordResetServiceInterface::class, fn() => new PasswordResetService(...));
Container::singleton(EmailVerificationServiceInterface::class, fn() => new EmailVerificationService(...));
Container::singleton(SessionManagementServiceInterface::class, fn() => Container::make(SessionManagementService::class));
```

**Actualizar controllers** que actualmente inyectan `AuthService` para usar solo los métodos que les correspondan. Los controllers de gestión de contraseña → inyectar `PasswordResetServiceInterface`. Los de verificación de email → `EmailVerificationServiceInterface`.

### Paso 2.3 — Eliminar UserService (13 métodos → 3 nuevos servicios)

`UserService` se ELIMINA. Sus métodos se distribuyen:

**Crear `UserProfileService`** (nuevo):

```php
public function getCurrentProfile(): UserDTO
public function getProfile(int $userId): UserDTO
public function updateProfile(int $userId, array $data): Result         // @return Result<null>
public function updateAvatar(int $userId, ?string $filename): Result    // @return Result<null>
```

**Crear `UserPreferenceService`** (nuevo):

```php
public function getPreferences(int $userId): array
public function updatePreferences(int $userId, array $preferences): Result  // @return Result<null>
```

**Crear `UserAccountService`** (nuevo):

```php
public function changePassword(int $userId, string $currentPassword, string $newPassword, string $confirmPassword): Result
public function verifyEmail(int $userId): Result
public function deactivateAccount(int $userId): Result
public function reactivateAccount(int $userId): Result
```

`deleteAccount()` → ya existe en `AccountDeletionService` (existente, sin cambios).
`getUsersByRole()`, `hasPermission()` → mover a `UserManagementService` (ya existe).

**Interfaces a crear:**

- `app/Services/Contracts/UserProfileServiceInterface.php`
- `app/Services/Contracts/UserPreferenceServiceInterface.php`
- `app/Services/Contracts/UserAccountServiceInterface.php`
- `app/Services/Contracts/AccountDeletionServiceInterface.php` (para AccountDeletionService existente)
- `app/Services/Contracts/UserManagementServiceInterface.php` (para UserManagementService existente)

**Actualizar `SharedServiceProvider`** y `bootstrap/container.php`:

- Eliminar `UserService::class` binding
- Registrar los 3 nuevos servicios + sus interfaces

**Los controllers que inyectaban `UserService`**:

- Cada controller recibe ÚNICAMENTE el/los servicios que realmente usa (→ inyección precisa, no el monolito)

### Paso 2.4 — Eliminar AdminService (16 métodos → 3 nuevos servicios)

`AdminService` se ELIMINA. `getDatabase()` se elimina sin reemplazar (es `Database::getConnection()` — nunca debió estar en un servicio).

**Crear `AdminStatisticsService`** (nuevo):

```php
public function getSystemStatistics(): array
public function getMonthlyStats(int $month, int $year): array
public function getCafePerformanceStats(string $dateFrom, string $dateTo, int $limit = 10): array
public function getReservationTrendStats(string $dateFrom, string $dateTo): array
public function getReservationsByCafeType(string $dateFrom, string $dateTo): array
public function getUserDistributionByRole(): array
public function getTopCafes(string $dateFrom, string $dateTo, int $limit = 10): array
```

**Crear `AdminActivityService`** (nuevo):

```php
public function getRecentReservations(int $limit = 10): array
public function getUsersWithRoles(): array
public function getProductsWithCategories(): array
public function getReservationsWithDetails(int $limit = 100): array
public function getRecentActivity(int $limit = 10): array
public function getSystemStatus(): array
```

**Crear `AdminReportService`** (nuevo):

```php
public function getReportsSummary(string $dateFrom, string $dateTo): array
```

**Interfaces a crear:**

- `app/Services/Contracts/AdminStatisticsServiceInterface.php`
- `app/Services/Contracts/AdminActivityServiceInterface.php`
- `app/Services/Contracts/AdminReportServiceInterface.php`

**Actualizar `StaffServiceProvider`** o el provider donde AdminService estaba registrado.

### Paso 2.5 — Trim: ReservationService (13 → 5 métodos)

`ReservationService` conserva solo los métodos de ciclo de vida de reserva:

```php
public function create(array $data, ?CartService $cart = null): Result  // @return Result<int>
public function cancel(int $reservationId, int $userId): Result          // @return Result<null>
public function getByUser(int $userId, ?string $status = null): array
public function getUpcoming(int $userId, int $limit = 5): array
public function enrichCartItems(array $cartItems): array
```

Métodos movidos o eliminados:

- `getAvailableSlots()` → `AvailabilityService` (ya existe)
- `getAvailablePasses()` → `AvailabilityService`
- `getAvailableCafesForReservation()` → `AvailabilityService`
- `getAvailableCafesById()` → `AvailabilityService`
- `getAvailablePassesForReservation()` → `AvailabilityService`
- `validateCafeExists()` → inline validation en `create()` o `AvailabilityService`
- `validatePassExists()` → inline validation o `AvailabilityService`

**Interfaces a crear:**

- `app/Services/Contracts/ReservationServiceInterface.php` — 5 métodos
- `app/Services/Contracts/AvailabilityServiceInterface.php` (para AvailabilityService existente)

### Paso 2.6 — Split: ReviewService (19 → 3 servicios)

**`ReviewService`** conserva (comandos de usuario):

```php
public function createReview(int $userId, int $cafeId, int $rating, string $title, string $body): Result
public function updateReview(...): Result
public function deleteReview(int $reviewId, ?int $userId = null): Result
public function canUserReview(int $userId, int $cafeId): array
public function userHasCompletedReservation(int $userId, int $cafeId): bool
public function userHasReviewInCafe(int $userId, int $cafeId): bool
```

**Crear `ReviewQueryService`** (nuevo, consultas de lectura):

```php
public function getReviewsByUserId(int $userId): array
public function getReviewsByCafeId(int $cafeId): array
public function listApprovedReviews(int $cafeId, int $page = 1): array
public function listUserReviews(int $userId): array
public function getReview(int $reviewId): ?ReviewDTO
public function calculateAverageRating(int $cafeId): float
public function getCafeRatingStats(int $cafeId): array
```

**Crear `ReviewModerationService`** (nuevo, comandos de moderación admin):

```php
public function approveReview(int $reviewId): Result
public function rejectReview(int $reviewId, string $reason): Result
public function moderateReview(int $reviewId, string $status): Result
public function listPendingReviews(int $page = 1): array
public function deleteReviewById(int $reviewId): Result
```

**Interfaces a crear:**

- `app/Services/Contracts/ReviewServiceInterface.php`
- `app/Services/Contracts/ReviewQueryServiceInterface.php`
- `app/Services/Contracts/ReviewModerationServiceInterface.php`

### Paso 2.7 — Interfaces para todos los demás servicios inyectados sin interfaz

Crear interfaces en `app/Services/Contracts/` para los servicios restantes sin interfaz.
Cada interfaz replica la firma pública de la clase concreta actual:

| Interfaz nueva | Clase existente |
|---|---|
| `CafeServiceInterface` | `CafeService` |
| `MenuServiceInterface` | `MenuService` |
| `ProductServiceInterface` | `ProductService` |
| `AllergenServiceInterface` | `AllergenService` |
| `WaitlistServiceInterface` | `WaitlistService` |
| `LoyaltyServiceInterface` | `LoyaltyService` |
| `GamificationServiceInterface` | `GamificationService` |
| `NewsletterServiceInterface` | `NewsletterService` |
| `WeatherServiceInterface` | `WeatherService` |
| `ClimaContextoServiceInterface` | `ClimaContextoService` |
| `CartServiceInterface` | `CartService` |
| `KitchenServiceInterface` | `KitchenService` |
| `ReceptionServiceInterface` | `ReceptionService` |
| `AnimalCareServiceInterface` | `AnimalCareService` |
| `SupervisorAssignmentServiceInterface` | `SupervisorAssignmentService` |
| `SettingsServiceInterface` | `SettingsService` |
| `HolidayServiceInterface` | `HolidayService` |
| `TimeSlotServiceInterface` | `TimeSlotService` |
| `ReservationTimeSlotServiceInterface` | `ReservationTimeSlotService` |
| `HealthCheckServiceInterface` | `HealthCheckService` |
| `FileUploadServiceInterface` | `FileUploadService` |
| `AuthTokenServiceInterface` | `AuthTokenService` |

**Actualizar todos los ServiceProviders** para registrar cada servicio bindeado a su interfaz:

```php
// Antes:
Container::singleton(CafeService::class, fn() => new CafeService(...));
// Después:
Container::singleton(CafeServiceInterface::class, fn() => new CafeService(...));
```

**Actualizar todos los constructores de controllers y servicios** que inyectan clases concretas para inyectar la interfaz correspondiente.

### Paso 2.8 — Error Boundary: cero throws desde métodos públicos de servicios

Clases que actualmente lanzan desde su API pública:

**`WeatherService`**: envolver en `try/catch` → `Result::fail('Clima no disponible', 'weather_unavailable')`
**`TelegramService`**: envolver en `try/catch` → `Result::fail('Notificación no enviada', 'telegram_unavailable')`
**`ContextServiceInstance`**: si lanza → `Result::fail('Contexto no disponible', 'context_error')`

Regla codificada (aplicar a todos los 52 servicios):

- Método público → SIEMPRE return `Result` o tipo puro (array, DTO, bool, int) — NUNCA `throw`
- Método privado → puede lanzar excepciones custom internas (`BusinessRuleException`, etc.)
- Si la excepción no es del dominio (infra, red, BD) → capturar y convertir a `Result::fail()`
- Excepciones de programación (config faltante, argumento imposible) → `throw` es correcto

### Paso 2.9 — Result<T>: docblocks en todos los servicios

Añadir `@return Result<tipo>` en cada método de servicio que devuelve `Result`.
Tipos posibles: `Result<int>`, `Result<UserDTO>`, `Result<array<string,mixed>>`, `Result<null>`.
`Result::ok()` sin argumento = confirmación pura → siempre `@return Result<null>`.
Mensajes de confirmación → `Flash::success()` en el controller, NO en `Result::ok('texto')`.

---

## STREAM 4 — HTTP Layer: API Migration + Transformers

*(Depende de Stream 2 y Stream 3)*

### Paso 4.1 — Auditar 4 fetch() calls sin ruta en routes.php

Antes de migrar, resolver las rutas huérfanas detectadas en JS:

- `/api/cookies/save-dietary` (dietary-preferences.js L38)
- `/api/cookies/save-filters` (cookie-helper.js L62)
- `/api/cookies/get-filters` (cookie-helper.js L85)
- `/api/supervisor/dashboard-data` (supervisor-dashboard.js L40)

Acción por cada una: confirmar si existe en routes.php como catch-all o dinámica.
Si no existe → añadir al router o marcar JS como dead code a eliminar.
Estas rutas también migran a v1 si son activas.

### Paso 4.2 — Migrar TODAS las rutas /api/ sin versión a /api/v1/

**Rutas a reubicar en routes.php** (22 rutas sin versión → todas bajo /api/v1/):

```
// Antes: /api/menu/alergenos → Después: /api/v1/menu/alergenos
// Antes: /api/menu/productos → Después: /api/v1/menu/productos
// Antes: /api/menu/view-product → Después: /api/v1/menu/view-product
// Antes: /api/cookies/accept → Después: /api/v1/cookies/accept
// Antes: /api/cookies/reject → Después: /api/v1/cookies/reject
// Antes: /api/cookies/update → Después: /api/v1/cookies/update
// Antes: /api/cookies/newsletter-prompted → Después: /api/v1/cookies/newsletter-prompted
// Antes: /api/cart/guest → Después: /api/v1/cart/guest
// Antes: /api/cart (GET) → Después: /api/v1/cart
// Antes: /api/cart/add → Después: /api/v1/cart/add
// Antes: /api/cart/remove → Después: /api/v1/cart/remove
// Antes: /api/cart/update → Después: /api/v1/cart/update
// Antes: /api/cart/clear → Después: /api/v1/cart/clear
// Antes: /api/newsletter/subscribe → Después: /api/v1/newsletter/subscribe
// Antes: /api/favorites/toggle → Después: /api/v1/favorites/toggle
// Antes: /api/favorites → Después: /api/v1/favorites
// Antes: /api/reservations/available → Después: /api/v1/reservations/available
// Antes: /api/reservations/create → Después: /api/v1/reservations/create
// Antes: /api/loyalty/validate/{code} → Después: /api/v1/loyalty/validate/{code}
// Antes: /api/loyalty/use → Después: /api/v1/loyalty/use
// Antes: /api/loyalty/redeem → Después: /api/v1/loyalty/redeem
```

**Controllers API que actualmente están en `Api/` (raíz, sin V1)**:
Mover a `Api/V1/`: `CartController`, `CookieController`, `FavoriteController`, `LoyaltyController`, `MenuController`, `NewsletterApiController`, `ReservationController`

Los 6 controllers en `Api/V1/` ya están en el lugar correcto.
`AbstractApiController` permanece en `Api/` (es base, no endpoint).

### Paso 4.3 — Actualizar JavaScript: todos los fetch() con rutas /api/

Archivos a actualizar:

- `public/js/sections/menu.js` — 3 llamadas (`/api/cart/guest` x2, `/api/cart/update`)
- `public/js/init/alpine-components.js` — 1 llamada (`/api/loyalty/redeem`)
- Todos los archivos que referencian `/api/` sin versión (resultado del grep de paso 4.1)

Todas las URLs `/api/xxx` → `/api/v1/xxx`.

### Paso 4.4 — Transformers en TODOS los controllers (no solo Api/)

**Actualmente:** Transformers usados ÚNICAMENTE en `Api/` controllers via `AbstractApiController::transform()`.
**Objetivo:** Todos los controllers que sirven datos de entidades principales aplican Transformer antes de View::render().

**Mecanismo (no romper View::render()):**
Los HTML controllers llaman `$transformer->transform($data)` y pasan el resultado a `View::render()`.
El resultado es un array → compatible con `escapeData()`.

**Controllers a actualizar (ejemplos representativos, aplicar al resto):**

- `Admin\UserController::index()` → `$users = $transformer->collection($users, new UserTransformer())`
- `Shared\ReservationController::index()` → `$reservas = (new ReservationTransformer())->collection($reservas)`
- `Public\CafeController::show()` → `$cafe = (new CafeTransformer())->transform($cafe)`

**Transformers existentes:** CafeTransformer, UserTransformer, ReservationTransformer, ReviewTransformer, WaitlistTransformer, AllergenTransformer ← todos reutilizables.

**Nuevos Transformers a crear** (para entidades que aún no tienen Transformer):

- `ProductTransformer` (excluye recipe, station, critical_check)
- `AnimalTransformer`
- `LoyaltyTransformer`

### Paso 4.5 — Prohibir objetos en View::render()

Añadir en `app/Core/View.php` en el docblock de `render()`:

```php
/**
 * @param array<string, array<mixed>|string|int|float|bool|null|Raw> $data
 * No objects allowed. DTOs must call toViewArray() before passing.
 */
```

Añadir en `phpstan.neon` una regla custom (o baseline comment) para detectar `instanceof` de objeto en $data.

### Paso 4.6 — OpenAPI: cobertura completa de v1

**Archivo:** `docs/openapi.yaml`

Añadir especificación para los 18 endpoints v1 no documentados actualmente:

- `/api/v1/menu/alergenos`, `/productos`, `/view-product`
- `/api/v1/cookies/*` (4 endpoints)
- `/api/v1/cart/*` (5 endpoints)
- `/api/v1/newsletter/subscribe`
- `/api/v1/favorites/*` (2 endpoints)
- `/api/v1/reservations/*` (2 endpoints)
- `/api/v1/loyalty/*` (3 endpoints)

Añadir sección en info: "Los endpoints bajo /api/v1/ son la API completa. No existen endpoints /api/ sin versión."

Documentar schemas de autenticación: `apiAuth` (Bearer token o session), `csrf` (header X-CSRF-Token).

---

## STREAM 5 — Quality Enforcement + Documentación

*(Depende de todos los streams anteriores)*

### Paso 5.1 — PHPStan: actualizar configuración

**Archivo:** `phpstan.neon`

- Ejecutar `make phpstan` tras todos los cambios — resolución de errores en baseline si los hay
- Añadir al análisis los nuevos directorios: `app/Domain/DTO/`, `app/Domain/ValueObjects/`, `app/Services/Contracts/`
- Añadir en `phpstan-baseline.neon` solo los errores que son a resolver en Fases futuras (no los de Fase 0)
- Objetivo: PHPStan nivel 5 sin nuevos errores

### Paso 5.2 — Actualizar `docs/ARCHITECTURE.md`

Añadir sección definitiva **"Decisiones Arquitectónicas — v2 (Abril 2026)"** con:

**Capa de Dominio (`app/Domain/`):**

- DTOs: `final readonly class` en `app/Domain/DTO/`, implementan `DomainTransferObject`
- Value Objects: `final readonly class` en `app/Domain/ValueObjects/`, validación en constructor
- Domain Logic: `app/Domain/Reservation/ReservationStateMachine` (ya existe, patrón a seguir)

**Contratos de Capas (flujo de datos):**

```
DB → Repository (FETCH_ASSOC array)
   → Service   (construye DTO, devuelve Result<DTO|int|null>)
   → Controller (Result::unwrap, llama Transformer o toViewArray())
   → View      (array solo, Raw para JSON y HTML pre-sanitizado)
```

**Regla View:** `View::render()` recibe ÚNICAMENTE arrays con valores escalares, Raw, o arrays de los anteriores. NUNCA objetos.

**Service Error Boundary:**

- Público: SIEMPRE Result o tipo puro — NUNCA throw
- Privado: puede lanzar excepciones custom internas
- Infraestructura externa: wrap en try/catch → Result::fail('...', 'code')
- Errores de programación/config: throw directo (no son flujos esperados)

**CQRS Naming Convention:**

- `get*()`, `find*()`, `list*()`, `search*()`, `count*()` → Query (sin side effects)
- `create*()`, `update*()`, `delete*()`, `toggle*()`, `cancel*()`, `send*()`, `publish*()` → Command (con side effects)
- `validate*()` (privado) → Helper interno, puede lanzar excepciones
- `build*()`, `calculate*()`, `prepare*()` → Cálculo puro, sin I/O

**Repository Field Convention:**

- `getSelectFields()` → SOLO campos de presentación (nunca password, IPs, credenciales, campos operativos)
- Acceso a campos sensibles → método explícito con nombre descriptivo en repositorio concreto

**API Versioning:**

- TODOS los endpoints bajo `/api/v1/`
- Sin rutas `/api/` sin versión
- Nuevos endpoints siempre bajo `/api/v1/`

### Paso 5.3 — Actualizar `AGENTS.md`

Añadir las siguientes convenciones en sección **Critical Patterns**:

```
**DTO pattern** — Services return Result<DTO>; controllers call $dto->toViewArray() for views.
Result::ok($dto) — typed payload via @return Result<UserDTO> docblock.

**View contract** — View::render() receives only arrays with scalar values, Raw, or nested arrays.
Never pass objects (DTOs, services, models) to View::render(). Call toViewArray() first.

**Service interface** — All injectable services are bound to their interface in the DI container.
Controllers inject interfaces, not concrete classes.

**getSelectFields() contract** — Returns ONLY presentation-safe fields. Credentials (password, IPs,
locked_until) and operational fields (protocol_*, recipe_steps) are NEVER in getSelectFields().
Use explicit named methods for sensitive access.

**Error boundary** — All public service methods return Result or a scalar type. They NEVER throw.
Exceptions thrown by infrastructure (network, DB, external services) are caught and converted to
Result::fail('message', 'code'). Private methods may throw domain exceptions internally.
```

### Paso 5.4 — PSR-12 compliance check

`make cs-fix` sobre todos los archivos nuevos y modificados en Fase 0.

---

## Archivos Relevantes

**Nuevos (crear):**

- `app/Domain/DTO/DomainTransferObject.php`
- `app/Domain/DTO/UserDTO.php`, `CafeDTO.php`, `ReservationDTO.php`, `ProductDTO.php`, `ReviewDTO.php`, `AnimalDTO.php`, `WaitlistEntryDTO.php`, `LoyaltyDTO.php`
- `app/Domain/ValueObjects/Email.php`, `Slug.php`
- `app/Services/PasswordResetService.php`, `EmailVerificationService.php`
- `app/Services/UserProfileService.php`, `UserPreferenceService.php`, `UserAccountService.php`
- `app/Services/AdminStatisticsService.php`, `AdminActivityService.php`, `AdminReportService.php`
- `app/Services/ReviewQueryService.php`, `ReviewModerationService.php`
- `app/Services/Contracts/` — ~40 interfaces (listadas en Paso 2.7 + los de cada split)
- `app/Http/Transformers/ProductTransformer.php`, `AnimalTransformer.php`, `LoyaltyTransformer.php`

**Modificar:**

- `app/Repositories/UserRepository.php` — campo separation + nuevo método
- `app/Repositories/ReservationRepository.php` — campo separation + nuevo método
- `app/Repositories/ProductRepository.php` — campo separation + nuevo método
- `app/Repositories/AbstractRepository.php` — docblock en getSelectFields()
- `app/Services/AuthService.php` — reducir a 5 métodos
- `app/Services/ReservationService.php` — reducir a 5 métodos
- `app/Services/ReviewService.php` — reducir a 6 métodos
- `app/Services/WeatherService.php` — error boundary fix
- `app/Services/TelegramService.php` — error boundary fix
- `app/Providers/AuthServiceProvider.php`, `SharedServiceProvider.php`, `StaffServiceProvider.php`
- `bootstrap/container.php` — nuevos bindings de interfaces
- `app/routes.php` — migración completa a /api/v1/
- `app/Http/Controllers/Api/` — 7 controllers a mover a Api/V1/
- `public/js/sections/menu.js`, `public/js/init/alpine-components.js`, otros js con /api/
- `docs/ARCHITECTURE.md`, `AGENTS.md`, `docs/openapi.yaml`

**Eliminar:**

- `app/Services/UserService.php` (todos los métodos movidos)
- `app/Services/AdminService.php` (todos los métodos movidos)

---

## Verificación

**Automatizada:**

1. `make phpstan` → 0 errores nuevos tras todos los cambios
2. `make cs-check` → PSR-12 limpio en todos los archivos nuevos/modificados
3. `make test-unit` → sin regresiones en tests existentes
4. `make test-integration` → sin regresiones, incluyendo test de integración que confirme que `UserRepository::findById()` NO devuelve `password` ni `last_ip_address`

**Manual:**
5. Cada controller que inyectaba `UserService` inyecta ahora SOLO la interfaz del servicio específico que usa (no UserService completo)
6. Grep: `grep -r "UserService\|AdminService" app/Http/Controllers/` → 0 resultados (servicios eliminados)
7. Grep: `grep -r "'password'" app/Repositories/UserRepository.php` → solo en `findByEmailWithCredentials()` y `findByIdForSecurity()`, nunca en `getSelectFields()`
8. Grep: `grep -r "'/api/" public/js/` → 0 resultados sin versión (todas son /api/v1/)
9. Grep: `grep -r "'/api/" app/routes.php` → 0 resultados sin versión (todas son /api/v1/)
10. Abrir `docs/openapi.yaml` y confirmar que tiene spec para todos los endpoints /api/v1/ (29 endpoints)

---

## Orden de Ejecución Recomendado

```
[Paralelo]
  Stream 1 (Domain Layer)    ← sin dependencias
  Stream 3 (Repo Security)   ← sin dependencias

[Secuencial]
  Stream 2 (Service Layer)   ← tras Stream 1
  Stream 4 (HTTP Layer)      ← tras Stream 2 y 3
  Stream 5 (Docs + Quality)  ← tras todo
```

---

## Decisiones Finales (sin ambigüedad, sin opciones abiertas)

| Decisión | Resolución |
|---|---|
| ¿DTOs en Http/ o Domain/? | `app/Domain/DTO/` — son objetos del dominio, no del transporte |
| ¿View recibe DTOs u arrays? | ARRAYS únicamente. DTOs llaman toViewArray(). |
| ¿Result transporta qué? | Typed via @return Result<T> en PHPDoc. Runtime: mixed (PHP 8.4 limitation) |
| ¿getSelectFields() incluye campos sensibles? | NUNCA. Métodos específicos para acceso sensible |
| ¿Dónde van las interfaces de servicios? | `app/Services/Contracts/` (un directorio, no disperso) |
| ¿UserService y AdminService se mantienen? | SE ELIMINAN. Todos sus métodos se distribuyen correctamente |
| ¿Se divide AuthService? | SÍ: en 4 servicios (AuthService, PasswordResetService, EmailVerificationService, SessionManagementService) |
| ¿Se divide ReviewService? | SÍ: en 3 servicios (ReviewService, ReviewQueryService, ReviewModerationService) |
| ¿Legacy /api/ sin versión? | ELIMINADO. Migración completa a /api/v1/ |
| ¿Controllers de Api/ en raíz? | Movidos a Api/V1/ (todos excepto AbstractApiController que es base) |
| ¿Transformers en HTML controllers? | SÍ, todos los controllers que sirven entidades principales |
| ¿Value Objects en DTOs? | Email y Slug como Value Objects tipados. El resto de campos: tipos primitivos PHP |
| ¿Error boundary en servicios externos? | WeatherService y TelegramService envuelven throws → Result::fail() |
| ¿Interfaces para los 52 servicios? | SÍ, todos los servicios inyectables. ~40 interfaces nuevas |
