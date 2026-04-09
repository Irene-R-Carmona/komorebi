# Plan: Gap Analysis komorebi_backup → komorebi-v3

> Actualizado: 31 marzo 2026 — Re-análisis completo
> Estado suite: 622 tests pasando, 0 fallos

---

## Estado real del proyecto

| Área | Estado |
|------|--------|
| Controladores (todos los namespaces) | ✅ 100% implementados y testeados |
| Servicios de negocio | ✅ ~90% (falta WeatherService, CartService, InvoicePDF) |
| Listeners/Telegram | ⚠️ 4 listeners de Telegram no portados |
| Jobs | ⚠️ WaitlistPromotionJob y ProcessImageJob faltan |
| Vistas Blade | 🔴 CRÍTICO — solo ~29% implementadas (40/139 ficheros) |
| Middleware seguridad | 🔴 SecurityHeadersMiddleware no portado |
| Migraciones DB | ✅ ~95% — todas las tablas principales existen |

---

## P0 — Seguridad (1–2h)

### SecurityHeadersMiddleware

El backup tiene `app/Middleware/SecurityHeadersMiddleware.php` con:
- CSP dinámico con nonce para scripts inline
- HSTS con preload
- X-Frame-Options, X-Content-Type-Options
- Cache-Control para endpoints dinámicos

**En v3: no existe.** El único middleware custom es `EncryptCookies`.
Riesgo OWASP: Security Misconfiguration (A05).

Archivos a crear:
- `app/Http/Middleware/SecurityHeaders.php`
- Registrar en `bootstrap/app.php` → `$middleware->web(append: [SecurityHeaders::class])`

---

## P1 — Vistas Admin (2–3 semanas)

El backup tiene 40+ vistas en `resources/views/admin/`. v3 tiene ~10, el resto son stubs de 4 líneas.

### Vistas completamente ausentes en v3

**admin/cafes/**
- `partials/_cafe-grid.blade.php`
- `partials/_cafe-modal.blade.php`
- `partials/_filters.blade.php`
- `partials/_stats.blade.php`

**admin/users/**
- `create.blade.php`
- `edit.blade.php`
- `partials/_user-table.blade.php`
- `partials/_user-modal.blade.php`
- `partials/_filters.blade.php`
- `partials/_stats.blade.php`

**admin/products/**
- `create.blade.php` ← MAIN VIEW MISSING
- `edit.blade.php` ← MAIN VIEW MISSING
- `partials/_form-basic-info.blade.php`
- `partials/_form-allergens.blade.php`
- `partials/_form-details.blade.php`
- `partials/_form-image.blade.php`

**admin/roles/**
- `partials/_role-list.blade.php`
- `partials/_role-modal.blade.php`
- `partials/_permissions-matrix.blade.php`
- `partials/_permissions-modal.blade.php`
- `partials/_stats.blade.php`

**admin/reservations/**
- `partials/_table.blade.php`
- `partials/_modal.blade.php`
- `partials/_filters.blade.php`
- `partials/_stats.blade.php`

**admin/settings/** (alias admin/system/)
- `partials/_tab-general.blade.php`
- `partials/_tab-email.blade.php`
- `partials/_tab-reservations.blade.php`
- `partials/_tab-security.blade.php`

**admin/reviews/**
- `partials/_stats.blade.php`
- `partials/_reject-modal.blade.php`

**admin/waitlist/**
- `index.blade.php` ← INEXISTENTE

---

## P2 — Vistas Keeper + Kitchen + Reception (1 semana)

**keeper/animals/**
- `show.blade.php` (detalle/edición — MISSING)
- `index.blade.php` (solo stub)

**keeper/health/**
- `create.blade.php`
- `show.blade.php`
- `history.blade.php`

**kitchen/**
- `index.blade.php` — stub sin celdas KDS
- `partials/kds_cell.blade.php`
- `partials/sop_modal.blade.php`

**reception/**
- `index.blade.php` — stub sin flujo de check-in completo

**supervisor/**
- `assignments.blade.php` ← INEXISTENTE

---

## P3 — Vistas Public/Frontend (3–4 días)

**public/cafes/**
- `review_form.blade.php` ← MISSING review form
- `reviews_section.blade.php` ← MISSING reviews display
- `experiences_section.blade.php` ← MISSING experiences

**public/newsletter/**
- `subscribe.blade.php`
- `confirm.blade.php`
- `unsubscribe.blade.php`

**public/waitlist/**
- `confirm.blade.php`
- `status.blade.php`

**public/pages/**
- `faq.blade.php`
- `contacto.blade.php`
- `historia.blade.php`

**public/quiz/**
- `resultado.blade.php`

**legal/**
- `terms.blade.php`
- `privacy.blade.php`
- `cookies.blade.php`

---

## P4 — Componentes compartidos (2–3 días)

**components/admin/** (7 componentes faltantes)
- `page-header.blade.php` — barra título/breadcrumb
- `stat-card.blade.php` — tarjeta KPI dashboard
- `pagination.blade.php` — paginación
- `empty-state.blade.php` — tabla vacía
- `review-card.blade.php` — tarjeta de reseña
- `delete-confirmation-modal.blade.php` — diálogo borrado
- `toast-container.blade.php` — contenedor notificaciones

**components/modals/** (4 componentes faltantes)
- `base-modal.blade.php` — wrapper modal base
- `cafe-form-modal.blade.php` — CRUD café
- `product-form-modal.blade.php` — CRUD producto
- `user-form-modal.blade.php` — CRUD usuario

**components/products/** (3 componentes faltantes)
- `allergen-badges.blade.php`
- `allergen-checkbox.blade.php`
- `image-preview.blade.php`

**components/** generales faltantes
- `estacion-widget.blade.php` — widget estación/clima
- `recently-viewed-widget.blade.php` — cafés vistos recientemente
- `reserva-contexto.blade.php` — tarjeta contexto reserva

---

## P5 — Jobs faltantes (1 día)

| Job | Backup | v3 | Prioridad |
|-----|--------|----|----|
| `WaitlistPromotionJob` | ✅ | ❌ | Media |
| `ProcessImageJob` | ✅ | ❌ | Baja |

---

## P6 — Listeners Telegram (1 día)

Listeners presentes en backup pero no portados a v3:
- `TelegramNewUserListener` → notifica nuevo registro vía bot
- `TelegramReservationListener` → notifica nueva reserva
- `TelegramReviewListener` → notifica nueva reseña
- `LogUserRegisteredListener` → logging separado de registro

Patrón de registro en `app/Providers/AppServiceProvider.php`:
```php
Event::listen(UserRegisteredEvent::class, TelegramNewUserListener::class);
```

---

## Backlog (sin fecha)

| Feature | Motivo bloqueo |
|---------|---------------|
| `CartService` + `Api/CartController` | Complejo, sin uso inmediato |
| `InvoicePDFService` | Sin librería PDF en composer.json |
| `WeatherService` (Open-Meteo API) | Externo, prioridad baja |
| `GamificationService` (logros/niveles) | Nice-to-have |
| `FestivosJaponesesService` | Nicho, prioridad baja |

---

## Vistas de error

Backup tiene 5 plantillas custom (400, 403, 404, 419, 500).
v3 usa las de Laravel por defecto.
Acción: crear `resources/views/errors/` con diseño del proyecto.

---

## Notas arquitectónicas

- v3 tiene **19 modelos Eloquent nuevos** respecto al backup (mejor separación: AnimalHealthCheck, AnimalIncident, StaffShift, etc.) — esto es una mejora, no un gap.
- Los **4 listeners de logging** del backup (LogUserRegistered, etc.) fueron reemplazados en v3 por jobs de email — decisión de diseño correcta, no un gap.
- `cafe_zones` y `trackers`: verificar si las tablas existen en la DB (las migraciones de v3 pueden tenerlas bajo otro nombre).
- Roles/permisos: backup usaba SQL puro, v3 usa Spatie Permission — diferente schema pero funcionalidad equivalente.
