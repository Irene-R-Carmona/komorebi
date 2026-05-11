# Índice Maestro de Planes — Komorebi Café

| Emoji | Estado |
|---|---|
| 🔵 | Plan creado — pendiente inicio |
| 🟡 | En implementación |
| 🟢 | Implementación completa — pendiente verificación |
| ✅ | Verificado y cerrado |

---

## Planes activos

| # | Plan | Archivo | Wave | Estado | Última actualización |
|---|---|---|---|---|---|
| — | Documentación Oficial TFG (LaTeX → PDF) | [2026-05-08-documentacion-tfg.md](2026-05-08-documentacion-tfg.md) | — | 🔵 Plan creado | 2026-05-08 |
| 1 | Identidad & Ubicación (14 cafés españoles) | _(eliminado)_ | 1 | ✅ Completado y eliminado (2026-05-09) | 2026-05-09 |
| 2 | Moneda & Precios (€) | _(eliminado)_ | 1 | ✅ Completado y eliminado (2026-05-09) | 2026-05-09 |
| 3 | Weather API & Tech Debt | _(eliminado)_ | 1 | ✅ Completado y eliminado (2026-05-09) | 2026-05-09 |
| 4 | Restricciones de Dominio (migration 031) | _(eliminado)_ | 2 | ✅ Completado y eliminado (2026-05-09) | 2026-05-09 |
| 5 | Settings & Cancelación (migration 032) | _(eliminado)_ | 2 | ✅ Completado y eliminado (2026-05-09) | 2026-05-09 |
| 6 | Módulo Adopciones (migration 033) | _(eliminado)_ | 2 | ✅ Completado y eliminado (2026-05-09) | 2026-05-09 |
| 7 | Servicios PHP con TDD | — | 3 | ✅ Completado y eliminado (2026-05-09) | 2026-05-09 |
| 8 | UX Reglas de Negocio & Contenido | _(eliminado)_ | 4 | ✅ Completado y eliminado (2026-05-09) | 2026-05-09 |
| 9 | Brand-Book Update | — | 4 | ✅ Completado y eliminado (2026-05-09) | 2026-05-09 |
| 10 | Pases con Incluidos + Rediseño Formulario Reserva | _(eliminado)_ | 4 | ✅ Completado y eliminado (2026-05-09) | 2026-05-09 |
| 11 | 4 Features Wizard Reservas (weather forecast, slot filter, pass inclusions, comanda step) | _(eliminado)_ | 5 | ✅ Completado y eliminado (2026-05-09) | 2026-05-09 |
| 12 | Rediseño Comanda Wizard Step 5 + KDS pre_order | [2026-05-09-comanda-redesign.md](2026-05-09-comanda-redesign.md) | 5 | 🟡 En implementación | 2026-05-09 |
| 13 | Time Slots y Sistema de Disponibilidad | [2026-05-11-timeslots-y-disponibilidad.md](2026-05-11-timeslots-y-disponibilidad.md) | 6 | 🔵 Plan creado — pendiente inicio | 2026-05-11 |
| 14 | Bugfixes UI/UX (escape, SOP, €, notif, filtros) | [2026-05-11-bugfixes-ui.md](2026-05-11-bugfixes-ui.md) | 6 | 🔵 Plan creado — pendiente inicio | 2026-05-11 |
| 15 | Bugfixes panel Admin (10 bugs confirmados) | [2026-05-11-bugfixes-admin.md](2026-05-11-bugfixes-admin.md) | 6 | 🔵 Plan creado — pendiente inicio | 2026-05-11 |

---

## Mapa de waves y dependencias

```
WAVE 1 — Identidad (bloqueante para todo lo demás)
  Plan 1 (Identidad)  ──┬──> Plan 3 (Weather — necesita coords españolas)
  Plan 2 (Moneda)      │    Plan 4 (themed_district en migration)
  Plan 3 (Weather)     │
                        │
WAVE 2 — Dominio (requiere Wave 1 completa)
  Plan 4 (Dominio)  ──┬──> Plan 7 (min_age_years para ReservationService)
  Plan 5 (Settings)   │
  Plan 6 (Adopciones) │   (Plan 6 es independiente — puede hacerse en Wave 1)
                        │
WAVE 3 — Código & Tests (requiere Wave 2 completa)
  Plan 7 (TDD) ────────┬──> Plan 8, Plan 10
                        │
WAVE 4 — Contenido & Completitud (requiere Wave 3)
  Plan 8 (UX)
  Plan 9 (Brand-Book) — SIEMPRE EL ÚLTIMO
  Plan 10 (Pases + Formulario) — depende de Plan 2, 4 y 7
```

**Paralelos posibles:**

- Wave 1: Plan 1 ‖ Plan 2 ‖ Plan 6
- Wave 2: Plan 3 ‖ Plan 4 ‖ Plan 5 (tras completar Plan 1)
- Wave 4: Plan 8 ‖ Plan 10 (Plan 9 siempre al final)

---

## Planes completados y eliminados

_Ninguno aún._
