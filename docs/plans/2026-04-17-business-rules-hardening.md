# Plan: Business Rules Hardening + Zero Legacy/Alias + Q Decisions

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development to implement this plan
> sprint-by-sprint. Steps use checkbox (`- [ ]`) syntax for tracking.
> **Execution choice (confirmed):** ✅ **Subagent-Driven** — subagente fresco por sprint + revisión entre sprints.

**Fecha:** 17 de abril de 2026
**Estado:** 🟡 En implementación
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
    - `$result->isOk()` → `$result->ok`
    - `$result->isFail()` → `$result->error !== null`
    - `$result->getDataOr($x)` → `$result->data ?? $x`
    - `$result->getMessage()` / `$result->getMessage('fb')` → `$result->error ?? 'fb'`
    - `$result->isSuccess()` → `$result->ok`
- [x] Verificar: `grep -r "isOk\|isFail\|getDataOr\|getMessage\|isSuccess" app/` → 0 resultados
- [x] Archivos conocidos: `AnimalCareService.php` (L234, L386), `StaffShiftService.php`,
  `Api\ReservationController.php`, `ReservationService.php` (comentario L86)

### S0-02 — Eliminar fallback `password_hash ?? password` (B-01)

- [x] Verificar columna real: `DESCRIBE users` → confirmar `password` (migración 002)
- [x] Corregir `$user['password_hash']` → `$user['password']` en `UserAccountService.php:53`
- [x] Auditar: `grep -r "'password'" app/Services/` → solo accesos correctos a columna `password`

### S0-03 — Inyección de repositorios en WaitlistService y ReservationTimeSlotService

- [ ] Inyectar `ReservationRepository`, `TimeSlotRepository`, `WaitlistRepository` vía constructor
- [ ] Eliminar `new Reservation($db)`, `new TimeSlot($db)`, `new Waitlist($db)` directos en servicios
- [ ] Actualizar registros en `bootstrap/container.php`
- [ ] Verificar: `grep -rn "new Reservation\|new TimeSlot\|new Waitlist" app/Services/` → 0

### S0-04 — Eliminar comentarios NOTE con APIs obsoletas

- [x] Eliminar/actualizar comentario `NOTE: $result->isSuccess()` en `ReservationService.php:86`
- [x] Confirmar que `Api\ReservationController` ya usa `$result->ok`

### S0-05 — Unificar columna de roles: slug vs code (Q-10)

- [ ] `DESCRIBE roles` → confirmar nombre de columna canónico
- [ ] `grep -r "'code'" app/` → identificar usos incorrectos
- [ ] Unificar a `slug` en todos los accesos, incluido `UserProfileService.php:64`

### S0-06 — `#[\Override]` faltante en StaffShiftService

- [x] Añadir `#[\Override]` a todos los métodos que implementan la interfaz
- [x] Verificar: `make phpstan` → 0 errores

---

## Sprint 1 — Seguridad HTTP y RBAC (P1 Crítico)

### S1-01 — RBAC bypass: `str_ends_with($role, '_admin')` (R-01)

- [ ] Reemplazar por `in_array($role, ['admin', 'manager', 'supervisor', 'reception', 'kitchen', 'keeper', 'user'])`
- [ ] Test: rol `evil_admin` NO pasa ningún guard

### S1-02 — IDOR en Waitlist: `user_id` desde body (I-01)

- [ ] Eliminar `$data['user_id']` del request body en `POST /api/v1/waitlist/join`
- [ ] `user_id` exclusivamente desde sesión autenticada

### S1-03 — Open Redirect: `HTTP_REFERER` sin validar (V-01)

- [ ] Crear `UrlValidator::isSafeInternal(string $url): bool`
- [ ] Aplicar en `ExceptionHandler.php` y `View::back()`; fallback a `/`

### S1-04 — Rate limiting no aplica en CLI (A-01)

- [ ] Revisar si bypass CLI en `AuthService.php:349-351` es intencional
- [ ] Si no intencional: eliminar; si intencional: test + comentario explicativo

### S1-05 — Logger con datos sensibles (L-01)

- [ ] Eliminar roles/user data de logs `error` en `AuthService.php:202-218`
- [ ] Verificar que ningún `Logger::error/warning` loguea contraseñas, tokens o sesiones

### S1-06 — Auditar cobertura CSRF en rutas

- [ ] Todas las rutas POST/PUT/PATCH/DELETE no-API deben tener `$mw->csrf()`
- [ ] Documentar rutas API exentas en `docs/ARCHITECTURE.md`

---

## Sprint 2 — Validación de entrada (P1 Crítico)

### S2-01 — `strlen` → `mb_strlen` en ReviewService (V-02)

- [ ] `ReviewService.php:71,75,149,153` → `mb_strlen()`
- [ ] Test: texto japonés/emoji no falla la validación de longitud

### S2-02 — Eliminar `htmlspecialchars` antes de persistir (I-02)

- [ ] Eliminar `htmlspecialchars()` en `createReview()` y `updateReview()` antes de INSERT/UPDATE
- [ ] Test: `<script>` en reseña → guardado en DB sin escapar, escapado en la vista

### S2-03 — Validación de rango en WaitlistService (V-03)

- [ ] `$data['guest_count']` en rango 1–10; rechazar 0, negativos y superiores al máximo

### S2-04 — Validación de fechas reales (V-04)

- [ ] `ReservationService.php:306-323` → `DateTimeImmutable::createFromFormat()` + no pasado + respetar
  `RESERVATION_MAX_DAYS_AHEAD`

### S2-05 — `validateRequired` acepta `0` como vacío (V-05)

- [ ] Separar "presente" (`isset`) de "no vacío string" (`!== ''`)

### S2-06 — Rango de edad de animales (V-06)

- [ ] `AnimalCareService.php:78` → `age_years` en rango 0–50

### S2-07 — Complejidad de contraseña (Q-03)

- [ ] Validar en registro y cambio de contraseña: min 8 chars, 1 mayúscula, 1 número
- [ ] Test: contraseñas débiles rechazadas con mensaje claro

---

## Sprint 3 — Reglas de dominio (P1/P2)

### S3-01 — Una reseña por usuario por café (Q-01)

- [ ] Guard en `createReview()` si ya existe reseña del mismo `user_id` y `cafe_id`
- [ ] Migración `020_review_unique_constraint.sql` con `UNIQUE(user_id, cafe_id)`

### S3-02 — Email verificado antes del primer acceso (Q-02)

- [ ] Guard en login: `email_verified_at IS NOT NULL`; controlable por `EMAIL_VERIFICATION_REQUIRED` env
- [ ] Test: login sin email verificado → error `email_not_verified`

### S3-03 — `deleteReview` separar admin/owner (Q-13)

- [ ] `deleteReview(int $reviewId, int $userId): Result` (nunca nullable)
- [ ] `deleteReviewAdmin(int $reviewId): Result` con log de auditoría

### S3-04 — Revertir sello al cancelar reserva (Q-05)

- [ ] `ReservationService::cancel()` llama a `LoyaltyService::reverseStamp(int $reservationId)` en la misma transacción
- [ ] Test: cancelar reserva con sello → sello desaparece del historial

### S3-05 — `addStamp` guard para valores ≤ 0 (D-01)

- [ ] Guard: `$stamps <= 0` → `Result::fail('El número de sellos debe ser positivo', 'invalid_stamps')`

### S3-06 — Race condition en `redeemReward` (R-02)

- [ ] Envolver en transacción con `SELECT ... FOR UPDATE` en la fila del usuario

### S3-07 — `cancel()` debe devolver `Result` (A-02)

- [ ] Cambiar retorno `bool` → `Result` en `ReservationService::cancel()`; actualizar controllers

### S3-08 — Turnos de staff no cruzan medianoche (Q-06)

- [ ] Validación `$start < $end` en `StaffShiftService`

### S3-09 — Animal sin `cafe_id` solo si `quarantine` (Q-07)

- [ ] Guard en `create()` y `update()` de `AnimalCareService`

### S3-10 — Newsletter: `subscribe()` devuelve `Result` (Q-12)

- [ ] Cambiar retorno de `array` a `Result`; actualizar controllers

### S3-11 — Token de confirmación newsletter con `expires_at` (D-02)

- [ ] Al generar token: `expires_at = now() + 48h`; verificar en `confirmSubscription()`

### S3-12 — `getConfirmedEmails()` sin LIMIT (D-03)

- [ ] Añadir paginación o LIMIT 500

### S3-13 — Cupón `WELCOME5` hardcoded (D-04)

- [ ] Mover a `NEWSLETTER_WELCOME_COUPON` env var o eliminar si no hay sistema de cupones real

### S3-14 — `CartService` devuelve `Result` (Q-11)

- [ ] Cambiar métodos que devuelven `array` → `Result`; actualizar controllers

---

## Sprint 4 — Arquitectura y consistencia (P2)

### S4-01 — SQL raw en servicios → repositorios (A-03)

- [ ] `AnimalCareService.php` (INSERT INTO animals)
- [ ] `ApiTokenService.php`, `AccountDeletionService.php`, `UserManagementService.php`
- [ ] `AuthService.php:238` (UPDATE users SET updated_at)
- [ ] `ProductService.php`, `AvailabilityService.php` (queries privadas)

### S4-02 — Lazy init en LoyaltyService → inyección (A-04)

- [ ] Eliminar propiedades `??=`; inyectar por constructor; registrar en container

### S4-03 — Tier thresholds en constantes (A-05)

- [ ] Extraer `10, 30, 50` a constantes de clase; unificar `$tierOrder`

### S4-04 — `health_status` vs `current_status` (A-06)

- [ ] `DESCRIBE animal_health_checks` → confirmar columna real; unificar referencias

### S4-05 — Guard `validity_days = 0` en LoyaltyService (D-05)

- [ ] Si `validity_days <= 0` → usar default 365 o error de configuración

---

## Sprint 5 — P3 y limpieza final

### S5-01 — `Logger::debug` sin guard en ReservationService (L-02)

- [ ] Eliminar o convertir a `Logger::debug` los logs de configuración estructural

### S5-02 — Eliminar ruta histórica alias en routes.php (L-04)

- [ ] Identificar ruta `// Ruta alternativa histórica`
- [ ] Sin tests ni docs → eliminar; con usuarios → redirect 301

### S5-03 — Endpoint público waitlist: validar email de contacto

- [ ] Para usuarios no autenticados en `/api/v1/waitlist/*`: requerir email válido con `filter_var`

### S5-04 — `Logger::error` nivel incorrecto en AuthService (L-03)

- [ ] Logs de login exitoso → `Logger::info`, no `Logger::error`

---

## Verificación final (obligatoria)

- [ ] `make phpstan` → 0 errores
- [ ] `make cs-check` → 0 violaciones PSR-12
- [ ] `make test-unit` → 0 fallos
- [ ] `grep -r "isOk\|isFail\|getDataOr\|getMessage\|isSuccess" app/` → 0 resultados
- [ ] `grep -r "htmlspecialchars" app/Services/` → 0 resultados
- [ ] `grep -r "'password'" app/Services/` → solo `password_hash`
- [ ] `grep -r "str_ends_with.*_admin" app/` → 0 resultados
- [ ] `grep -rn "new Reservation\|new TimeSlot\|new Waitlist" app/Services/` → 0 resultados
- [ ] `chr_lighthouse_audit` → accesibilidad ≥ 90
- [ ] Review manual rutas públicas API — ninguna expone `user_id` desde body

---

## Ciclo de vida del plan

Al completar cada sprint: marcar tareas `[x]` + actualizar estado en `indice-maestro.md`.
Al completar todas las fases: eliminar este archivo y marcar `✅ Completado y eliminado` en el índice.
