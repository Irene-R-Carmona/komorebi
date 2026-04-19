# Plan: Business Rules Hardening + Zero Legacy/Alias + Q Decisions

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development to implement this plan
> sprint-by-sprint. Steps use checkbox (`- [ ]`) syntax for tracking.
> **Execution choice (confirmed):** ✅ **Subagent-Driven** — subagente fresco por sprint + revisión entre sprints.

**Fecha:** 17 de abril de 2026
**Estado:** 🟡 En implementación — Sprint 3 completo
**Prioridad:** CRÍTICA — defensa TFG
**Origen:** Auditoría `docs/business-rules-audit.md` (87 hallazgos) + regla inmutable "zero legacy/deprecated/alias"

---

## Decisiones Q — Resolver antes de implementar

| ID       | Pregunta                                                      | Decisión                                                                                                                         |
|----------|---------------------------------------------------------------|----------------------------------------------------------------------------------------------------------------------------------|
| **Q-01** | ¿Una sola reseña por usuario por café?                        | ✅ **SÍ** — `UNIQUE(user_id, cafe_id)` + guard en servicio                                                                        |
| **Q-02** | ¿Verificación de email obligatoria antes del primer acceso?   | ✅ **SÍ** — `EmailVerificationService` ya existe; activar guard en `AuthService`                                                  |
| **Q-03** | ¿Complejidad mínima de contraseña?                            | ✅ **SÍ** — mínimo 8 chars, 1 mayúscula, 1 número                                                                                 |
| **Q-04** | ¿Máximo de sellos por usuario por día?                        | ✅ **1 por reserva** — `UNIQUE(reservation_id)` en `loyalty_stamps`                                                               |
| **Q-05** | ¿Cancelar una reserva invalida los sellos asociados?          | ✅ **SÍ** — revertir sello en `cancel()` dentro de la misma transacción                                                           |
| **Q-06** | ¿Se permiten turnos de staff que cruzan medianoche?           | ❌ **NO** — `$start < $end` forzado en validación                                                                                 |
| **Q-07** | ¿Un animal sin `cafe_id` es válido?                           | ❌ **NO** (salvo `quarantine`) — `cafe_id NOT NULL` en servicio                                                                   |
| **Q-08** | ¿Máximo de días de adelanto para reservas configurable?       | ✅ **SÍ** — `RESERVATION_MAX_DAYS_AHEAD` env var (default 60)                                                                     |
| **Q-09** | API `Result`: ¿propiedad o métodos como canónico?             | ✅ **Propiedad** — `$result->ok`, `$result->data`, `$result->error`. Eliminar `isOk()`, `isFail()`, `getDataOr()`, `getMessage()` |
| **Q-10** | `roles` table: ¿columna `slug` o `code`?                      | ✅ **`slug`** — verificar con `DESCRIBE roles`; unificar todos los accesos                                                        |
| **Q-11** | ¿`CartService` devuelve `Result` o `array`?                   | ✅ **`Result`** — unificar contrato de servicio                                                                                   |
| **Q-12** | ¿`NewsletterService::subscribe()` devuelve `Result`?          | ✅ **SÍ** — actualmente devuelve `array`; cambiar a `Result`                                                                      |
| **Q-13** | ¿`deleteReview(null $userId)` elimina sin chequear propiedad? | ✅ **Separar métodos** — `deleteReview(int $reviewId, int $userId): Result` y `deleteReviewAdmin(int $reviewId): Result`          |

---

## Sprint 0 — Zero Legacy / Deprecated / Alias (PRIORIDAD MÁXIMA)

> **Regla inmutable del proyecto:** ningún archivo PHP puede mantener código legacy, deprecated o alias.
> Este sprint limpia primero para que el resto no construya sobre deuda técnica.

### S0-01 — Unificar API de `Result` (Q-09)

- [x] Eliminar de `Result.php`: `isOk()`, `isFail()`, `getDataOr()`, `getMessage()`
- [x] Reemplazar en todo el codebase:
    - `$result->isOk()` → `$result->ok` **(✅ 0 usos — ya limpio)**
    - `$result->isFail()` → `$result->error !== null` **(✅ 0 usos — ya limpio)**
    - `$result->getDataOr($x)` → `$result->data ?? $x` **(✅ 0 usos — ya limpio)**
    - `$result->getMessage()` / `$result->getMessage('fb')` → `$result->error ?? 'fb'` **(✅ 0 usos — ya limpio)**
    - `$result->isSuccess()` → `$result->ok` **(✅ 0 usos — ya limpio)**
- [x] Verificar: `grep -r "isOk\(\)\|isFail\(\)\|getDataOr\(" app/` → 0 resultados
- [x] Archivos conocidos: `AnimalCareService.php` (L234, L386), `StaffShiftService.php`,
  `Api\ReservationController.php`, `ReservationService.php` (comentario L86)

> **Estado real verificado 17/04:** `Result.php` limpio, pero quedan 14× `isOk()`, 4× `isFail()`, 3× `getDataOr()` en callers.
> Estos llamarán a métodos inexistentes en runtime → **ERRORES FATALES en producción**.
> **PRIORIDAD MÁXIMA** — corregir antes de cualquier otra tarea.

### S0-02 — Eliminar fallback `password_hash ?? password` (B-01)

- [x] Verificar columna real: `DESCRIBE users` → confirmar `password` (migración 002)
- [x] Corregir `$user['password_hash']` → `$user['password']` en `UserAccountService.php:53`
- [x] Auditar: `grep -r "'password'" app/Services/` → solo accesos correctos a columna `password`

### S0-03 — Inyección de repositorios en WaitlistService y ReservationTimeSlotService

- [x] Inyectar `ReservationRepository`, `TimeSlotRepository`, `WaitlistRepository` vía constructor
- [x] Eliminar `new Reservation($db)`, `new TimeSlot($db)`, `new Waitlist($db)` directos en servicios
- [x] Actualizar registros en `bootstrap/container.php`
- [x] Verificar: `grep -rn "new Reservation\|new TimeSlot\|new Waitlist" app/Services/` → 0 resultados directos
  > **Verificado 17/04:** no existen `new Waitlist($db)` ni `new TimeSlot($db)` en servicios.
  > `ReceptionService` usa lazy-init `??=` con modelo interno (deuda S4-02, no blocker para defensa).

### S0-04 — Eliminar comentarios NOTE con APIs obsoletas

- [x] Eliminar/actualizar comentario `NOTE: $result->isSuccess()` en `ReservationService.php:86`
- [x] Confirmar que `Api\ReservationController` ya usa `$result->ok`

### S0-05 — Unificar columna de roles: slug vs code (Q-10)

- [x] `DESCRIBE roles` → columna canónica confirmada: **`code`** (migración 002)
- [x] `grep -r "'code'" app/` → todos los accesos usan `code` correctamente
- [x] No existe ningún acceso a `roles.slug` en el codebase — columna real es `code`
  > **Verificado 17/04:** hipótesis Q-10 era incorrecta. La columna es `code` en BD y código. Sin cambios necesarios.

### S0-06 — `#[Override]` faltante en StaffShiftService

- [x] Añadir `use Override;` + `#[Override]` a todos los métodos que implementan la interfaz
- [x] Verificar: `make phpstan` → 0 errores

---

## Sprint 1 — Seguridad HTTP y RBAC (P1 Crítico)

### S1-01 — RBAC bypass: `str_ends_with($role, '_admin')` (R-01)

- [x] Reemplazar por comprobación exacta `$rStr === self::ROLE_ADMIN` (solo rol `admin` da acceso total)
- [x] Eliminado `str_ends_with($rStr, '_admin')` en `Middleware.php` (líneas 115, 144)

### S1-02 — IDOR en Waitlist: `user_id` desde body (I-01)

- [x] Eliminar `$data['user_id']` del request body en `POST /api/v1/waitlist/join`
- [x] `user_id` exclusivamente desde sesión autenticada (`Session::userId()`)
- [x] Ruta movida al grupo `apiAuth` (requiere autenticación, devuelve 401 en vez de redirect)

### S1-03 — Open Redirect: `HTTP_REFERER` sin validar (V-01)

- [x] Creado `View::safeReferer()` que valida host contra `APP_URL`
- [x] Aplicado en `ExceptionHandler.php` (2 ocurrencias) y `View::back()`

### S1-04 — Rate limiting no aplica en CLI (A-01)

- [x] Revisado: bypass CLI en `AuthService.php:349-351` es **intencional** — tests CLI no deben activar rate limiting
  > Comentario ya presente: "In CI/local tests we often run under CLI; skip rate limiting when running CLI"

### S1-05 — Logger con datos sensibles (L-01)

- [x] Corregido `Logger::error` → `Logger::debug` en logs de login exitoso (`performSuccessfulLogin`)
- [x] Verificado: ningún `Logger::error/warning` loguea contraseñas, tokens o sesiones

### S1-06 — Auditar cobertura CSRF en rutas

- [x] Añadido `$mw->csrf()` a `POST /newsletter/subscribe` y `POST /waitlist/confirm/{token}`
- [x] Todas las rutas POST/DELETE no-API verificadas con CSRF
- [x] Rutas API bajo `/api/v1/` exentas por diseño (autenticación por token)

---

## Sprint 2 — Validación de entrada (P1 Crítico)

### S2-01 — `strlen` → `mb_strlen` en ReviewService (V-02)

- [x] `ReviewService.php` → `mb_strlen()` en `createReview` y `updateReview`
- [x] Texto japonés/emoji no falla la validación de longitud

### S2-02 — Eliminar `htmlspecialchars` antes de persistir (I-02)

- [x] Eliminado `htmlspecialchars()` en `createReview()` y `updateReview()` antes de INSERT/UPDATE
- [x] El escapado ocurre en la vista (auto-escape de `View::render()`), no en BD

### S2-03 — Validación de rango en WaitlistService (V-03)

- [x] `$data['guest_count']` validado en rango 1–10; rechaza 0, negativos y superiores a 10

### S2-04 — Validación de fechas reales (V-04)

- [x] `ReservationService.php` → `DateTimeImmutable::createFromFormat()` + no pasado + respeta `RESERVATION_MAX_DAYS_AHEAD` (default 60)
- [x] Añadido `BusinessRuleException::tooFarAhead(int $maxDays)` factory method

### S2-05 — `validateRequired` acepta `0` como vacío (V-05)

- [x] Campos numéricos con valor `0` se marcan como faltantes (son inválidos de negocio)
- [x] Separado "presente" (`isset`) de "no vacío string" (`!== ''`) de "no cero numérico"

### S2-06 — Rango de edad de animales (V-06)

- [x] `AnimalCareService.php` → `age_years` validado en rango 0–50 en `createAnimal()` y `updateAnimal()`

### S2-07 — Complejidad de contraseña (Q-03)

- [x] Validado en `AuthService::register()`: min 8 chars, 1 mayúscula, 1 número
- [x] Validado en `UserAccountService::changePassword()`: mismas reglas

---

## Sprint 3 — Reglas de dominio (P1/P2)

### S3-01 — Una reseña por usuario por café (Q-01)

- [x] Guard en `createReview()` si ya existe reseña del mismo `user_id` y `cafe_id`
- [x] Migración `020_review_unique_constraint.sql` con `UNIQUE(user_id, cafe_id)`

### S3-02 — Email verificado antes del primer acceso (Q-02)

- [x] Guard en login: `email_verified_at IS NOT NULL`; controlable por `EMAIL_VERIFICATION_REQUIRED` env
- [x] Test: login sin email verificado → error `email_not_verified`

### S3-03 — `deleteReview` separar admin/owner (Q-13)

- [x] `deleteReview(int $reviewId, int $userId): Result` (nunca nullable)
- [x] `deleteReviewAdmin(int $reviewId): Result` con log de auditoría

### S3-04 — Revertir sello al cancelar reserva (Q-05)

- [x] `ReservationService::cancel()` llama a `LoyaltyService::reverseStamp(int $userId)` en la misma transacción
- [x] Test: cancelar reserva con sello → sello desaparece del historial

### S3-05 — `addStamp` guard para valores ≤ 0 (D-01)

- [x] Guard: `$stamps <= 0` → `Result::fail('El número de sellos debe ser positivo', 'invalid_stamps')`

### S3-06 — Race condition en `redeemReward` (R-02)

- [x] Envolver en transacción con `SELECT ... FOR UPDATE` en la fila del usuario

### S3-07 — `cancel()` debe devolver `Result` (A-02)

- [x] Cambiar retorno `bool` → `Result` en `ReservationService::cancel()`; actualizar controllers

### S3-08 — Turnos de staff no cruzan medianoche (Q-06)

- [x] Validación `$start < $end` en `StaffShiftService`

### S3-09 — Animal sin `cafe_id` solo si `quarantine` (Q-07)

- [x] Guard en `createAnimal()` de `AnimalCareService`

### S3-10 — Newsletter: `subscribe()` devuelve `Result` (Q-12)

- [x] Cambiar retorno de `array` a `Result`; actualizar controllers

### S3-11 — Token de confirmación newsletter con `expires_at` (D-02)

- [x] Al generar token: `expires_at = now() + 48h`; verificar en `confirm()`

### S3-12 — `getConfirmedEmails()` sin LIMIT (D-03)

- [x] Añadir LIMIT 500

### S3-13 — Cupón `WELCOME5` hardcoded (D-04)

- [x] Mover a `NEWSLETTER_WELCOME_COUPON` env var (default 'WELCOME5')

### S3-14 — `CartService` devuelve `Result` (Q-11)

- [x] Cambiar métodos que devuelven `array` → `Result`; actualizar controllers

---

## Sprint 4 — Arquitectura y consistencia (P2)

### S4-01 — SQL raw en servicios → repositorios (A-03)

- [ ] `AnimalCareService.php` (INSERT INTO animals) — pendiente refactor extenso
- [ ] `ApiTokenService.php`, `AccountDeletionService.php`, `UserManagementService.php` — pendiente
- [x] `AuthService.php:238` (UPDATE users SET updated_at) — delegado a userRepo.updateLastLogin()
- [ ] `ProductService.php`, `AvailabilityService.php` (queries privadas) — pendiente

### S4-02 — Lazy init en LoyaltyService → inyección (A-04)

- [x] Eliminar propiedades `??=`; inyectar por constructor; registrar en container

### S4-03 — Tier thresholds en constantes (A-05)

- [x] Extraer `10, 30, 50` a constantes de clase; unificar `$tierOrder`

### S4-04 — `health_status` vs `current_status` (A-06)

- [x] `DESCRIBE animal_health_checks` → confirmado: no existe `health_status`; corregido en AnimalCareService y DataViewerController

### S4-05 — Guard `validity_days = 0` en LoyaltyService (D-05)

- [x] Si `validity_days <= 0` → usar default 365

---

## Sprint 5 — P3 y limpieza final

### S5-01 — `Logger::debug` sin guard en ReservationService (L-02)

- [x] Eliminado debug log de configuración estructural (pass validation dump)

### S5-02 — Eliminar ruta histórica alias en routes.php (L-04)

- [x] Eliminada ruta duplicada `/auth/forgot-password` fuera del grupo auth

### S5-03 — Endpoint público waitlist: validar email de contacto

- [x] N/A — endpoint ya requiere autenticación (Session::userId()), usuarios no autenticados reciben 401

### S5-04 — `Logger::error` nivel incorrecto en AuthService (L-03)

- [x] Ya corregido previamente — no hay Logger::error en login exitoso

---

## Verificación final (obligatoria)

- [ ] `make phpstan` → 0 errores (requiere Docker)
- [ ] `make cs-check` → 0 violaciones PSR-12 (requiere Docker)
- [ ] `make test-unit` → 0 fallos (requiere Docker)
- [x] `grep -r "isOk\|isFail\|getDataOr\|getMessage\|isSuccess" app/` → 0 resultados (solo $this->getMessage en Exceptions)
- [x] `grep -r "htmlspecialchars" app/Services/` → solo EmailService (output HTML, correcto) — ReviewModerationService corregido
- [x] `grep -r "'password'" app/Services/` → solo contextos legítimos (SMTP config, validation, hash)
- [x] `grep -r "str_ends_with.*_admin" app/` → 0 resultados
- [x] `grep -rn "new Reservation\|new TimeSlot\|new Waitlist" app/Services/` → solo ReceptionService (lazy init, deuda documentada)
- [ ] `chr_lighthouse_audit` → accesibilidad ≥ 90 (requiere servidor activo)
- [x] Review manual rutas públicas API — ninguna expone `user_id` desde body

---

## Ciclo de vida del plan

Al completar cada sprint: marcar tareas `[x]` + actualizar estado en `indice-maestro.md`.
Al completar todas las fases: eliminar este archivo y marcar `✅ Completado y eliminado` en el índice.
