# Plan: Unificación de Estilos de Funciones y Clases Globales PHP

**Fecha:** 17 de abril de 2026
**Estado:** 🔵 Plan creado — pendiente inicio
**Rama sugerida:** `fix/unify-global-php-style`

---

## Contexto y Motivación

Una auditoría completa del proyecto detectó dos estilos coexistentes para referenciar
funciones y clases del namespace global de PHP dentro de namespaces declarados:

| Estilo A (dominante, ~95%)    | Estilo B (minoría, ~5%)                                |
|-------------------------------|--------------------------------------------------------|
| `\trim($x)`                   | `use function trim;` + `trim($x)`                      |
| `throw new \RuntimeException` | `use RuntimeException;` + `throw new RuntimeException` |
| `\PDO::FETCH_ASSOC`           | `use PDO;` + `PDO::FETCH_ASSOC`                        |
| `new \DateTimeImmutable()`    | `new DateTimeImmutable()` (sin use)                    |

**Estilo canónico elegido: FQFN con prefijo `\`** (Estilo A).

### Justificación técnica

- Es el estilo dominante en el código existente (≥ 95% de ocurrencias).
- Permite al opcode cache (OPcache) resolver funciones/clases en compile-time sin búsqueda
  en namespace actual.
- Elimina imports innecesarios en clases cortas (una línea de `use` por una función).
- Consistente con la práctica de PHP moderno en proyectos orientados a rendimiento.
- PHPStan nivel 5 ya lo valida — no genera nuevos errores.

---

## Archivos Afectados por Categoría

### Categoría 1 — `use function` (único caso)

| Archivo                                                | Línea  | Corrección                                                   |
|--------------------------------------------------------|--------|--------------------------------------------------------------|
| `app/Http/Controllers/Keeper/AnimalCareController.php` | 21, 96 | Eliminar `use function trim;` → cambiar `trim(` por `\trim(` |

### Categoría 2 — `use RuntimeException;` → `\RuntimeException`

**Services:**

- `app/Services/AuthService.php`
- `app/Services/ContextServiceInstance.php`
- `app/Services/PasswordResetService.php`
- `app/Services/UserProfileService.php`
- `app/Services/UserAccountService.php`
- `app/Services/ReviewService.php`

**Providers:**

- `app/Providers/DatabaseServiceProvider.php`

**Models:**

- `app/Models/Cafe.php`
- `app/Models/Product.php`
- `app/Models/Review.php`
- `app/Models/Role.php`
- `app/Models/Tracker.php`
- `app/Models/Reservation.php`
- `app/Models/Permission.php`
- `app/Models/Traits/ValidatesData.php`

**Core:**

- `app/Core/Config.php`
- `app/Core/Database.php`
- `app/Core/View.php`

**Exceptions:**

- `app/Exceptions/CircuitOpenException.php`
- `app/Exceptions/DateMalformedStringException.php`

> Nota: `WeatherService`, `TelegramService`, `Container` y `SecretLoader` ya usan `\RuntimeException`
> correctamente — no requieren cambio.

### Categoría 3 — `use PDO;` → `\PDO::`

**Eliminar import y añadir `\` prefix en todos los usos de `PDO::` en:**

- `app/Services/ProductService.php`
- `app/Services/ReceptionService.php`
- `app/Services/TimeSlotService.php`
- `app/Services/UserManagementService.php`
- `app/Services/WaitlistService.php`
- `app/Services/SessionManagementService.php`
- `app/Services/ReservationTimeSlotService.php`
- `app/Services/NewsletterService.php`
- `app/Services/LoyaltyService.php`
- `app/Services/KitchenService.php`
- `app/Services/Manager/DashboardService.php`
- `app/Services/CafeService.php`
- `app/Services/AuthTokenService.php`
- `app/Services/AvailabilityService.php`
- `app/Services/AuthService.php`
- `app/Services/ApiTokenService.php`
- `app/Services/AnimalCareService.php`
- `app/Services/AdminStatisticsService.php`
- `app/Services/AdminReportService.php`
- `app/Services/AccountDeletionService.php`
- `app/Repositories/UserRepository.php` (ya usa `\PDO::`, verificar `use PDO;`)
- `app/Jobs/RewardUnlockedJob.php`
- `app/Models/Waitlist.php`

### Categoría 4 — `DateTime` mutable → `DateTimeImmutable`

| Archivo                                      | Método afectado                              | Tipo de cambio                                                                                                                                                                                                    |
|----------------------------------------------|----------------------------------------------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `app/Services/ClimaContextoService.php`      | `obtenerClimaActual()`                       | `new DateTime(...)` → `new \DateTimeImmutable(...)` (solo llama `->format()`)                                                                                                                                     |
| `app/Services/MicroestacionesService.php`    | `obtenerActual()`                            | `new DateTime(...)` → `new \DateTimeImmutable(...)` (solo llama `->format()`)                                                                                                                                     |
| `app/Services/FestivosJaponesesService.php`  | `calcularNesimoLunes()`, `normalizarFecha()` | `DateTime` → `\DateTimeImmutable`; `$fecha->modify(...)` → `$fecha = $fecha->modify(...)` (DTI::modify devuelve nueva instancia); `DateTime::createFromInterface()` → `\DateTimeImmutable::createFromInterface()` |
| `app/Core/Seeders/TimeSlotSeeder.php`        | método interno                               | `new \DateTime()` → `new \DateTimeImmutable()`                                                                                                                                                                    |
| `app/Repositories/ReservationRepository.php` | `generateTimeSlots()`                        | Verificar si usa `->modify()` mutable; si no, migrar a `\DateTimeImmutable`                                                                                                                                       |

### Categoría 5 — Clases de fecha sin `\` prefijo (sin import)

| Archivo                                    | Instancias                      | Corrección                      |
|--------------------------------------------|---------------------------------|---------------------------------|
| `app/Services/ReservationService.php`      | `new DateTimeImmutable()`       | → `new \DateTimeImmutable()`    |
| `app/Services/ReviewModerationService.php` | `new DateTimeImmutable()`       | → `new \DateTimeImmutable()`    |
| `app/Services/AuthService.php`             | `new DateTimeImmutable()`       | → `new \DateTimeImmutable()`    |
| `app/Models/Waitlist.php`                  | `new DateTimeImmutable(...)` x2 | → `new \DateTimeImmutable(...)` |
| `app/Models/TimeSlot.php`                  | `new DateTimeImmutable(...)` x2 | → `new \DateTimeImmutable(...)` |
| `app/Models/Reservation.php`               | `new DateTimeImmutable(...)` x2 | → `new \DateTimeImmutable(...)` |
| `app/Core/Time.php`                        | `new DateTimeImmutable(...)`    | → `new \DateTimeImmutable(...)` |

---

## Deuda Técnica Documentada (fuera del scope de este plan)

Los siguientes `throw new \RuntimeException(...)` en **Models** corresponden a errores de
negocio esperados que deberían devolver `Result::fail()` en lugar de lanzar excepciones.
**No se corrigen en este plan** porque requieren refactorizar los callers.

| Archivo                      | Instancias | Descripción                                                 |
|------------------------------|------------|-------------------------------------------------------------|
| `app/Models/Reservation.php` | 8          | Estados inválidos, no-show, check-in/out, campos requeridos |
| `app/Models/Cafe.php`        | 2          | Slug duplicado                                              |
| `app/Models/Role.php`        | 1          | Rol duplicado                                               |
| `app/Models/Permission.php`  | 1          | Permiso duplicado                                           |
| `app/Models/Tracker.php`     | 1          | Tracker no disponible                                       |

**Plan futuro sugerido:** `2026-XX-XX-models-result-pattern.md` — Migrar Models a Result pattern.

---

## Tasks

- [ ] **PASO 1** — Documentar estilo canónico en `AGENTS.md` y `docs/ARCHITECTURE.md`
- [ ] **PASO 2** — Corregir Categoría 1: `AnimalCareController.php` (`use function trim` → `\trim`)
- [ ] **PASO 3** — Corregir Categoría 2: eliminar `use RuntimeException;` en todos los archivos listados →
  `\RuntimeException`
- [ ] **PASO 4** — Corregir Categoría 3: eliminar `use PDO;` en todos los archivos listados → `\PDO::`
- [ ] **PASO 5** — Corregir Categoría 4: migrar `DateTime` mutable a `\DateTimeImmutable` en 5 archivos
- [ ] **PASO 6** — Corregir Categoría 5: añadir `\` prefix a `DateTimeImmutable` sin import en 7 archivos
- [ ] **PASO 7** — Ejecutar `make phpstan` — verificar 0 errores nuevos respecto a baseline
- [ ] **PASO 8** — Ejecutar `make cs-check` — verificar 0 violaciones PSR-12
- [ ] **PASO 9** — Ejecutar `make test-unit` — verificar que todos los tests siguen en verde

---

## Criterios de Aceptación

1. `grep -r "use function" app/` → 0 resultados.
2. `grep -r "use RuntimeException;" app/` → 0 resultados.
3. `grep -rn "use PDO;" app/` → 0 resultados.
4. `grep -rn "new DateTime(" app/Services/` → 0 resultados (`DateTime` mutable eliminado de Services).
5. `make phpstan` → 0 errores nuevos (igual o menor que baseline).
6. `make test-unit` → todos los tests en verde.
7. `make cs-check` → 0 violaciones.

