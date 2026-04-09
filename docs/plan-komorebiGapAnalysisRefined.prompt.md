# Plan: Gap Analysis komorebi_backup → komorebi-v3 (Refinado)

> Actualizado: 31 marzo 2026 — Re-análisis exhaustivo con lectura de código real
> Estado suite: 622 tests, 0 fallos
>
> **Sesión actual:** P0 ✅ P4 ✅ P5 ✅ — en curso: P1-A

---

## Correcciones al análisis previo (falsos positivos confirmados)

| Área                                | Creído faltante | Estado real                                      |
| ----------------------------------- | --------------- | ------------------------------------------------ |
| Migraciones `cafe_zones`/`trackers` | ⚠️ MISSING      | ✅ En `0001_01_01_000000_create_users_table.php` |
| `components/estacion-widget`        | ❌              | ✅ Existe                                        |
| `components/recently-viewed`        | ❌              | ✅ Existe                                        |
| `components/reserva-contexto`       | ❌              | ✅ Existe                                        |
| `components/stat-card`              | ❌              | ✅ Genérico en raíz                              |
| `components/pagination`             | ❌              | ✅ En raíz                                       |
| `components/modal` + `data-table`   | ❌              | ✅ Ambos en raíz                                 |
| `components/allergen-badges`        | ❌              | ✅ Existe                                        |

---

## Estado real por área (REVISADO)

| Área                                 | Estado                                                  |
| ------------------------------------ | ------------------------------------------------------- |
| Controladores (todos los namespaces) | ✅ 100%                                                 |
| Migraciones DB                       | ✅ 100% — todas las tablas existen                      |
| Sistema de componentes Blade         | ✅ 70% — base genérica ok, faltan admin-específicos     |
| Servicios de negocio                 | ✅ ~90% — falta WeatherService, CartService, InvoicePDF |
| **Middleware seguridad**             | ✅ SecurityHeaders implementado + nonce en layouts      |
| **Vistas Admin backoffice**          | 🔴 ~20% funcionales — resto stubs de 4–8 líneas         |
| Vistas OPS (Kitchen/Reception)       | 🟡 ~40% — básicas sin diseño completo                   |
| Vistas Keeper                        | 🟡 ~50% — faltan `show` y health                        |
| Vistas Public/Frontend               | 🟡 ~60% — faltan reseñas, newsletter, legales           |
| Listeners Telegram                   | ✅ 3 listeners creados y registrados en AppServiceProvider |
| Jobs async                           | ✅ `WaitlistPromotionJob` creado                        |

---

## Sistema de componentes Blade de v3 (ya existe — no duplicar)

`resources/views/components/` tiene 19 componentes:

```
badge.blade.php           — variantes: neutral|success|warning|error|info
button.blade.php          — variantes + loading spinner
card.blade.php            — slots header/footer
stat-card.blade.php       — KPI con valor + tendencia (genérico)
modal.blade.php           — wrapper Alpine x-trap
data-table.blade.php      — tabla con búsqueda/sort/paginación Alpine
pagination.blade.php      — paginación Laravel
allergen-badges.blade.php
clima-widget.blade.php
estacion-widget.blade.php
recently-viewed.blade.php
reserva-contexto.blade.php
cookie-banner.blade.php
cookie-preferences.blade.php
flash-message.blade.php
error-page.blade.php
loading.blade.php
newsletter-popup.blade.php
responsive-image.blade.php
```

Las vistas admin se construyen _componiendo_ estos bloques. No recrear equivalentes — extender con `components/admin/` solo para necesidades específicas de backoffice.

---

## P0 — SecurityHeadersMiddleware ✅ COMPLETADO

### Qué aplica el backup por cada respuesta

- `Content-Security-Policy` con nonce dinámico por request
- `Strict-Transport-Security: max-age=31536000; includeSubDomains; preload`
- `X-Frame-Options: DENY`
- `X-Content-Type-Options: nosniff`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=()`
- `Cache-Control: no-cache, no-store, must-revalidate`

**CSP completa del backup:**

```
default-src 'self'
script-src 'self' cdn.jsdelivr.net 'nonce-{dynamic}' 'unsafe-eval'
style-src 'self' cdn.jsdelivr.net fonts.googleapis.com cdnjs.cloudflare.com 'unsafe-inline'
font-src 'self' fonts.gstatic.com cdn.jsdelivr.net cdnjs.cloudflare.com data:
img-src 'self' data: blob: randomuser.me
connect-src 'self' cdn.jsdelivr.net
frame-ancestors 'none'; form-action 'self'; upgrade-insecure-requests
```

### Plan de implementación en v3

1. **`app/Http/Middleware/SecurityHeaders.php`** — generar nonce con `Str::random(24)` en `handle()`, setear headers, exponer vía `app()->instance('csp-nonce', $nonce)`.

2. **`AppServiceProvider::boot()`** — registrar Blade directive:

   ```php
   Blade::directive('cspNonce', fn() => "<?php echo app('csp-nonce'); ?>");
   ```

3. **`bootstrap/app.php`** — registrar solo en grupo `web`:

   ```php
   $middleware->web(append: [\App\Http\Middleware\SecurityHeaders::class])
   ```

4. **Layouts** — todos los `<script>` inline y CDN usan `nonce="@cspNonce"`.

### Implementación entregada

- `app/Http/Middleware/SecurityHeaders.php` — nonce `Str::random(24)`, 8 cabeceras, `Vite::useCspNonce()`
- `bootstrap/app.php` — `$middleware->web(append: [SecurityHeaders::class])`
- `AppServiceProvider::boot()` — directiva `@cspNonce` registrada
- `resources/views/layouts/app.blade.php` — `nonce="@cspNonce"` en script inline

### Criterios de aceptación P0

- [x] Cada respuesta web tiene `Content-Security-Policy` con nonce único
- [x] El nonce es distinto en requests consecutivas
- [x] Scripts inline en layouts usan `nonce="@cspNonce"`
- [x] Las rutas `/api/*` **no** llevan estos headers

---

## P1 — Vistas Admin backoffice 🔴 ← EN CURSO

### Patrón canónico para vistas admin CRUD en v3

```blade
@extends('layouts.backoffice')
@section('title', 'Título · Admin')

@section('content')
<div
    class="container-fluid py-4"
    x-data="adminModuleName({{ Js::from(['items' => $items, 'meta' => $meta]) }})"
    x-cloak>

    <x-admin.page-header
        icon="people-fill"
        title="Gestión de Usuarios"
        subtitle="Administra el equipo y permisos"
        action-label="Nuevo Usuario"
        action-click="openCreateModal()" />

    <div class="row g-3 mb-4">
        <div class="col-sm-3">
            <x-stat-card label="Total" :value="$stats['total']" icon="people" />
        </div>
    </div>

    <x-data-table :columns="$columns" :data="$rows" />

    <x-modal id="entity-modal" title="Nuevo registro" size="lg">
        @include('admin.module.partials._form')
    </x-modal>

    <x-admin.delete-confirmation-modal />

</div>
@endsection
```

La función Alpine `adminModuleName(config)` se define en `resources/js/alpine-components.js`.
**Datos PHP→Alpine siempre con `Js::from($data)` — nunca `json_encode` manual.**

### Componentes a crear en `components/admin/`

No son redundantes con los de raíz — son especializados para backoffice:

| Componente                            | Props clave                                                                |
| ------------------------------------- | -------------------------------------------------------------------------- |
| `page-header.blade.php`               | icon, title, subtitle, action-label, action-click, action-url, breadcrumbs |
| `empty-state.blade.php`               | icon, message, action-label, action-click                                  |
| `delete-confirmation-modal.blade.php` | sin props — Alpine-driven                                                  |
| `review-card.blade.php`               | review, show-actions                                                       |
| `toast-container.blade.php`           | sin props — Alpine-driven                                                  |

### Componentes a crear en `components/products/`

| Componente                    | Props                                    | Nota                               |
| ----------------------------- | ---------------------------------------- | ---------------------------------- |
| `allergen-checkbox.blade.php` | allergens (Collection), selected (array) | selector múltiple para formularios |
| `image-preview.blade.php`     | src, alt, input-name                     | preview con Alpine FileReader      |

> `allergen-badges.blade.php` ya existe en raíz — **no duplicar**.

### Vistas admin por módulo

#### admin/cafes/

- `index.blade.php` — **reemplazar stub** → grid/tabla, filtros, botón crear
- `partials/_cafe-modal.blade.php` — modal create/edit: nombre, slug, categoría, zona, capacidad, horarios, descripción, imagen
- `partials/_filters.blade.php`

#### admin/users/

- `index.blade.php` — **mejorar** (tiene tabla básica sin acciones) → filtros, roles, editar/desactivar
- `partials/_user-modal.blade.php` — modal create/edit: nombre, email, roles (multiselect), activo
- `partials/_filters.blade.php`

#### admin/products/

- `index.blade.php` — tabla con categoría, precio, alérgenos, acciones
- `create.blade.php` **← FALTA** — secciones: info básica, precio/stock, alérgenos (`<x-products.allergen-checkbox>`), imagen (`<x-products.image-preview>`)
- `edit.blade.php` **← FALTA** — idem con `@method('PUT')` y valores pre-rellenados

#### admin/roles/

- `index.blade.php` — lista roles Spatie + conteo de usuarios por rol
- `partials/_permissions-matrix.blade.php` — tabla role × permission con checkboxes

#### admin/reservations/

- `index.blade.php` — tabla con filtros fecha/estado/café, paginación, stats
- `show.blade.php` — detalle con timeline de estados + acciones (cancelar, completar)

#### admin/reviews/

- `index.blade.php` — tabs: pendientes / aprobadas / rechazadas
- `partials/_reject-modal.blade.php` — modal con campo `reason` obligatorio

#### admin/waitlist/

- `index.blade.php` **← FALTA COMPLETA** — tabla entradas + estado + posición + acciones promover/eliminar

#### admin/animals/

- `index.blade.php` — thumbnail, especie, estado (badge), café asignado
- `show.blade.php` — datos, zona actual, historial de estados, últimos health checks

#### admin/system/ (settings)

- `index.blade.php` — tabs: General / Email / Reservas / Seguridad
  - Cada tab como `@include('admin.system.partials._tab-{name}')`
  - Formulario con `@method('PATCH')` por grupo de settings

#### admin/catalog/ (loyalty rewards)

- `index.blade.php` — tabla de recompensas con acciones CRUD inline

### Criterios de aceptación P1

- [ ] Todas las vistas extienden `layouts.backoffice`
- [ ] Ninguna vista repite lógica ya en componentes
- [ ] Toda acción destructiva usa `<x-admin.delete-confirmation-modal>`
- [ ] Datos PHP→Alpine via `Js::from()`
- [ ] Todos los formularios incluyen `@csrf`

---

## P2 — Vistas OPS: Kitchen, Reception, Keeper, Supervisor 🟡

### Kitchen — KDS (Kitchen Display System)

El backup tiene arquitectura de 3 columnas de estaciones. v3 tiene lista plana básica.

**Archivos:**

- `kitchen/index.blade.php` — **reemplazar**: layout 3 columnas, `@foreach($stations as $key => $items)`, `x-data="kdsApp()"`
- `kitchen/partials/kds-cell.blade.php` — celda: reservation ID, item, cantidad, tiempo transcurrido, botón "Listo"/"Servido"
- `kitchen/partials/sop-modal.blade.php` — modal guía de preparación con `x-show`

**Alpine `kdsApp()` en `alpine-components.js`:**

```js
function kdsApp() {
  return {
    polling: null,
    init() {
      this.polling = setInterval(() => location.reload(), 30000);
    },
    destroy() {
      clearInterval(this.polling);
    },
    markReady(itemId) {
      fetch(`/kitchen/items/${itemId}/ready`, {
        method: "PATCH",
        headers: {
          "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')
            .content,
        },
      }).then(() => location.reload());
    },
  };
}
```

### Reception — Panel zen

El backup tiene: sidebar de llegadas + floor map con anillos de tiempo (conic-gradient).
v3 tiene tabla básica funcional — añadir el diseño.

**Patrón de anillo de tiempo (portar del backup):**

```blade
@php
    $elapsed = Carbon::parse($group->check_in_at)->diffInMinutes();
    $deg = min(360, ($elapsed / 60) * 360);
    $color = match(true) {
        $elapsed > 60 => '#ef4444',
        $elapsed > 50 => '#f59e0b',
        default       => '#87a77b',
    };
@endphp
<div class="table-ring"
     style="background: conic-gradient({{ $color }} {{ $deg }}deg, #e5e7eb 0deg)">
    <div style="position:absolute;inset:6px;background:var(--rec-bg);border-radius:50%;"></div>
    <div class="table-surface">
        <span>#{{ $group->tracker->code }}</span>
        <span>{{ $group->guest_count }} pax</span>
        <span style="color:{{ $color }}">{{ round($elapsed) }} min</span>
    </div>
</div>
```

**Alpine `receptionApp()` en `alpine-components.js`:**

```js
function receptionApp() {
  return {
    checkinOpen: false,
    selectedResId: null,
    openCheckin(id) {
      this.selectedResId = id;
      this.checkinOpen = true;
    },
    closeCheckin() {
      this.checkinOpen = false;
      this.selectedResId = null;
    },
  };
}
```

### Keeper

**keeper/animals/**

- `show.blade.php` **← FALTA** — datos del animal, zona actual, historial de estados (timeline), health checks recientes (últimos 5), botón "Nuevo chequeo"

**keeper/health/**

- `create.blade.php` **← FALTA** — selector animal, temperatura, peso, condición general, observaciones, fecha
- `show.blade.php` **← FALTA** — detalle del chequeo registrado (solo lectura)

### Supervisor

- `supervisor/dashboard.blade.php` — ya funcional ✅ (reservas del día + asignaciones + formulario nueva asignación)
- `supervisor/assignments.blade.php` **← FALTA** — histórico de asignaciones con filtros fecha/café, acciones desactivar

---

## P3 — Vistas Public/Frontend 🟡

### Sección reseñas en detalle de café

Añadir `@include` a `public/cafes/show.blade.php`:

- `public/cafes/partials/reviews-section.blade.php` — rating promedio con stars, listado reseñas aprobadas, paginación
- `public/cafes/partials/review-form.blade.php` — visible solo con `@auth`, validación Alpine, 1–5 estrellas + texto
- `public/cafes/partials/experiences-section.blade.php` — galería masonry o cards de fotos/testimonios

### Newsletter

- `public/newsletter/subscribe.blade.php` — formulario de suscripción standalone (`/newsletter/suscribirse`)
- `public/newsletter/confirm.blade.php` — confirmación tras clic en email (`/newsletter/confirmar/{token}`)
- `public/newsletter/unsubscribe.blade.php` — baja con token (`/newsletter/baja/{token}`)

### Páginas estáticas

- `public/quiz/resultado.blade.php` — café recomendado + enlace directo al café + botón "Repetir quiz"
- `public/pages/faq.blade.php`
- `public/pages/contacto.blade.php` — formulario de contacto
- `public/pages/historia.blade.php`

### Legales (GDPR)

- `legal/terms.blade.php`
- `legal/privacy.blade.php`
- `legal/cookies.blade.php` — **DEBE** incluir `<x-cookie-preferences>` (componente ya existe)

### Páginas de error

Usar el componente `<x-error-page>` (ya existe en v3):

- `resources/views/errors/403.blade.php`
- `resources/views/errors/404.blade.php`
- `resources/views/errors/419.blade.php` — sesión expirada (CSRF)
- `resources/views/errors/500.blade.php`

---

## P4 — Listeners Telegram ✅ COMPLETADO

`TelegramService` ya existe en v3 con los métodos `notify*`. Solo faltan los listeners:

| Listener                      | Evento                      | Llamada al servicio                                                     |
| ----------------------------- | --------------------------- | ----------------------------------------------------------------------- |
| `TelegramNewUserListener`     | `UserRegisteredEvent`       | `$telegram->notifyNewUser($event->user)` |
| `TelegramReservationListener` | `ReservationConfirmedEvent` | `$telegram->notifyReservationConfirmed($event->reservation)` |
| `TelegramReviewListener`      | `ReviewPublishedEvent`      | `$telegram->notifyReviewPublished($event->review)` |

> **Nota de adaptación:** las firmas del plan original eran incorrectas. `TelegramService` recibe objetos completos, no scalars. Corregido en implementación.

**Implementación entregada:**
- `app/Listeners/TelegramNewUserListener.php`
- `app/Listeners/TelegramReservationListener.php`
- `app/Listeners/TelegramReviewListener.php`
- `AppServiceProvider` — 3 `Event::listen()` añadidos

> `LogUserRegisteredListener` del backup → **no portado**: el logging se hace directamente en servicios, decisión correcta en v3.

---

## P5 — Jobs async ✅ COMPLETADO

| Job                    | Qué hace                                                             | Estado |
| ---------------------- | -------------------------------------------------------------------- | ------ |
| `WaitlistPromotionJob` | al cancelar una reserva, promueve al siguiente de la lista de espera | ✅     |

**Implementación entregada:** `app/Jobs/WaitlistPromotionJob.php`
- `ShouldQueue`, `$tries = 3`, inyecta `WaitlistService` en `handle()`
- Llama `$waitlistService->promoteNext($this->timeSlotId)`
- Loguea si no hay usuarios en espera (no es error, es estado normal)

**Pendiente:** disparar el job desde el controlador/servicio de cancelación de reservas.

---

## Backlog (sin fecha comprometida)

| Feature                              | Motivo de bloqueo                                          |
| ------------------------------------ | ---------------------------------------------------------- |
| `CartService` + `Api/CartController` | Complejo, caso de negocio no definido                      |
| `InvoicePDFService`                  | Requiere `barryvdh/laravel-dompdf`                         |
| `WeatherService` (Open-Meteo)        | `clima-widget` ya existe esperándolo                       |
| `GamificationService`                | Nice-to-have, no afecta flujo core                         |
| `FestivosJaponesesService`           | Controller y tests ya existen, solo falta el servicio real |
| Manager dashboard con gráficas       | Chart.js; stub actual funcional como punto de partida      |

---

## Arquitectura recomendada — Convenciones v3

### Estructura de vistas

```
resources/views/
  layouts/          — app, backoffice, kds, reception, mobile, errors
  components/       — design system genérico (19 ya existen)
    admin/          — solo backoffice: page-header, empty-state, delete-modal, review-card, toast
    products/       — allergen-checkbox, image-preview
  admin/            — vistas CRUD admin con partials
  manager/          — dashboard, informes
  keeper/           — animals + health
  kitchen/          — KDS + partials
  reception/        — panel zen
  supervisor/       — dashboard + assignments
  public/           — catálogo, café detail, quiz, newsletter, waitlist, páginas
  user/             — dashboard usuario autenticado
  auth/             — login, register, passwords, verify
  legal/            — terms, privacy, cookies
  errors/           — 403, 404, 419, 500
```

### Reglas de implementación de vistas

1. **Alpine data** — siempre `Js::from($array)`, nunca `json_encode` manual
2. **Formularios destructivos** — siempre `<x-admin.delete-confirmation-modal>`, nunca `confirm()` nativo
3. **Flash messages** — solo en layouts con `<x-flash-message>`, no en vistas individuales
4. **Paginación** — `{{ $paginator->links('components.pagination') }}`
5. **Nonce CSP** — `nonce="@cspNonce"` en todo `<script>` inline y CDN
6. **Autorización** — `@can('...')/@endcan` o `@role('admin')/@endrole` (Spatie directives)
7. **Assets CDN** — los layouts ya cargan Bootstrap Icons + Alpine; no duplicar en vistas individuales

### Orden de ejecución recomendado

```
P0 (SecurityHeaders) → P4 (Telegram listeners)     — paralelo
↓
P5 (WaitlistPromotionJob)
↓
P1-A: componentes admin/ + components/products/     ← desbloquean todas las vistas admin
↓
P1-B: vistas admin CRUD (cafes, users, products, roles, reservations, reviews, waitlist)
↓
P2 (Kitchen, Reception, Keeper, Supervisor)         — paralelo entre sí
↓
P3 (Public + legales + errores)
```

---

## Criterios de aceptación por fase

### P0 — SecurityHeaders

- [ ] Cada respuesta web tiene `Content-Security-Policy` con nonce único
- [ ] El nonce es distinto en requests consecutivas
- [ ] Scripts inline en layouts usan `nonce="@cspNonce"`
- [ ] Las rutas `/api/*` no llevan estos headers

### P1 — Admin

- [ ] Todas las vistas extienden `layouts.backoffice`
- [ ] Ninguna vista repite lógica ya en componentes
- [ ] Toda acción destructiva usa `<x-admin.delete-confirmation-modal>`
- [ ] Datos PHP→Alpine via `Js::from()`
- [ ] Todos los formularios incluyen `@csrf`

### P2 — OPS

- [ ] Kitchen KDS muestra mínimo 2 estaciones con auto-refresh (30s)
- [ ] Reception muestra floor map con anillo de tiempo por tracker activo
- [ ] Keeper `show` incluye historial de estados + health checks del animal

### P3 — Public

- [ ] Café detail muestra sección reseñas si hay reseñas aprobadas
- [ ] Formulario reseña visible solo con `@auth`
- [ ] `/legal/cookies` embebe `<x-cookie-preferences>`
- [ ] Páginas de error usan `<x-error-page>` con código y mensaje
