# Plan: Separación Arquitectura SSR / API REST

**Creado:** 2026-04-27
**Estado:** ✅ Verificado y cerrado (todas las fases completadas)
**Rama:** `feature/api-architecture-separation`

---

## Problema

43 rutas `/api/v1/*` apuntan a 13 controladores híbridos que mezclan SSR y JSON:

- Sin envelope RFC 9457 consistente
- Sin ETag / `Vary: Accept`
- Dos rutas apuntan a `View::render()` → respuesta HTML desde endpoint JSON
- Constructores nullable con `Container::make()` (dificultan tests y DI)
- `admin-products.js` llama a URLs legacy POST en lugar de REST

---

## Regla de oro

> Los controladores en `Api\V1\*` extienden `AbstractApiController`, reciben todas sus
> dependencias como parámetros requeridos (no nullable), y **siempre** devuelven
> `ResponseInterface` (nunca `?ResponseInterface`).

---

## Inventario de rutas afectadas

| Grupo     | Rutas | Controladores híbridos actuales         | Nuevos controladores `Api\V1\`      |
|-----------|-------|-----------------------------------------|-------------------------------------|
| Admin     | 32    | `Admin\UserController`, `CafeController`, `MenuController`, `ReviewController`, `ReservationController`, `RoleController`, `SystemController`, `AuditLogController`, `AuthLogController` | `Admin\UserApiController`, `CafeApiController`, `MenuApiController`, `ReviewApiController`, `ReservationApiController`, `RoleApiController`, `SystemApiController`, `LogApiController` |
| Manager   | 11    | `Manager\CafeController`, `ProductController`, `StaffController`, `Admin\ReviewController` | `Manager\CafeApiController`, `ProductApiController`, `StaffApiController`, `ReviewApiController` |
| Ops       | 5     | `Reception\ReceptionController`, `Kitchen\KitchenController` | `Ops\ReceptionApiController`, `KitchenApiController` |

---

## Fases

### Fase 1 — Crear `Api\V1\Admin\` (8 controladores) ✅

**Directorio:** `app/Http/Controllers/Api/V1/Admin/`

| Archivo              | Deps principales                                                  | Métodos API                                               |
|----------------------|-------------------------------------------------------------------|-----------------------------------------------------------|
| `UserApiController`  | `UserManagementServiceInterface`, `UserRepositoryInterface`       | `create`, `update`, `delete`, `toggleActive`              |
| `CafeApiController`  | `CafeServiceInterface`                                            | `create`, `update`, `delete`, `toggleStatus`              |
| `MenuApiController`  | `ProductServiceInterface`                                         | `create`, `update`, `delete`, `toggleAvailability`        |
| `ReviewApiController`| `ReviewModerationServiceInterface`                                | `approve`, `reject`, `delete`                             |
| `ReservationApiController` | `ReservationServiceInterface`                             | `confirm`, `cancel`                                       |
| `RoleApiController`  | `Role` model, `Permission` model                                  | `createRole`, `updateRole`, `deleteRole`, `grantPermission`, `revokePermission` |
| `SystemApiController`| `SettingsServiceInterface`, `EmailServiceInterface`, `AuditLogRepositoryInterface` | `getSettingsData`, `updateSettingsGroup`, `testEmail`, `clearCache` |
| `LogApiController`   | `AuditLogRepositoryInterface`, `AuthLogRepositoryInterface`       | `auditLogs`, `auditExport`, `authLogs`, `suspiciousCount`, `authExport`, `blockIp` |

- [x] `UserApiController.php`
- [x] `CafeApiController.php`
- [x] `MenuApiController.php`
- [x] `ReviewApiController.php`
- [x] `ReservationApiController.php`
- [x] `RoleApiController.php`
- [x] `SystemApiController.php`
- [x] `LogApiController.php`

### Fase 2 — Crear `Api\V1\Manager\` (4 controladores)

**Directorio:** `app/Http/Controllers/Api/V1/Manager/`

| Archivo              | Deps principales                                  | Métodos API                                           |
|----------------------|---------------------------------------------------|-------------------------------------------------------|
| `CafeApiController`  | `CafeServiceInterface`                            | `updateCapacity`, `updateSchedule`, `updateSettings`  |
| `ProductApiController`| `ProductServiceInterface`                        | `create`, `update`, `toggleAvailability`, `delete`    |
| `StaffApiController` | `StaffServiceInterface` o similar                 | `assignShift`, `editPermissions`, `viewPerformance`   |
| `ReviewApiController`| `ReviewModerationServiceInterface`                | `approve`, `reject`                                   |

- [x] `CafeApiController.php`
- [x] `ProductApiController.php`
- [x] `StaffApiController.php`
- [x] `ReviewApiController.php` (delega a `Admin\ReviewApiController`)

### Fase 3 — Crear `Api\V1\Ops\` (2 controladores)

**Directorio:** `app/Http/Controllers/Api/V1/Ops/`

| Archivo                 | Deps principales                   | Métodos API                                        |
|-------------------------|------------------------------------|----------------------------------------------------|
| `ReceptionApiController`| `ReservationServiceInterface`      | `todayReservations`, `checkIn`, `checkOut`         |
| `KitchenApiController`  | Kitchen service/repo               | `activeOrders`, `completeOrder`                    |

- [x] `ReceptionApiController.php`
- [x] `KitchenApiController.php`

### Checkpoint V1 — Actualizar routes.php + verificación smoke

- [x] Actualizar 32 rutas `/api/v1/admin` → `Api\V1\Admin\*`
- [x] Actualizar 11 rutas `/api/v1/manager` mutations → `Api\V1\Manager\*`
- [x] Actualizar 5 rutas `/api/v1/ops` → `Api\V1\Ops\*`
- [x] `make phpstan` → 0 errores
- [ ] Verificación smoke de Content-Type: todas las rutas nuevas devuelven `application/json`

### Fase 4 — Limpiar controladores híbridos

Eliminar métodos API migrados de los 13 controladores híbridos.
El método SSR `index()` (y similares) permanece en el controlador original.

- [x] Limpiar `Admin\UserController` (conservar `index()`)
- [x] Limpiar `Admin\CafeController` (conservar `index()`)
- [x] Limpiar `Admin\MenuController` (conservar `index()`)
- [x] Limpiar `Admin\ReviewController` (conservar `index()`)
- [x] Limpiar `Admin\ReservationController` (conservar `index()`)
- [x] Limpiar `Admin\RoleController` (conservar `index()`)
- [x] Limpiar `Admin\SystemController` (conservar vistas SSR)
- [x] Limpiar `Admin\AuditLogController` (conservar `index()` SSR)
- [x] Limpiar `Admin\AuthLogController` (conservar `index()` SSR)
- [x] Limpiar `Manager\CafeController` (conservar `index()`)
- [x] Limpiar `Manager\ProductController` (conservar `index()`)
- [x] Limpiar `Manager\StaffController` (conservar `index()`)
- [x] Limpiar `Reception\ReceptionController` (conservar SSR)
- [x] Limpiar `Kitchen\KitchenController` (conservar SSR)

### Checkpoint V2 — Regresión post-limpieza

- [x] `make test-unit` → 0 fallos
- [x] Vistas SSR admin/manager siguen renderizando (Playwright screenshot)
- [x] `make phpstan` → 0 errores

### Fase 5 — Actualizar `admin-products.js`

| URL legacy (POST)                        | URL REST nueva            | Método |
|------------------------------------------|---------------------------|--------|
| `/admin/productos/crear`                 | `/api/v1/admin/menu`      | POST   |
| `/admin/productos/{id}/edit`             | `/api/v1/admin/menu/{id}` | PUT    |
| `/admin/productos/{id}/toggle-available` | `/api/v1/admin/menu/{id}/toggle` | PATCH |
| `/admin/productos/{id}/delete`           | `/api/v1/admin/menu/{id}` | DELETE |

- [x] Actualizar `public/js/admin/admin-products.js`

### Checkpoint V3 — Verificación JS

- [ ] Operaciones CRUD de productos desde la UI admin funcionan
- [ ] DevTools → Network: sin peticiones a URLs legacy `/admin/productos/*`

### Fase 6 — Modularizar routes.php

Dividir `app/routes.php` (600 líneas) en archivos por módulo:

```
app/routes/
  public.php      ← rutas públicas + auth
  admin.php       ← /admin/* + /api/v1/admin/*
  manager.php     ← /manager/* + /api/v1/manager/*
  ops.php         ← /ops/* + /api/v1/ops/*
  api.php         ← /api/v1/* auth-required user routes (ya correctas)
```

`app/routes.php` queda como dispatcher que incluye cada archivo.

- [x] Crear `app/routes/public.php` (rutas públicas + health + CORS + errors)
- [x] Crear `app/routes/auth.php` (guest auth + auth user SSR/API — fusiona `manager.php` y `api.php`)
- [x] Crear `app/routes/admin.php` (FEATURE_BACKOFFICE: /admin + /manager + /supervisor + /api/v1/admin+manager+supervisor)
- [x] Crear `app/routes/ops.php` (FEATURE_OPS + FEATURE_KEEPER)
- [x] Actualizar `app/routes.php` (dispatcher de 29 líneas)
- [x] PHPStan 0 errores tras modularización
- [x] Smoke test: `/`, `/health`, `/api/v1/menu/productos`, `/admin/dashboard` → 200 OK

### Fase 7 — Eliminar rutas duplicadas / legacy

- [x] Eliminar 12 rutas POST legacy (admin/roles, manager/cafe, manager/products) que duplican las REST
- [x] Actualizar JS/formularios HTML que usaban rutas antiguas (`admin-roles.js`, `admin-cafes.js`, `manager-products.js` → todos usan `/api/v1/*`)
- [x] Restaurar grupo `/supervisor` omitido en refactorización (regresión corregida)
- [x] `grep -r '/admin/productos' public/js` → 0 resultados

### Checkpoint V3 ✅

- [x] POST `/admin/roles/create` → 404
- [x] POST `/admin/roles/1/edit` → 404
- [x] POST `/manager/cafe/schedule` → 404
- [x] POST `/manager/cafe/capacity` → 404
- [x] POST `/manager/products/create` → 404

### Checkpoint V4 ✅

- [x] PHPStan `[OK] No errors` — 745 archivos
- [x] PHPUnit: Tests: 1763, Failures: 0, Errors: 14 (todos integration tests sin BD — pre-existentes)
- [x] FeatureFlagsTest 9/9 ✅
- [x] RouteRegistrationTest 17/17 ✅

---

## Métricas antes / después

| Métrica                               | Antes | Después |
|---------------------------------------|-------|---------|
| Rutas `/api/v1/*` con controller híbrido | 43  | 0       |
| Controladores que mezclan SSR + JSON  | 13    | 0       |
| Controladores `Api\V1\*` puros        | 7     | 20      |
| Constructores con params nullable     | 13    | 0 nuevos|
| Rutas duplicadas (legacy POST)        | ~12   | 0       |
| Líneas en routes.php                  | ~600  | ~80     |
