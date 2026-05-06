# Plan: Auditoría Pre-despliegue — 2026-05-06

## Estado: � En implementación — S4 completado, pendiente S5/S6

## Contexto

Auditoría final antes del despliegue a Railway. Cubre:

- Centralización de patrones inline repetidos (utilities)
- Datos de loyalty vacíos (catalog vacío en BD)
- 17 fallos WCAG reales en 7 archivos CSS
- Listas sin paginación (productos limit 200, reservas limit 50, reseñas sin límite)
- Staff: sin CRUD de turnos, calendario sin navegación semanal
- Consistencia `e()` vs `htmlspecialchars` en EmailService

## Orden de ejecución

**S0 → S2 → S1 → S3 → S4 → S5 → S6**

S0 primero porque las utility classes son usadas en S3, S4, S5.
S2 segundo porque desbloquea el testing del módulo loyalty antes de parchear CSS.

---

## Descartado (audit confirma que no se justifican)

| Utilidad | Motivo descarte |
|----------|----------------|
| `ValidationPatterns` | `DateString.php` + `TimeString.php` ya cubren validación completa |
| `ArrayTransformers` | Solo 1 ocurrencia real en todo el proyecto (MenuRepository:49) |
| `FieldListing` | 4 usos locales dentro de `AbstractRepository`, no se reutilizan fuera |
| `HtmlEscaping` | `e()` ya existe en `app/Core/Helpers.php`; solo EmailService no la usa → fix puntual en S6 |
| `HtmlAttributes` | 2-3 archivos, no justifica clase |
| `StringManipulation` autónoma | 2 líneas en un solo archivo (CafeRepository); `substr($time,0,5)` se añade a `TimeHelper` |

---

## Stream 0 — Utility classes (prerequisito de todo)

### 0a — Extender `app/Support/TimeHelper.php`

| Método | Firma | Descripción |
|--------|-------|-------------|
| `duration` | `(string $start, string $end): string` | '09:00','14:30' → '5h 30min' |
| `weekRange` | `(int $offset = 0): array` | `['from' => 'Y-m-d', 'to' => 'Y-m-d']` |
| `display` | `(string $time): string` | '14:30:00' → '14:30' (reemplaza `substr($time,0,5)` — 4+ archivos) |

### 0b — Crear `app/Support/StatusLabeling.php` (NUEVO)

Mapeos actualmente inline en 8+ vistas con arrays repetidos:

| Método | Descripción |
|--------|-------------|
| `reservationLabel(string $status): string` | 'pendiente' → 'Pendiente' |
| `reservationBadge(string $status): string` | → clase CSS |
| `waitlistLabel(string $status): string` | |
| `waitlistBadge(string $status): string` | |
| `reviewLabel(string $status): string` | |
| `reviewBadge(string $status): string` | |
| `animalLabel(string $status): string` | |
| `animalBadge(string $status): string` | |

### 0c — Crear `app/Support/DateFormatting.php` (NUEVO)

24 ocurrencias de `date('d/m/Y'...)` en 9 archivos app/ + 21 vistas:

| Método | Firma | Descripción |
|--------|-------|-------------|
| `toSpanishDate` | `(string $dateYmd): string` | → 'd/m/Y' |
| `toSpanishDateTime` | `(string $datetimeStr): string` | → 'd/m/Y H:i' |
| `dateAdd` | `(string $dateYmd, int $days): string` | reemplaza `date('Y-m-d', strtotime('+N days'))` |

### 0d — Crear `app/Support/CurrencyFormatting.php` (NUEVO)

43 ocurrencias de `number_format()` en 2 archivos app/ + 41 vistas:

| Método | Firma | Descripción |
|--------|-------|-------------|
| `yen` | `(int\|float $value): string` | → '¥1,234' |
| `rating` | `(float $value): string` | → '4.5' (1 decimal) |
| `percentage` | `(float $value, int $decimals = 1): string` | → '45.5%' |
| `number` | `(int\|float $value): string` | formato general sin símbolo |

### Tareas S0

- [ ] Extender `TimeHelper` con 3 métodos nuevos
- [ ] Crear `StatusLabeling.php` con 8 métodos
- [ ] Crear `DateFormatting.php` con 3 métodos
- [ ] Crear `CurrencyFormatting.php` con 4 métodos
- [ ] PHPStan nivel 5 sin errores en las 4 clases

---

## Stream 2 — Loyalty data

### Contexto

`getAvailableRewards()` devuelve lista vacía porque `loyalty_reward_catalog` no tiene filas en BD
(los INSERT están en migración 013 pero no aplicados).

### Tareas S2

- [ ] Verificar: `SELECT COUNT(*) FROM loyalty_reward_catalog`
- [ ] Crear `migrations/028_seed_loyalty_catalog.sql` con `INSERT IGNORE` para 5 rewards estándar
- [ ] `LoyaltyService::getAvailableRewards()`: coerce `null` tier → `'bronze'` para usuarios nuevos

---

## Stream 1 — WCAG: 17 fallos en 7 archivos CSS

### Principio

Solo corregir contraste insuficiente. No rediseñar. Usar design tokens existentes.
Todos los fixes aplican el patrón: fondo sólido o par de colores con ratio ≥ 4.5:1.

### Tareas S1

- [ ] `public/css/sections/loyalty.css`
  - `.stat-card`: fondo `rgba(255,255,255,0.15)` + texto blanco = 1.05:1 → fondo sólido con `--color-primary-*`
  - `.status-badge--waiting`: `color-mix` insuficiente → par de colores sólidos design tokens
- [ ] `public/css/sections/profile.css`
  - `.status-pill`: fondo `rgba(0,0,0,0.03)` = ~1.5:1 → fondo sólido
  - `.status-pill--pending`: `color-mix(...15%)` = 2.8:1 → par sólido
- [ ] `public/css/sections/home.css`
  - `.badge`: fondo `rgba(255,255,255,0.15)` + texto blanco = 1.08:1 → gradient más oscuro o fondo sólido
- [ ] `public/css/sections/manager/cafe-management.css`
  - Añadir `dd { color: var(--text-primary, #2D1F13); }` — Bootstrap override que aplica `#0d6efd` azul
- [ ] `public/css/components/buttons.css`
  - `.btn-komorebi-secondary` hover `rgba(92,61,46,0.06)` = ~3.2:1 → opacity 0.06→0.12; active → 0.18
- [ ] `public/css/backoffice-modern.css`
  - `--sidebar-hover: rgba(201,168,118,0.15)` = 1.3:1 → opacity 0.15→0.45
  - `--sidebar-active: rgba(255,179,71,0.25)` = 1.8:1 → opacity 0.25→0.45
- [ ] `public/css/workspaces/reception.css`
  - `.brand-icon` fondo `rgba(135,167,123,0.2)` = 2.1:1 → opacity 0.20→0.35
- [ ] Lighthouse accessibility ≥ 90 en /loyalty/card, /user/profile, /manager/cafe

---

## Stream 3 — Paginación + filtros + sort (módulo manager)

Patrón: `PaginationParams::fromRequest()` + sentinel row + `ViewHelpers::sortLink/paginationLinks()`.
Replicar exactamente el patrón ya implementado en el módulo admin.

### 3a — Productos (actualmente hardcoded limit 200)

- [ ] `ProductController::index()` → `PaginationParams::fromRequest($request)`, límite 20, filtros
- [ ] `ProductRepository` o `findFiltered()` → añadir `$page`, offset, sentinel
- [ ] Vista `resources/views/manager/products/index.php` → sortLink + selects filtros + paginationLinks
- [ ] Usar `DateFormatting` / `CurrencyFormatting` al tocar la vista

### 3b — Reservas (actualmente limit 50 sin offset)

- [ ] `ReservationRepository::findByCafeWithFilters()` → añadir `$page`, offset, sentinel row
- [ ] Actualizar `ReservationRepositoryInterface` con nueva firma
- [ ] `ReservationController::index()` → `PaginationParams`, `$page`
- [ ] Vista `resources/views/manager/reservations/index.php` → sortLink + paginationLinks + `StatusLabeling::reservationLabel/Badge()`
- [ ] Test unitario para `ReservationRepository` con paginación

### 3c — Reseñas (sin paginación, solo 'approved')

- [ ] Nuevo método `ReviewQueryServiceInterface::listForManager(int $cafeId, ?string $status, int $page): array`
- [ ] Implementar en `ReviewQueryService` → nuevo `ReviewRepository::findByCafeAllStatusesPaginated()`
- [ ] `ReviewController::index()` → `PaginationParams`, filtro `$status` (todos los estados)
- [ ] Vista `resources/views/manager/reviews/index.php` → filtros + sortLink + paginationLinks + `StatusLabeling::reviewLabel/Badge()`

---

## Stream 4 — Staff: CRUD turnos + calendario navegable

### 4a — Backend CRUD

- [ ] `StaffShiftRepository`: añadir `update(int $id, array $data): bool` + `delete(int $id): bool`
- [ ] `StaffShiftService`: añadir `updateShift(int $id, array $data, int $cafeId): Result` + `deleteShift(int $id, int $cafeId): Result` con verificación de ownership
- [ ] `StaffController`: métodos `updateShift()` y `deleteShift()`
- [ ] `app/routes.php`: `PUT /api/v1/manager/staff/shifts/{id}` + `DELETE /api/v1/manager/staff/shifts/{id}` con `$mw->csrf()`

### 4b — Calendario navegable por semana

- [ ] `StaffShiftService::getWeekShifts($cafeId, int $weekOffset = 0)` usa `TimeHelper::weekRange($offset)` en lugar de `date(...strtotime('+7 days'))`
- [ ] `StaffController::index()`: leer `?week_offset=N` del request
- [ ] Vista: botones `< Semana anterior` | `Esta semana` | `Semana siguiente >` con Alpine + fetch

### 4c — Mejoras visuales

- [ ] Fix `<output x-show="...">` sin `x-cloak` → añadir `x-cloak` (elimina el "□" ghost)
- [ ] Mostrar duración de turno via `TimeHelper::duration()` calculada en PHP
- [ ] Colorear día del calendario: `.calendar-day--empty` / `--low` / `--busy` según conteo de turnos
- [ ] CSS: `repeat(7, 1fr)` → `repeat(auto-fill, minmax(180px, 1fr))`
- [ ] `shift-item { background: #cfe2ff }` → brand token `var(--color-primary-100)`

### 4d — Modal dual assign/edit

- [ ] Alpine: `shiftToEdit: null`, `openEdit(shift)` rellena el form + cambia endpoint a PUT
- [ ] Botón "Editar" en tabla de turnos + en items del calendario
- [ ] Botón "Eliminar" con `confirm()` inline Alpine

---

## Stream 5 — Aplicar utilities de forma sistemática

Una vez creadas las clases en S0, reemplazar todos los patrones inline.

### 5a — DateFormatting (24 archivos afectados)

Archivos `app/`:

- [ ] `app/Services/EmailService.php` → `DateFormatting::toSpanishDateTime()`
- [ ] `app/Services/InvoicePDFService.php` → `DateFormatting::toSpanishDate()`
- [ ] `app/Services/LoyaltyService.php` → `DateFormatting::toSpanishDate()`
- [ ] `app/Http/Controllers/Admin/ReportController.php` → `DateFormatting::dateAdd()`

Vistas (21 archivos — orden por impacto):

- [ ] `resources/views/manager/staff/show.php` (4 ocurrencias)
- [ ] `resources/views/public/waitlist-status.php` (3 ocurrencias)
- [ ] `resources/views/admin/data-viewer.php` (3 ocurrencias)
- [ ] `resources/views/shared/reservas/lista.php`, `paso-3.php`, `confirmation.php`
- [ ] Resto de vistas con `date('d/m/Y'...)`

### 5b — CurrencyFormatting (43 archivos afectados)

Archivos `app/`:

- [ ] `app/Http/Controllers/Manager/ReportController.php` → `CurrencyFormatting::yen()` etc.
- [ ] `app/Http/Controllers/Admin/HomeController.php` → `CurrencyFormatting::yen()`

Vistas (41 archivos — orden por impacto):

- [ ] `resources/views/shared/reservas/paso-3.php` (3 ocurrencias)
- [ ] `resources/views/admin/home.php` (3 ocurrencias)
- [ ] `resources/views/reception/index.php`
- [ ] Resto de vistas con `number_format(`

### 5c — StatusLabeling en vistas no tocadas en S3/S4

- [ ] `resources/views/shared/reservas/lista.php`
- [ ] `resources/views/user/waitlists.php`
- [ ] `resources/views/public/waitlist-status.php`
- [ ] `resources/views/admin/home.php`
- [ ] `resources/views/backoffice/keeper/animals/show.php`

### 5d — TimeHelper::display() en archivos no tocados en S4

- [ ] `resources/views/manager/staff/show.php` → `substr($time,0,5)` → `TimeHelper::display()`
- [ ] `app/Services/InvoicePDFService.php` → `TimeHelper::display()`
- [ ] `app/Http/Controllers/Supervisor/SupervisorController.php` → `TimeHelper::display()`

---

## Stream 6 — Consistencia EmailService

- [ ] `app/Services/EmailService.php`: reemplazar todas las llamadas a `\htmlspecialchars($var, ENT_QUOTES, 'UTF-8')` por `e($var)` (global ya disponible en `app/Core/Helpers.php`)

---

## Mapa de archivos completo

### S0 — Utilities nuevas/extendidas

| Archivo | Acción |
|---------|--------|
| `app/Support/TimeHelper.php` | Extender (+3 métodos) |
| `app/Support/StatusLabeling.php` | CREAR |
| `app/Support/DateFormatting.php` | CREAR |
| `app/Support/CurrencyFormatting.php` | CREAR |

### S2 — Loyalty data

| Archivo | Acción |
|---------|--------|
| `migrations/028_seed_loyalty_catalog.sql` | CREAR |
| `app/Services/LoyaltyService.php` | Modificar (null coerce) |

### S1 — CSS WCAG

| Archivo | Acción |
|---------|--------|
| `public/css/sections/loyalty.css` | Fix contraste |
| `public/css/sections/profile.css` | Fix contraste |
| `public/css/sections/home.css` | Fix contraste |
| `public/css/sections/manager/cafe-management.css` | Añadir dd override |
| `public/css/components/buttons.css` | Fix contraste hover |
| `public/css/backoffice-modern.css` | Fix contraste sidebar |
| `public/css/workspaces/reception.css` | Fix contraste brand-icon |

### S3 — Paginación

| Archivo | Acción |
|---------|--------|
| `app/Http/Controllers/Manager/ProductController.php` | Paginación |
| `app/Http/Controllers/Manager/ReservationController.php` | Paginación |
| `app/Repositories/ReservationRepository.php` | +page+offset |
| `app/Repositories/Contracts/ReservationRepositoryInterface.php` | Actualizar firma |
| `app/Http/Controllers/Manager/ReviewController.php` | Paginación + status filter |
| `app/Services/Contracts/ReviewQueryServiceInterface.php` | +listForManager() |
| `app/Services/ReviewQueryService.php` | Implementar |
| `app/Repositories/ReviewRepository.php` | +findByCafeAllStatusesPaginated() |
| 3 vistas manager (products, reservations, reviews) | sortLink + paginationLinks |

### S4 — Staff CRUD + calendario

| Archivo | Acción |
|---------|--------|
| `app/Repositories/StaffShiftRepository.php` | +update() +delete() |
| `app/Services/StaffShiftService.php` | +updateShift() +deleteShift() +weekOffset |
| `app/Http/Controllers/Manager/StaffController.php` | +updateShift() +deleteShift() |
| `app/routes.php` | +PUT +DELETE shifts |
| `resources/views/manager/staff/index.php` | x-cloak, duración, semana nav, modal dual |
| `public/js/sections/manager/manager-staff.js` (o Alpine inline) | openEdit, semana nav |
| `public/css/sections/manager/staff.css` | brand tokens, calendar grid |

### S5 — Apply utilities (~70+ archivos)

- DateFormatting: 24 archivos (4 app/ + 21 vistas)
- CurrencyFormatting: 43 archivos (2 app/ + 41 vistas)
- StatusLabeling: 5 vistas adicionales
- TimeHelper::display(): 3 archivos

### S6 — EmailService

| Archivo | Acción |
|---------|--------|
| `app/Services/EmailService.php` | `htmlspecialchars` → `e()` |

---

## Criterios de verificación por stream

| Stream | Comando / evidencia |
|--------|---------------------|
| S0 | `make phpstan` → 0 errores en `app/Support/` |
| S2 | `SELECT COUNT(*) FROM loyalty_reward_catalog` = 5; UI muestra rewards |
| S1 | Lighthouse accessibility ≥ 90 en /loyalty/card, /user/profile, /manager/cafe |
| S3 | Productos 20/página; URL preserva filtros; sort funcional en las 3 listas |
| S4 | CRUD sin reload; "□" eliminado; calendario navega semanas |
| S5 | `grep -r "date('d/m/Y'" resources/views` = 0; `grep -r "number_format(" resources/views` = 0 |
| S6 | `grep -n "htmlspecialchars" app/Services/EmailService.php` = 0 |
| Global | `make test` verde; `make phpstan` 0 errores; `make cs-check` limpio |
