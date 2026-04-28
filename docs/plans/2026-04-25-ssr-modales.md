# Plan: Migración SSR con Modales — Hypermedia-Driven Application

**Fecha:** 25 de abril de 2026
**Estado:** 🔵 Plan creado — pendiente inicio
**Prioridad:** P5-arquitectura (prerrequisito de una defensa TFG sólida)
**Estimación total:** ~48h
**Absorbe:** `docs/plans/2026-04-24-api-audit-bugfix.md` Bloques A, B, C → Fase 0

---

## Objetivo

Migrar de Alpine.js como capa de gestión de datos a un modelo **Hypermedia-Driven Application (HDA)**:
PHP renderiza todo el HTML con datos del servidor; Alpine.js queda exclusivamente como capa de
_progressive enhancement_ (modales, debounce de búsqueda, feedback inline de mutaciones).

### Patrón formal

**Hypermedia-Driven Application** — Fielding (tesis 2000, restricción HATEOAS) +
Carson Gross (htmx essays 2022). Implementación operacional: GitHub, Basecamp, GitLab.

### Los 5 invariantes del patrón (comprobables en código)

1. **PHP es la única fuente de verdad de datos de página** — ningún `GET` devuelve JSON para datos que el cliente renderizará como HTML.
2. **La URL es el estado de la aplicación** — filtros, ordenamiento y paginación son query params; cualquier estado es bookmarkable.
3. **Alpine.js = comportamiento de UI, no gestión de datos** — nunca almacena colecciones de negocio.
4. **La API REST existe solo para mutaciones** — su contrato con el cliente es `{ok: bool, data/errors}` para feedback inline en modales.
5. **AJAX solo para queries reactivas a input del usuario** — `GET /api/v1/time-slots/available?date=&cafe_id=` cuando el usuario cambia la fecha es legítimo; `fetch('/api/v1/cafes')` en `init()` no lo es.

---

## Contexto y estado de partida

| Área | Estado actual | ¿Necesita migración? |
|---|---|---|
| KDS (`kds.js`) | `refresh() { window.location.reload() }` — ya es HDA | ❌ No |
| Recepción | Mercure SSE → `location.reload()` — ya es HDA | ❌ No |
| Keeper | Vistas PHP estáticas SSR | ❌ No |
| Páginas públicas (menú, catálogo) | Read-only SSR | ❌ No |
| Admin CRUD (x8 paneles) | Alpine gestiona filter/sort/paginate | ✅ Sí |
| Manager (x4 vistas) | Alpine gestiona tablas + fetch con URLs incorrectas | ✅ Sí |
| Wizard de reservas | Alpine fetches cafés/pases en `init()` + multi-step | ✅ Sí |

---

## Fase 0 — Bug Fixes (prerrequisito) ~5h

_F0.1 y F0.2 pueden ejecutarse en paralelo. F0.3 es independiente._

### F0.1 — Admin roles: rutas `/api/v1/admin/roles/*` ausentes ~2h

**Problema:** `admin-roles.js` hace fetch a `/admin/roles/*` — rutas no registradas en `routes.php`.

**Tareas:**

- [ ] **F0.1a** `app/routes.php`: registrar `POST /api/v1/admin/roles`, `PUT /api/v1/admin/roles/{id}`,
  `DELETE /api/v1/admin/roles/{id}`, `GET /api/v1/admin/roles/{id}/permissions`
- [ ] **F0.1b** `public/js/sections/admin/admin-roles.js`: todos los fetch `/admin/roles/*`
  → `/api/v1/admin/roles/*`
- [ ] **F0.1c** Verificar que `Admin\RoleController` devuelve `{ok: bool, data: {}}`, no `{success: bool}`

### F0.2 — Manager: corregir 8 fetch con URLs incorrectas ~2h

**Problema:** vistas manager usan rutas `/manager/*` sin prefijo `/api/v1/` y métodos HTTP incorrectos.

**Mapa de correcciones:**

| Vista | URL actual | URL correcta | Método actual | Método correcto |
|---|---|---|---|---|
| `manager/cafe/show.php` L86 | `/manager/cafe/schedule` | `/api/v1/manager/cafe/schedule` | POST | PUT |
| `manager/cafe/show.php` L137 | `/manager/cafe/capacity` | `/api/v1/manager/cafe/capacity` | POST | PUT |
| `manager/cafe/show.php` L181 | `/manager/cafe/settings` | `/api/v1/manager/cafe/settings` | POST | PUT |
| `manager/products/index.php` (create) | `/manager/products/create` | `/api/v1/manager/products` | POST | POST ✅ |
| `manager/products/index.php` (update) | `/manager/products/{id}/update` | `/api/v1/manager/products/{id}` | POST | PUT |
| `manager/products/index.php` (toggle) | `/manager/products/{id}/toggle` | `/api/v1/manager/products/{id}/toggle` | POST | PATCH |
| `manager/products/index.php` (delete) | `/manager/products/{id}/delete` | `/api/v1/manager/products/{id}` | POST | DELETE |
| `manager/staff/index.php` L207 | `/manager/staff/assign-shift` | `/api/v1/manager/staff/assign-shift` | POST | POST ✅ |
| `manager/staff/show.php` L53 | `/manager/staff/performance/{id}` | Sin ruta API — ver nota | GET | — |

> **Nota `manager/staff/show.php` L53:** decidir si registrar `GET /api/v1/manager/staff/performance/{id}` en `routes.php` (+ método en `Manager\StaffController`) o eliminar el fetch si no se necesita en la defensa.

**Tareas:**

- [ ] **F0.2a** `resources/views/manager/cafe/show.php` — corregir 3 fetch (L86, L137, L181)
- [ ] **F0.2b** `resources/views/manager/products/index.php` — corregir 4 fetch
- [ ] **F0.2c** `resources/views/manager/staff/index.php` L207 — corregir URL
- [ ] **F0.2d** `resources/views/manager/staff/show.php` L53 — decidir y actuar

### F0.3 — MenuRepository: columnas faltantes en SELECT ~1h

**Problema:** `MenuRepository::getProductsByCategory()` no incluye `target_cafe_types` ni `target_animal_types` en el SELECT.

**Tareas:**

- [ ] **F0.3a** `app/Repositories/MenuRepository.php` → `getProductsByCategory()`: añadir
  `target_cafe_types`, `target_animal_types` al array de campos

---

## Fase 1 — Infraestructura server-side filtering ~4h

_Prerrequisito de todas las fases siguientes. Completar antes de Fase 2._

### F1.1 — Verificar y extender `findPaginated()` (AbstractRepository.php L217) ~1.5h

- [ ] **F1.1a** Leer implementación actual: ¿soporta búsqueda `LIKE`, sort column/dir validados contra whitelist?
- [ ] **F1.1b** Si `conditions` es solo `WHERE` exacto: extender para aceptar `SearchParams` con búsqueda fuzzy y columna de ordenamiento
- [ ] **F1.1c** Garantizar que la columna de sort se valida contra una whitelist (previene SQL injection)

### F1.2 — `PaginationParams` DTO ~1.5h

- [ ] **F1.2a** Verificar si existe `PaginationParams` o similar en `app/Core/` o `app/Domain/`
- [ ] **F1.2b** Si no existe: crear `app/Domain/DTO/PaginationParams.php` como `final readonly class`
  con `fromRequest(ServerRequestInterface $request): self` — extrae y valida `search`, `status`,
  filtros específicos, `sort`, `dir`, `page` con defaults seguros

### F1.3 — View helpers para sort links y paginación ~1h

- [ ] **F1.3a** En `app/Support/` (o `app/Core/`): crear `sortLink(string $label, string $field, array $currentParams): string`
  — genera `<a href="?sort=field&dir=asc&search=foo">` con flecha de dirección
- [ ] **F1.3b** Crear `paginationLinks(array $meta, array $currentParams): string`
  — genera links de página preservando todos los query params actuales

---

## Fase 2 — Admin Users: prototipo del patrón ~8h

_Primera migración completa. Valida el patrón antes de replicarlo en Fase 3._

### F2.1 — `Admin\UserController@index()` con query params ~1.5h

- [ ] **F2.1a** Cambiar firma: `index(ServerRequestInterface $request): ?ResponseInterface`
- [ ] **F2.1b** Extraer de `$request->getQueryParams()`: `search`, `status`, `role`, `sort`, `dir`, `page`
- [ ] **F2.1c** Whitelist de sort: `['id', 'name', 'email', 'is_active', 'created_at']`
- [ ] **F2.1d** Usar `findPaginated()` (F1.1) en lugar de `getUsersWithRoles()`
- [ ] **F2.1e** Pasar `$meta` (total, currentPage, totalPages) y params actuales a la vista

### F2.2 — `_filters.php` → HTML `<form method="GET">` ~1.5h

- [ ] **F2.2a** Eliminar `x-model`, `@click`, Alpine bindings de filtros
- [ ] **F2.2b** `<form method="GET" action="/admin/users">` con `<input name="search">`,
  `<select name="status">`, `<select name="role">` — valores PHP-rendered
- [ ] **F2.2c** Mantener Alpine mínimo: `@input.debounce.500ms="$el.form.submit()"` solo en búsqueda
- [ ] **F2.2d** Botón "Limpiar" → `<a href="/admin/users">` (cero JavaScript)

### F2.3 — `_user-table.php` → PHP `foreach` + sort links ~2h

- [ ] **F2.3a** Cabeceras `<th>`: eliminar `@click="sortBy('id')"`, reemplazar con links del helper F1.3
- [ ] **F2.3b** Cuerpo `<tbody>`: eliminar `<template x-for>`, añadir `<?php foreach ($users as $user): ?>`
- [ ] **F2.3c** Botón Editar: `@click="openEditModal(<?= htmlspecialchars(json_encode([...])) ?>)"`
- [ ] **F2.3d** Paginación: HTML generado por helper F1.3 con query params actuales preservados

### F2.4 — `admin-users.js` → 400 → ~100 líneas ~2h

**ELIMINAR del componente Alpine:**
`users`, `filteredUsers`, `sortedUsers`, `paginatedUsers`, `filterStatus`, `filterRole`, `searchQuery`,
`currentPage`, `sortField`, `sortDir`, `perPage`, `sortBy()`, `getSortIcon()`, `isSortedBy()`,
toda la lógica de filtrado / ordenamiento / paginación

**MANTENER:**
`roles`, `csrfToken`, `isEditMode`, `isSubmitting`, `form`, `formErrors`, `modalInstance`,
`openCreateModal()`, `openEditModal(userData)`, `closeModal()`, `resetForm()`,
`submitUser()`, `toggleUserStatus()`, `deleteUser()`

**Patrón de mutación tras esta fase:**

```javascript
async submitUser() {
    const res = await fetch(/* ... */);
    const data = await res.json();
    if (data.ok) { this.modalInstance?.hide(); window.location.reload(); }
    else { this.formErrors = data.errors ?? [data.detail]; }
}
```

- [ ] **F2.4a** Eliminar propiedades y métodos listados arriba
- [ ] **F2.4b** Actualizar mutaciones para hacer `window.location.reload()` tras `data.ok`

### F2.5 — Verificación del prototipo ~1h

- [ ] **F2.5a** `GET /admin/users?search=test&status=active&sort=name&dir=asc&page=1` → tabla filtrada/ordenada
- [ ] **F2.5b** Crear usuario → modal → éxito → reload → fila nueva en tabla
- [ ] **F2.5c** Ordenar por columna → URL cambia → PHP devuelve tabla reordenada → filtros activos preservados
- [ ] **F2.5d** Bookmarkear URL filtrada → recargar → mismo estado visual

---

## Fase 3 — Admin CRUD panels: replicar patrón ~14h

_Dependen de Fase 2 (patrón validado). Los paneles pueden ejecutarse en paralelo._

**Patrón por panel:**

1. `Controller@index(ServerRequestInterface $request)` acepta query params
2. `_filters.php` → `<form method="GET">` con valores PHP-rendered
3. tabla → PHP `foreach` + sort links del helper
4. JS → solo modal UX + `window.location.reload()` tras mutación

### F3.1 — Admin Cafés (`cafes/`, `admin-cafes.js`) ~2h

- [ ] `Admin\CafeController@index()` con query params (search, status, sort, page)
- [ ] Filtros → `<form method="GET" action="/admin/cafes">`
- [ ] Tabla → PHP `foreach`
- [ ] `admin-cafes.js` → solo modal UX

### F3.2 — Admin Productos (`products/`, `admin-products.js`) ~3h

- [ ] `Admin\ProductController@index()` con query params
- [ ] Filtros → `<form method="GET">`
- [ ] Tabla → PHP `foreach`
- [ ] `admin-products.js` → solo modal UX
- [ ] _Nota: 4 form-partials complejos (form-basic-info, form-allergens, form-image, form-details) — se mantienen como están_

### F3.3 — Admin Reservas (`reservations/`, `admin-reservations.js`) ~3h

- [ ] `Admin\ReservationController@index()` con query params (search, status, date_from, date_to, sort, page)
- [ ] Filtros de rango de fechas en query params (`<input type="date" name="date_from">`)
- [ ] Tabla → PHP `foreach`
- [ ] `admin-reservations.js` → solo modal UX

### F3.4 — Admin Reseñas (`reviews/`, `admin-reviews.js`) ~2h

- [ ] `Admin\ReviewController@index()` con query params
- [ ] Filtros → `<form method="GET">`
- [ ] Tabla → PHP `foreach`
- [ ] `admin-reviews.js` → solo modal UX

### F3.5 — Admin Roles (`roles/`, `admin-roles.js`) ~2h

- [ ] URLs ya corregidas en F0.1
- [ ] `Admin\RoleController@index()` con query params
- [ ] Tabla → PHP `foreach`
- [ ] `admin-roles.js` → solo modal UX + matriz de permisos (se mantiene Alpine por complejidad)

### F3.6 — Admin Waitlist (`waitlist/`) ~2h

- [ ] Verificar primero si tiene Alpine data management antes de asumir alcance
- [ ] Si tiene: aplicar el mismo patrón (filtros → GET, tabla → foreach, JS → solo UX)

---

## Fase 4 — Manager panels ~5h

_Dependen de F0.2 (URLs corregidas). Pueden ejecutarse en paralelo con F3._

### F4.1 — Manager Productos (`manager/products/index.php`) ~1.5h

- [ ] `Manager\ProductController@index()` con query params
- [ ] Filtros → `<form method="GET">`
- [ ] Tabla → PHP `foreach`

### F4.2 — Manager Café (`manager/cafe/show.php`) ~1h

- [ ] URLs ya corregidas en F0.2
- [ ] Vista de configuración (no tabla) — verificar ausencia de `init()` fetches de datos de página
- [ ] Si hay fetch de datos iniciales: inyectar desde controller

### F4.3 — Manager Reservas (`manager/reservations/index.php`) ~1.5h

- [ ] `Manager\ReservationController@index()` con query params (date range, status, sort, page)
- [ ] Filtros de fecha → `<form method="GET">`
- [ ] Tabla → PHP `foreach`

### F4.4 — Manager Staff (`manager/staff/index.php`, `manager/staff/show.php`) ~1h

- [ ] Tabla → PHP `foreach`
- [ ] Asignación de turno via fetch ya correcta tras F0.2

---

## Fase 5 — Wizard de reservas ~8h

_Independiente de F3-F4. Solo requiere Fase 1._

### F5.1 — Eliminar `init()` fetches → PHP-inject ~3h

**Problema actual:** `reservaForm()` en `init()` hace `fetch('/api/v1/cafes')` y `fetch('/api/v1/passes')` — viola Invariante 1.

- [ ] **F5.1a** Identificar el controller que renderiza `/reservar` (`Public\ReservacionController`)
- [ ] **F5.1b** Inyectar cafés activos y pases activos desde el controller vía repositorios
- [ ] **F5.1c** `resources/views/shared/reservas/index.php`: pasar cafés y pases como parámetros:
  `x-data="reservaForm(<?= json_encode($cafes) ?>, <?= json_encode($passes) ?>, $cartTotal, $festivos)"`
- [ ] **F5.1d** `public/js/sections/reservas.js`: reescribir `init()` para recibir `cafes` y `passes`
  como parámetros — eliminar los dos `fetch()` del `init()`

### F5.2 — Multi-step PRG con estado en sesión PHP ~4h

**Flujo objetivo:**

```
GET  /reservar           → PHP renderiza Paso 1 (cafés, pases de F5.1)
POST /reservar/paso-1    → validar, $_SESSION['wizard'], redirect → GET /reservar/paso-2
GET  /reservar/paso-2    → PHP lee sesión, renderiza fecha; TimeSlotService para slots del primer día
[AJAX] GET /api/v1/time-slots/available?date=&cafe_id=  ← Alpine reactivo (Invariante 5 ✅)
POST /reservar/paso-2    → validar, $_SESSION['wizard'], redirect → GET /reservar/paso-3
GET  /reservar/paso-3    → PHP lee sesión, renderiza resumen completo (sin JavaScript)
POST /reservar           → crear reserva, limpiar sesión, redirect → /reservas/confirmacion/{id}
```

- [ ] **F5.2a** `Public\ReservacionController`: añadir `paso2()`, `paso3()`, `procesarPaso1()`, `procesarPaso2()`
- [ ] **F5.2b** Crear vistas `reservas/paso-2.php` y `reservas/paso-3.php`
- [ ] **F5.2c** Gestión de sesión wizard (`$_SESSION['wizard']`): guardar y limpiar correctamente
- [ ] **F5.2d** El AJAX de slots (`GET /api/v1/time-slots/available`) se mantiene — query reactiva ✅

### F5.3 — Routes ~1h

- [ ] **F5.3a** `app/routes.php`: añadir `GET /reservar/paso-2`, `GET /reservar/paso-3`,
  `POST /reservar/paso-1`, `POST /reservar/paso-2` (todos con CSRF middleware)

---

## Fase 6 — Documentación y limpieza ~4h

_F6.1-F6.2: en cualquier momento. F6.3: al final, tras completar F2-F5._

### F6.1 — ADR 001: Hypermedia-Driven Application ~1h

- [ ] Crear directorio `docs/adr/`
- [ ] `docs/adr/001-hda-architecture.md`: contexto, decisión, los 5 invariantes, consecuencias para el código, alternativas consideradas (BFF — rechazado, SSR completo sin JS — rechazado)

### F6.2 — `docs/ARCHITECTURE.md` sección front-end ~1h

- [ ] Añadir sección "Capa de Presentación: HDA + Progressive Enhancement"
- [ ] Documentar rol de Alpine.js post-migración
- [ ] Documentar contrato de la API REST (solo mutaciones con feedback inline)

### F6.3 — Limpieza GET `/api/v1/` ~2h

- [ ] Auditar `routes.php`: identificar endpoints `GET /api/v1/` que solo servían datos de página
- [ ] Conservar: `GET /api/v1/time-slots/available` (reactivo — Invariante 5 ✅)
- [ ] Evaluar `GET /api/v1/cafes` y `GET /api/v1/passes`: si solo los consumía el wizard tras F5.1, eliminar
- [ ] Actualizar `docs/openapi.yaml` para reflejar el nuevo contrato

---

## Dependencias

```
F0.1 ─┐
F0.2 ─┼──→ F1 ──→ F2 ──→ F3.1 ─┐
F0.3 ─┘    ↑      ↑       F3.2  │
           └──→ F5        F3.3  │ (paralelo)
      F4 ──────────────→  F3.4  │
      (solo requiere F0.2) F3.5  │
                           F3.6 ─┘
F6.1-F6.2 (en cualquier momento)
F6.3 (al final, tras F2-F5)
```

---

## Resumen de archivos modificados

| Archivo | Fase | Tipo de cambio |
|---|---|---|
| `app/routes.php` | F0.1, F5.3 | Añadir rutas roles API; rutas wizard |
| `public/js/sections/admin/admin-roles.js` | F0.1 | URLs `/admin/roles/*` → `/api/v1/admin/roles/*` |
| `resources/views/manager/cafe/show.php` | F0.2 | 3 fetch corregidos |
| `resources/views/manager/products/index.php` | F0.2 | 4 fetch corregidos |
| `resources/views/manager/staff/index.php` | F0.2 | 1 fetch corregido |
| `resources/views/manager/staff/show.php` | F0.2 | decidir/implementar |
| `app/Repositories/MenuRepository.php` | F0.3 | +2 columnas en SELECT |
| `app/Repositories/AbstractRepository.php` | F1.1 | Extender findPaginated() |
| `app/Domain/DTO/PaginationParams.php` | F1.2 | Nuevo |
| `app/Support/ViewHelpers.php` | F1.3 | Nuevo o extender |
| `app/Http/Controllers/Admin/UserController.php` | F2.1 | index() con query params |
| `resources/views/admin/users/partials/_filters.php` | F2.2 | Alpine → HTML GET form |
| `resources/views/admin/users/partials/_user-table.php` | F2.3 | PHP foreach, sort links |
| `public/js/sections/admin/admin-users.js` | F2.4 | 400 → ~100 líneas |
| (x5 paneles admin: cafes, products, reservations, reviews, roles, waitlist) | F3.x | Mismo patrón |
| (x4 vistas manager: products, cafe, reservations, staff) | F4.x | Mismo patrón |
| `resources/views/shared/reservas/index.php` | F5.1 | Eliminar init() fetches |
| `public/js/sections/reservas.js` | F5.1 | Reescribir init() |
| `app/Http/Controllers/Public/ReservacionController.php` | F5.2 | Nuevos métodos paso |
| `resources/views/shared/reservas/paso-2.php` | F5.2 | Nuevo |
| `resources/views/shared/reservas/paso-3.php` | F5.2 | Nuevo |
| `docs/adr/001-hda-architecture.md` | F6.1 | Nuevo |
| `docs/ARCHITECTURE.md` | F6.2 | Actualizar sección frontend |
| `docs/openapi.yaml` | F6.3 | Actualizar contrato API |
| `docs/plans/2026-04-24-api-audit-bugfix.md` | — | **Eliminar al completar F0** |

---

## Argumento para la defensa

> _"La arquitectura sigue el modelo Hypermedia-Driven Application: el servidor es la única fuente de verdad. PHP renderiza HTML completo en cada GET, con datos desde la base de datos y el estado de navegación codificado en la URL. Alpine.js existe exclusivamente como capa de progressive enhancement — gestión de modales y feedback inline de mutaciones. No existe ningún endpoint GET de la API que sirva datos de página; el contrato de la API REST es estrictamente el de las mutaciones que requieren feedback inline sin recargar la página completa."_
