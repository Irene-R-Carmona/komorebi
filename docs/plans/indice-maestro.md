# Índice Maestro — Komorebi Café: Arquitectura, Observabilidad y UI/UX

**Fecha:** 12 de abril de 2026
**Estado:** Índice maestro — los planes de implementación detallados se crean por fase a demanda
**Branches a crear por fase:** pendiente decisión

---

## Propósito de este Documento

Este índice es la fuente de verdad de todo el trabajo de mejora técnica y visual surgido del análisis
del 12/04/2026. Cada fase tiene su propio plan de implementación detallado que se crea cuando
se indica. El orden de las fases refleja la dependencia real: los principios arquitectónicos definen
cómo se implementan todas las demás.

---

## FASE 0 — Principios Arquitectónicos (fundamento conceptual)

> Debe resolverse **conceptualmente antes** de tocar código en otras fases. Define los contratos
> de capas que el resto de fases respetará. Sin este acuerdo, las fases 1-3 pueden contradecirse.

### Principios a evaluar, decidir y documentar

**Capa de datos y dominio:**

- [ ] **DTO vs arrays asociativos** — Decidir si DTOs son `readonly class` en `app/Http/DTO/`
  o si se eleva al dominio (`app/Domain/DTO/`). Implicaciones en todas las capas.
- [ ] **Value Objects** — ¿`Email`, `Slug`, `CafeRating`, `Money` como clases del dominio?
  Hoy son strings/floats sin validación de negocio encapsulada.
- [ ] **DAO vs Repository** — El `AbstractRepository` ya es el DAO. Confirmarlo y dejar claro
  que no se añade otra capa. El DAO real es el Repository.
- [ ] **Separación de campos por contexto** — Definir convención: `getSelectFields()` (presentación),
  `getAuthFields()` (operaciones de autenticación), `getOperationalFields()` (staff/backoffice).
  Esta convención afecta a UserRepository, ReservationRepository y ProductRepository.

**Flujo de datos entre capas:**

- [ ] **Contrato Repository → Service** — ¿Service recibe arrays o DTOs? Si recibe arrays,
  la responsabilidad de filtrado está en el Service. Si recibe DTOs, en el Repository o un assembler.
- [ ] **Contrato Service → Controller** — ¿`Result` envuelve un DTO o un array? Hoy envuelve arrays.
  Cambiar requiere actualizar todos los servicios que retornan `Result::ok($data)`.
- [ ] **Contrato Controller → View** — ¿Las vistas reciben DTOs directamente o `toArray()`?
  `View::escapeData()` trabaja con arrays, lo que sugiere `toArray()` o mantener arrays tipados.

**Principios de diseño adicionales:**

- [ ] **CQRS Light** — Nombrar métodos explícitamente: `find*()` para queries (sin side effects),
  verbos imperativos (`crear`, `cancelar`, `publicar`) para commands. No requiere bus.
- [ ] **Specification Pattern** — Evaluar si los filtros del catálogo (`CafeFilter`, `ProductFilter`)
  se benefician. Umbral: > 4 criterios combinados. Hoy son arrays en métodos de repositorio.
- [ ] **Interface Segregation en Services** — `UserService` y `CafeService` tienen demasiadas
  responsabilidades. Evaluar dividir: `UserProfileService`, `UserAuthService`, `UserAdminService`.
  Impacta registro en `bootstrap/container.php`.
- [ ] **Error Boundary — Excepciones vs Result** — Hoy hay mezcla: servicios devuelven `Result`,
  pero también hay `throw` en algunos casos. Definir cuándo se lanza y cuándo se devuelve `Result`.
- [ ] **API Versioning** — ¿Las rutas API ya tienen `/v1/`? Si no, añadir antes de consumidores externos.
- [ ] **Consistencia de nombres** — Revisar convención de idioma en métodos: `findById` vs `obtenerPorId`.
  Elegir uno y documentarlo.

**Corto plazo (necesario antes del primer lanzamiento público):**

- Separación de campos sensibles (UserRepository) — urgente, es seguridad
- DTO de presentación para User — urgente, evita filtración accidental

**Largo plazo (post-lanzamiento o cuando la codebase crezca):**

- Value Objects completos para dominio
- Interface Segregation en servicios grandes
- CQRS estructurado si se añade event sourcing

**Entregable de esta fase:**
Decisiones documentadas en `docs/ARCHITECTURE.md` (sección "Decisiones de Diseño") + actualización
de `app/Core/AbstractRepository.php` con la convención de métodos de selección.

---

## FASE 1 — Seguridad de Datos: Filtrado y Contratos de Capas

> Depende de las decisiones tomadas en Fase 0. Implementa los contratos acordados.

### Problemas identificados (CRÍTICO)

- **`UserRepository::getSelectFields()`** incluye `password`, `last_ip_address`, `locked_until`,
  `login_attempts` → cualquier `findById()` devuelve el hash de contraseña
- **HTML Controllers** (Admin, Public, Shared) no usan Transformers → datos brutos a vistas
- **`ReservationRepository`** expone `tracker_id`, `protocol_*`, `payment_notes` en contexto cliente
- **`ProductRepository`** expone `recipe_steps`, `ingredients_list`, `station`, `critical_check`
  fuera de contexto KDS/backoffice

### Scope de implementación

- Separar `getAuthFields()` en `UserRepository` (solo para `AuthService`)
- Crear `app/Http/DTO/`: `UserDTO`, `CafeDTO`, `ReservationDTO`, `ProductDTO`
- Extender Transformers existentes (ya disponibles en `app/Http/Transformers/`) a controllers HTML
- Añadir `getOperationalFields()` en Reservation y Product repositories
- Tests de integración que confirmen que `getSelectFields()` NO devuelve campos sensibles

**Archivos afectados:**

- `app/Repositories/UserRepository.php`
- `app/Repositories/ReservationRepository.php`
- `app/Repositories/ProductRepository.php`
- `app/Http/Controllers/Admin/UserController.php` (y otros que usen UserRepository)
- `app/Http/DTO/` (directorio nuevo)
- `tests/Integration/Repositories/UserRepositoryTest.php` (nuevo)

---

## FASE 2 — Observabilidad: Logging, Trazabilidad y Monitoreo

> No depende de Fase 0 ni Fase 1. Se puede implementar en paralelo.

### Fortalezas actuales (no tocar)

- ✅ `WideEvent` — canonical log line, un evento rico por request
- ✅ Correlation ID propagado a jobs async (`_correlation_id` en Queue payload)
- ✅ Structured logging: JSON en prod, LineFormatter en dev
- ✅ `LogContextProcessor` — contexto automático en todos los logs
- ✅ Workers con logs de startup/shutdown/retry

### Gaps a implementar

**Corto plazo (alto impacto en dev):**

- [x] **Slow Query Detection** — Decorator `LoggingPDO` en `app/Core/Database.php`
  Umbral: `DB_SLOW_QUERY_MS` (env var, default 100ms). Dev: loguea todas en debug.
- [x] **Request Body sanitizado en errores 4xx** — En `RequestLogMiddleware`, loguear body
  sin campos PII (`password`, `token`, `cvv`, `card_*`).
- [x] **Make targets dev** — `make logs-errors`, `make logs-slow`, `make logs-trace REQUEST_ID=xxx`,
  `make logs-http` — todos via `docker compose logs app | jq '...'`
- [x] **Health endpoint completo** — `public/health.php` existe pero puede ser básico.
  Expandir: `{status, db, redis, workers: {email, notifications}, version}`

**Largo plazo (observabilidad en producción):**

- [x] **Cache hit/miss en WideEvent** — Contadores en `app/Core/Cache.php` → sección `cache: {hits, misses}`
- [x] **Worker heartbeat + queue size** — Cada 60s: `[Worker] Heartbeat {queue, pending, processed, errors}`
  - `Queue::size()` logueado. Dead-letter log cuando job supera `max_retries`.
- [ ] **OpenTelemetry / Sentry** — Instrumentación externa. Es decisión de infraestructura, no de código.
  Preparar hooks (el request_id ya existe, facilita correlación con Sentry).
- [ ] **Métricas de percentiles** — `duration_ms` capturado en WideEvent pero sin p95/p99.
  Requiere agregación externa (Grafana/Loki).

**Archivos afectados:**

- `app/Core/Database.php`
- `app/Core/Middleware/RequestLogMiddleware.php`
- `app/Core/Cache.php`
- `bin/email-worker.php`, `bin/notification-worker.php`
- `public/health.php`
- `Makefile`

---

## FASE 3 — UI/UX: Vistas Públicas

> No depende de Fase 0 ni Fase 1. Se puede implementar en paralelo con Fase 2.

### Estado del sistema de diseño (sólido, no romper)

- ✅ Design tokens completos (`design-tokens.css`) — colores, espaciado, tipografía, sombras, transiciones
- ✅ Dark mode (`[data-tema="oscuro"]`) con paleta separada
- ✅ 3 fuentes japonesas (`Shippori Mincho`, `Zen Maru Gothic`, `Kaisei Decol`)
- ✅ Accesibilidad WCAG AA: focus rings, skip link, touch targets 44px, aria-live
- ✅ Alpine.js bien organizado por sección (`catalogo.js`, `menu.js`, `detalle-cafe.js`, etc.)

### Gaps por subárea

**Performance Web Vitals (corto plazo, alto impacto):**

- [ ] `fetchpriority="high"` en imagen hero del home (LCP)
- [x] `loading="lazy"` + `width`/`height` explícitos en tarjetas del catálogo (CLS = 0)
- [ ] `font-display: swap` en `@font-face` de fuentes japonesas (FOUT prevention)
- [ ] Verificar WebP/AVIF en imágenes servidas (o añadir `<picture>` con fallback)

**Dark mode — inconsistencias (corto plazo):**

- [x] Imágenes sin filtro en dark → `[data-tema="oscuro"] img { filter: brightness(0.82) saturate(0.9) }`
- [ ] Sombras usan `rgba(marrón,...)` — en dark deberían ser casi transparentes o neutras
- [ ] Focus outlines en dark (verificar contraste sobre fondos oscuros)

**Estados faltantes (corto plazo):**

- [x] Skeleton loaders en catálogo y menú mientras Alpine inicializa
- [x] Empty states con SVG + CTA cuando filtros no dan resultados
- [ ] Estado de carga optimista en formularios (botón submit con spinner)

**Tipografía (corto plazo):**

- [x] `text-wrap: balance` en H1/H2 grandes
- [x] Line-clamp en descriptions de tarjetas de café

**Micro-interacciones (largo plazo, decorativo):**

- [ ] Animación corazón en botón favoritos (scale + color con `@keyframes`)
- [ ] Slide horizontal entre preguntas del quiz
- [ ] Hover state para cards con `will-change: transform` solo durante hover (no siempre)

**Accesibilidad avanzada (largo plazo):**

- [x] `role="status"` en contador "X cafés encontrados"
- [ ] `aria-label` en step indicator del quiz
- [ ] Revisar contraste de `--color-texto-suave` (#5C4A3A) sobre `--color-fondo-alt` (#E8DCC4)

**Archivos afectados (corregir):**

- `resources/views/public/home.php` (fetchpriority, dark mode img)
- `resources/views/public/cafes/index.php` (skeleton, empty state, lazy)
- `resources/views/public/menu/index.php` (skeleton, empty state)
- `resources/views/layouts/main.php` (font-display swap si está aquí)
- `public/css/design-tokens.css` (dark mode sombras)
- `public/css/` — archivos de componentes relevantes

---

## Orden de Ejecución Recomendado

```
FASE 0 (principios y código fundacional)
  → bloquea Fase 1

FASE 1 + FASE 2 (pueden ir en paralelo)
  → Fase 1 depende de Fase 0
  → Fase 2 es independiente

FASE 3 (independiente, puede empezar en cualquier momento)
  → Recomendado: después de Fase 1 para no mezclar PRs de seguridad con PRs visuales

FASE 4 (independiente de Fases 0-3)
  → Puede ejecutarse en paralelo con cualquier fase técnica
  → No afecta a lógica de negocio ni a tests unitarios
```

---

## Planes de Implementación Detallados

| Fase | Plan | Estado |
|---|---|---|
| Fase 0 | `docs/plans/2026-04-12-principios-arquitectonicos.md` | ✅ Plan definitivo creado |
| Fase 1 | `docs/plans/2026-04-13-seguridad-datos-dto.md` | ✅ Plan creado — en implementación |
| Fase 2 | `docs/plans/2026-04-13-observabilidad-logging.md` | ✅ Plan creado — implementación completa |
| Fase 3 | `docs/plans/2026-04-13-uiux-vistas-publicas.md` | ✅ Plan creado — en implementación |
| Fase 4 | `docs/plans/2026-04-14-brand-visual-unification.md` | 🟡 En implementación — A✅ B✅(B4 pendiente) D(parcial)✅ E1+E2✅ — C y resto D pendientes |
| Sprint QoL | `docs/plans/2026-04-14-qol-holistic-sprint.md` | 🔵 Plan creado — pendiente inicio |
| Pre-defensa TFG | `docs/plans/2026-04-15-refuerzo-predefensa-tfg.md` | 🟡 En implementación — F1 ✅, F2 omitida (suite ya verde), F3 pendiente |
| Ecosystem Cleanup | ~~`docs/plans/2026-04-15-ecosystem-cleanup.md`~~ | ✅ Completado y eliminado (2026-04-15) — commit `ecbae94` |
