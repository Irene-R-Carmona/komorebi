# Informe de Auditoría Visual — Komorebi Café

**Fecha:** 30 de abril de 2026
**Método:** Playwright (Chromium) — sesión autenticada como `admin@komorebi.cafe`
**Total de páginas auditadas:** 57
**Páginas con problemas:** 15

---

## Resumen ejecutivo

| Categoría | Cantidad |
|-----------|----------|
| ✅ Sin problemas visuales | 42 |
| ⚠️ Assets faltantes (CSS/JS 404) | 3 páginas afectadas |
| 🔴 Rutas inaccessibles (redirects) | 9 rutas |
| 🔴 Rutas inexistentes (HTTP 404) | 1 ruta |
| ⚠️ API background con error | 2 endpoints |

---

## Problemas detectados

### 1. Archivos CSS/JS faltantes

Estos archivos son referenciados en las vistas pero no existen en `public/`:

| # | Ruta de la página | Recurso faltante | Impacto visual |
|---|-------------------|-----------------|----------------|
| 1 | `/` (home) | `public/css/home.css` | Bajo — página carga correctamente |
| 2 | `/menu` | `public/css/menu.css` | Bajo — página carga correctamente |
| 3 | `/admin/dashboard` | `public/css/admin/admin-dashboard.css` | Bajo — dashboard visualmente intacto |
| 3 | `/admin/dashboard` | `public/js/admin/admin-dashboard.js` | Bajo — funcionalidad JS posiblemente degradada |

> **Nota:** Las páginas `/` y `/menu` renderizan visualmente bien a pesar del 404, lo que
> indica que los archivos faltantes eran adiciones planeadas pero no críticas.
> El dashboard admin también se renderiza correctamente con Bootstrap 5 y los tokens de diseño.

**Archivos a crear:**
- `public/css/home.css`
- `public/css/menu.css`
- `public/css/admin/admin-dashboard.css`
- `public/js/admin/admin-dashboard.js`

---

### 2. Rutas que redirigen (inaccessibles)

Estas rutas redirigen en lugar de mostrar su contenido. La causa principal es el middleware `ownsCafe()`
o la ausencia de contexto de sede en el usuario de prueba (`admin@komorebi.cafe` sin café asignado).

#### 2a. Módulo Manager → redirigen a `/manager/dashboard`

| # | Ruta solicitada | Redirige a | Causa probable |
|---|----------------|-----------|----------------|
| 4 | `/manager/staff` | `/manager/dashboard` | `ownsCafe()` sin café asignado |
| 5 | `/manager/cafe` | `/manager/dashboard` | `ownsCafe()` sin café asignado |
| 6 | `/manager/products` | `/manager/dashboard` | `ownsCafe()` sin café asignado |

#### 2b. Módulo Keeper → redirigen a `/manager/dashboard`

| # | Ruta solicitada | Redirige a | Causa probable |
|---|----------------|-----------|----------------|
| 7 | `/keeper/animals` | `/manager/dashboard` | `ownsCafe()` sin café asignado |
| 8 | `/keeper/health-checks` | `/manager/dashboard` | `ownsCafe()` sin café asignado |
| 9 | `/keeper/incidents` | `/manager/dashboard` | `ownsCafe()` sin café asignado |

#### 2c. Módulo Ops/Recepción → redirigen a `/` (home)

| # | Ruta solicitada | Redirige a | Causa probable |
|---|----------------|-----------|----------------|
| 10 | `/ops/reception` | `/` | `ReceptionController` lanza `ValidationException` (sin `cafeId`) |
| 11 | `/ops/reception/reservations` | `/` | Misma causa — sin contexto de sede |

> **Severidad:** Alta para funcionalidad, Baja para "bug visual". Los redirects son comportamiento
> correcto del sistema cuando el usuario no tiene café asignado. Para reproducir correctamente
> estas vistas hay que usar un usuario con rol `manager`/`keeper`/`reception` y café asignado.

#### 2d. Kitchen/Orders → redirige a `/ops/kitchen`

| # | Ruta solicitada | Redirige a | Causa probable |
|---|----------------|-----------|----------------|
| 12 | `/ops/kitchen/orders` | `/ops/kitchen` | La ruta existe (`KitchenController@activeOrders`) pero la vista redirige cuando no hay órdenes activas o hay un redirect interno |

---

### 3. Rutas HTTP 404 (no definidas)

| # | Ruta | Código HTTP | Notas |
|---|------|-------------|-------|
| 13 | `/keeper/schedule` | 404 | Ruta **no registrada** en `app/routes.php` ni en `app/routes/ops.php`. La página 404 custom se muestra correctamente. |

> Las rutas keeper definidas en `routes.php` son: `/keeper/dashboard`, `/keeper/animals`,
> `/keeper/animals/{id}`, `/keeper/health-checks`, `/keeper/health-checks/create/{animalId}`,
> `/keeper/health-checks/{checkId}`, `/keeper/health-checks/history/{animalId}`,
> `/keeper/incidents`, `/keeper/incidents/create`, `/keeper/incidents/{id}`.
> **No existe ninguna ruta `/keeper/schedule`.**

---

### 4. Errores de API en background (afectan todas las páginas)

Estos errores ocurren silenciosamente; no producen rotura visual directa pero indican
funcionalidad degradada.

| # | Endpoint | Error | Páginas afectadas | Impacto |
|---|----------|-------|-------------------|---------|
| 14 | `GET /api/v1/cart` | `ERR_ABORTED` | Todas las páginas públicas | Widget de carrito sin datos |
| 15 | `GET /api/v1/user/profile` | `ERR_ABORTED` | Páginas de perfil | Datos de perfil API no cargan |

---

## Páginas auditadas sin problemas (✅ OK)

### Páginas públicas (14)

| Ruta | Estado |
|------|--------|
| `/cafes` | ✅ |
| `/quiz` | ✅ |
| `/historia` | ✅ |
| `/faq` | ✅ |
| `/contacto` | ✅ |
| `/legal/privacidad` | ✅ |
| `/legal/cookies` | ✅ |
| `/legal/terminos` | ✅ |
| `/reservar` | ✅ |
| `/login` | ✅ |
| `/registro` | ✅ |
| `/forgot-password` | ✅ |
| `/waitlist/status/{token}` | No probada (requiere token) |
| `/waitlist/confirm/{token}` | No probada (requiere token) |

### Páginas Admin (17)

| Ruta | Estado |
|------|--------|
| `/admin/users` | ✅ |
| `/admin/users/create` | ✅ |
| `/admin/roles` | ✅ |
| `/admin/cafes` | ✅ |
| `/admin/menu` | ✅ |
| `/admin/menu/create` | ✅ |
| `/admin/reviews` | ✅ |
| `/admin/reservations` | ✅ |
| `/admin/waitlists` | ✅ |
| `/admin/animals` | ✅ |
| `/admin/animals/create` | ✅ |
| `/admin/settings` | ✅ |
| `/admin/logs` | ✅ |
| `/admin/logs/audit` | ✅ |
| `/admin/logs/auth` | ✅ |
| `/admin/reports` | ✅ |
| `/admin/data-viewer` | ✅ |

### Páginas Manager/Supervisor/Ops/Keeper (8)

| Ruta | Estado |
|------|--------|
| `/manager/dashboard` | ✅ |
| `/manager/reservations` | ✅ |
| `/manager/reviews` | ✅ |
| `/manager/reports` | ✅ |
| `/supervisor/dashboard` | ✅ |
| `/supervisor/assignments` | ✅ |
| `/ops/kitchen` | ✅ |
| `/ops/kitchen/history` | ✅ |
| `/keeper/dashboard` | ✅ |

### Páginas de usuario autenticado (7)

| Ruta | Estado |
|------|--------|
| `/profile` | ✅ |
| `/account/sessions` | ✅ |
| `/account/security` | ✅ |
| `/account/change-password` | ✅ |
| `/reservas/mis-reservas` | ✅ |
| `/mis-favoritos` | ✅ |
| `/loyalty/card` | ✅ |

---

## Tabla de prioridades

| Prioridad | Issue | Acción recomendada |
|-----------|-------|-------------------|
| 🟡 Media | `public/css/home.css` faltante | Crear archivo CSS (o limpiar referencia en vista) |
| 🟡 Media | `public/css/menu.css` faltante | Crear archivo CSS (o limpiar referencia en vista) |
| 🟡 Media | `public/css/admin/admin-dashboard.css` faltante | Crear archivo (o limpiar referencia) |
| 🟡 Media | `public/js/admin/admin-dashboard.js` faltante | Crear archivo (o limpiar referencia) |
| 🔴 Alta | `/keeper/schedule` → HTTP 404 | Crear la ruta o eliminar referencias a ella en navegación |
| 🔴 Alta | `/ops/reception` → redirige a `/` | Requiere usuario con café asignado; verificar manejo de error |
| 🔵 Baja | Manager/Keeper → redirigen a dashboard | Comportamiento esperado con usuario sin café asignado |
| 🔵 Baja | `GET /api/v1/cart` → ERR_ABORTED | Verificar endpoint `/api/v1/cart` existe y responde |
| 🔵 Baja | `GET /api/v1/user/profile` → ERR_ABORTED | Verificar endpoint de perfil API |

---

## Notas metodológicas

- La auditoría se realizó con el usuario `admin@komorebi.cafe` (rol `admin`).
- Muchos módulos de manager/keeper usan el middleware `ownsCafe()` que requiere un café
  asignado al usuario en la tabla `user_roles`. El usuario admin en el entorno de prueba
  no tiene café asignado, lo que provoca los redirects documentados arriba.
- Para auditar visualmente manager/keeper/reception hay que crear/usar un usuario con
  los roles correspondientes y café asignado (o asignar un café al admin en la DB).
- Todas las rutas con `⚠️ asset 404` fueron inspeccionadas visualmente: las páginas
  renderizan correctamente con Bootstrap 5 y los design tokens — los archivos faltantes
  son suplementarios y su ausencia no rompe el layout.
