# ADR 001 — Arquitectura HDA (Hypermedia-Driven Application)

**Estado:** Aceptado  
**Fecha:** 2026-04-25  
**Autores:** Equipo Komorebi

---

## Contexto

Antes de esta decisión, la capa de presentación seguía un patrón SPA parcial:

- Los controladores PHP servían páginas con variables de vista mínimas.
- Las vistas delegaban a Alpine.js la obtención de datos mediante `fetch()` en `init()`.
- Cada componente Alpine mantenía colecciones completas en estado reactivo (`cafes`, `passes`, `reservations`, etc.).
- El resultado era una arquitectura híbrida: renderizado de servidor para la estructura, cliente para los datos.

**Problemas concretos observados:**

1. Doble fuente de verdad — PHP y Alpine podían divergir.
2. Cascadas de peticiones en el arranque de la página.
3. Tests de integración que pasaban pero cuyo comportamiento de producción dependía de llamadas API no testeadas.
4. Alpine como capa de datos, no como capa de comportamiento — violando su diseño declarativo.
5. Los endpoints GET del API (`/api/v1/cafes`, `/api/v1/passes`) acumulaban lógica de proyección para el cliente.

---

## Decisión

Adoptamos el patrón **HDA (Hypermedia-Driven Application)**, inspirado en los principios de Roy Fielding (HATEOAS) y los ensayos de Carson Gross sobre htmx.

La premisa central: **el servidor es la única fuente de verdad del estado de la aplicación**. El cliente solo gestiona comportamiento de UI efímero (animaciones, modales, feedback de mutaciones).

### Los 5 invariantes

| # | Invariante | Formulación |
|---|-----------|-------------|
| 1 | PHP como fuente única de datos | `View::render()` inyecta todos los datos de colección. Alpine no hace `fetch()` para datos estáticos de página. |
| 2 | URL = estado de la aplicación | Cada paso del wizard es una URL propia. Recargar la página devuelve el mismo estado. |
| 3 | Alpine = comportamiento UI, no datos | Alpine gestiona: modales, stepper, validación inline, feedback optimista. No gestiona colecciones de dominio. |
| 4 | API REST = mutaciones y consultas reactivas | Los endpoints GET del API solo responden a input del usuario en tiempo real (slots disponibles, clima, festivos). Los POST/PATCH/DELETE son mutaciones de dominio. |
| 5 | AJAX solo para consultas reactivas | `fetch()` está permitido únicamente cuando la consulta depende de input del usuario que cambia después de la carga de página (fecha → slots, búsqueda → resultados). |

---

## Consecuencias para el código

### Lo que cambia

- Los controladores PHP pasan a PHP-inject colecciones completas vía `View::render()`.
- El wizard de reservas usa el patrón PRG (Post/Redirect/Get) con estado en sesión PHP.
- Las vistas de pasos intermedios (paso-2, paso-3) son respuestas GET independientes con su propia URL.
- `Alpine.data('reservaForm', config)` recibe `config` como objeto PHP-serializado, no lo obtiene por AJAX.

### Lo que permanece AJAX (Invariante 5)

- `GET /api/v1/time-slots/available` — reactivo a la fecha elegida por el usuario.
- `GET /api/v1/weather` — reactivo a la fecha.
- `GET /api/v1/holidays/{fecha}` — reactivo a la fecha.
- `GET /api/v1/user/reservations` — historial del usuario autenticado (mutable, cargado asíncronamente para no bloquear el paso 1).
- `POST /api/v1/reservations/{id}/cancel` — mutación.

### Lo que deja de ser AJAX

- Catálogo de cafés (`cafes`) — inyectado por PHP en el controlador.
- Catálogo de pases (`passes`) — inyectado por PHP en el controlador.
- Cualquier colección que no cambia en respuesta a input del usuario durante la sesión de página.

---

## Alternativas rechazadas

### BFF (Backend-for-Frontend)

Crear un servicio intermedio que agrega y proyecta datos para cada vista cliente.

**Rechazado porque:**
- Añade una capa de red y un proceso extra para un equipo pequeño.
- El problema raíz (doble fuente de verdad) no desaparece — solo se mueve.
- PHP con `View::render()` ya es el BFF natural de esta arquitectura.

### Full SSR sin JavaScript

Eliminar Alpine.js completamente y usar PHP + formularios HTML puros.

**Rechazado porque:**
- Los slots disponibles requieren reactivity real (el usuario elige fecha → necesita ver slots sin recargar).
- El historial de reservas se carga de forma no bloqueante intencionadamente.
- Los modales de confirmación y feedback de cancelación mejoran la UX sin ser datos de dominio.
- Alpine.js en modo comportamiento (sin datos de dominio) es correcto y ligero.

### SPA (React / Vue)

Reescribir la capa de presentación como aplicación de página única.

**Rechazado porque:**
- El proyecto no requiere navegación sin recarga completa.
- Introduce build tooling, gestión de estado global, y duplicación de lógica de negocio en el cliente.
- Las vistas de administración son CRUD estándar que se benefician más de SSR que de SPA.

---

## Referencias

- Roy Fielding, "Architectural Styles and the Design of Network-based Software Architectures" (2000), cap. 5 (REST/HATEOAS)
- Carson Gross, "Hypermedia Systems" (2023)
- Plan de migración: `docs/plans/2026-04-25-ssr-modales.md`
