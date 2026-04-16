# Índice Maestro — Komorebi Café: Plan de Cierre Pre-Defensa TFG

**Fecha:** 16 de abril de 2026
**Estado:** Índice maestro reordenado — prioridades aplicadas por auditoría TFG
**Criterio de orden:** lo que un evaluador puede reproducir o ver en < 5 minutos tiene prioridad absoluta

---

## Propósito de este Documento

Este índice reemplaza al índice original del 12/04/2026. Es la fuente de verdad del trabajo
pendiente desde la perspectiva de la defensa del TFG. Las tareas se agrupan en bloques
ordenados por impacto en la defensa, no por dependencia técnica.

Los planes de implementación detallados siguen existiendo en este mismo directorio.
Este índice indica **en qué orden ejecutarlos y qué tareas de cada plan son prioritarias**.

---

## BLOQUE 1 — Bugs visuales reproducibles en < 2 min
>
> **Urgencia: CRÍTICA.** Si un tribunal abre el navegador, estos son visibles de inmediato.
> Plan origen: `2026-04-14-qol-holistic-sprint.md` — OLA 0

| # | Tarea | Archivo | Estado |
|---|-------|---------|--------|
| 1 | **T0.1** — Fix claves `$cafeData` en quiz resultado (`nombre`→`name`, `imagen`→`image_url`, etc.) | `resources/views/public/quiz/resultado.php` | - [x] ✅ |
| 2 | **T0.2** — Fix JS injection en modal reviews admin (usar `json_encode` en lugar de `e()` + comillas simples) | `resources/views/components/admin/review-card.php` | - [x] ✅ |
| 3 | **T0.3** — Fix KPI cards keeper dashboard (`total_animals`→`healthy`, eliminar `avg_interactions`) | `resources/views/backoffice/keeper/dashboard.php` | - [x] ✅ |
| 4 | **T3.1** — Vista 404: añadir enlace "Volver al inicio" | Vista 404 (localizar con grep) | - [x] ✅ |

**Pasos 1–4 son independientes entre sí → ejecutables en paralelo.**

---

## BLOQUE 2 — Documentación de progreso
>
> **Urgencia: ALTA.** La sección `[Unreleased]` vacía dice "este proyecto no se documenta".
> No están en ningún plan existente — son tareas manuales independientes.

| # | Acción | Archivo | Estado |
|---|--------|---------|--------|
| 5 | Rellenar `CHANGELOG.md [Unreleased]` con todo el trabajo desde 12/04 (8 planes, 20+ commits) | `CHANGELOG.md` | - [x] ✅ |
| 6 | Actualizar `migrations/README.md`: añadir entradas 017, 018 y nota de 019 eliminada | `migrations/README.md` | - [x] ✅ |

---

## BLOQUE 3 — Integridad de datos (BD y seeders)
>
> **Urgencia: ALTA.** Un `make db-reset` delante del tribunal puede fallar silenciosamente.
> Plan origen: `2026-04-15-infra-calidad-integral.md` — Módulos A y B

| # | Tarea | Archivo | Estado |
|---|-------|---------|--------|
| 7 | **B1** — `016_supervisor_assignments.sql`: cambiar INT → BIGINT UNSIGNED en columnas clave | `migrations/016_supervisor_assignments.sql` | - [x] ✅ |
| 8 | **B2** — Eliminar `019_fix_supervisor_assignments_bigint.sql` (fusionado en 016 por B1) | `migrations/019_fix_supervisor_assignments_bigint.sql` | - [x] ✅ |
| 9 | **A2** — WaitlistSeeder: `r.name` → `r.code` en línea 63 | `app/Core/Seeders/WaitlistSeeder.php` | - [x] ✅ |
| 10 | **A3** — Prereq ReservationSeeder: añadir check `time_slots > 0` | `scripts/apply-db.php` | - [x] ✅ |
| 11 | **A5** — Workers: investigar fallo de arranque + aplicar fix | `docker/supervisor.conf` + workers | - [ ] |

**B1 debe completarse antes de B2. A2, A3 y A5 son independientes entre sí.**

---

## BLOQUE 4 — Tests positivos de seguridad (completar FASE 1)
>
> **Urgencia: MEDIA.** Los tests actuales verifican que los métodos existen, no que devuelven los campos correctos.
> Plan origen: `2026-04-13-seguridad-datos-dto.md`

| # | Tarea | Archivo | Estado |
|---|-------|---------|--------|
| 12 | **TASK 1** — Test positivo `ReservationRepository::findWithOperationalData()` | `tests/Integration/Repositories/ReservationRepositorySecurityTest.php` | - [ ] |
| 13 | **TASK 2** — Test positivo `ProductRepository::findWithRecipe()` | `tests/Integration/Repositories/ProductRepositorySecurityTest.php` | - [ ] |

---

## BLOQUE 5 — UI/UX Vistas Públicas (completar FASE 3)
>
> **Urgencia: MEDIA.** Impacto visual directo en la demo.
> Plan origen: `2026-04-13-uiux-vistas-publicas.md`

| # | Tarea | Estado |
|---|-------|--------|
| 14 | **TASK 1** — Dark mode: filtro CSS en imágenes | - [x] ✅ |
| 15 | **TASK 2** — Tipografía: `text-wrap: balance` + `.line-clamp-*` | - [x] ✅ |
| 16 | **TASK 3+4** — Cafés: `width`/`height` explícitos + `line-clamp` en cards | - [x] ✅ |
| 17 | **TASK 5** — Skeleton loader catálogo | - [x] ✅ |
| 18 | **TASK 6+7** — Menú: dimensiones explícitas + `line-clamp` en productos | - [x] ✅ |
| 19 | **TASK 8** — Menú: empty state para filtro de tipo de café | - [x] ✅ |

---

## BLOQUE 6 — Brand Visual (completar lo que queda)
>
> **Urgencia: MEDIA.** Coherencia visual entre site público y backoffice.
> Plan origen: `2026-04-14-brand-visual-unification.md` — Fase D parcial + E3-E7

| # | Tarea | Estado |
|---|-------|--------|
| 20 | **Fase D** (componentes backoffice restantes) | - [x] ✅ |
| 21 | **E3** — Loyalty card: reemplazar gradient indigo por paleta de marca | - [x] ✅ |
| 22 | **E4** — Login button: reemplazar `#3B82F6` azul por `--brand-amber` | - [x] ✅ |
| 23 | **E5-E7** — Font Awesome mezclado: eliminar referencias residuales | - [x] ✅ |

---

## BLOQUE 7 — Calidad técnica (deuda, no bugs)
>
> **Urgencia: BAJA-MEDIA.** Visible si se muestra logs o se audita el código.
> Plan origen: `2026-04-15-infra-calidad-integral.md` — Módulos C y D

| # | Tarea | Estado |
|---|-------|--------|
| 24 | **C1** — `scripts/apply-db.php`: emojis y `\n` literal → ASCII + Logger | - [ ] |
| 25 | **C2** — 7 seeders: `echo` + emojis → `Logger::*` con prefijo `[ClassName]` | - [ ] |
| 26 | **C3** — `bin/quality-check.php`: emojis → marcadores ASCII | - [ ] |
| 27 | **D1** — AbstractRepository: añadir `execTimed()` + envolver 6 métodos CRUD | - [ ] |
| 28 | **D2** — Logger.php: activar StreamHandler en canales `db` y `queue` | - [ ] |
| 29 | **D3** — WideEvent::setSection en 4 servicios clave (Reservation, Review, Auth, Loyalty) | - [ ] |
| 30 | **D4** — Logger::warning tipo B antes de Result::fail en 4 services | - [ ] |
| 31 | **D5** — Completar `_correlation_id` en re-push de workers | - [ ] |

---

## BLOQUE 8 — FrankenPHP + Stack Optimization (argumento de rendimiento)
>
> **Urgencia: BAJA.** Impacto narrativo alto si se activa Worker Mode para demo.
> Plan origen: `2026-04-15-frankenphp-stack-optimization.md`
> Ejecutar solo si el tiempo lo permite. FASE 5 primero, el resto es opcional.

| # | Tarea | Esfuerzo | Estado |
|---|-------|----------|--------|
| 32 | **FASE 0.7** — Migración CHECK Constraints + índices compuestos (`020_integrity_indexes.sql`) | Bajo | - [ ] |
| 33 | **FASE 5** — Activar Worker Mode (`frankenphp_handle_request=1`) | Medio-alto | - [ ] |
| 34 | **FASE 7.2** — Redis Streams en lugar de LISTS (jobs con ACK) | Alto | - [ ] |
| 35 | Resto FASES 0-4, 6, 7.1, 7.3 | Varios | - [ ] |

---

## TAREAS NUEVAS — Sin plan existente (identificadas en auditoría)

| # | Acción | Archivo | Esfuerzo | Estado |
|---|--------|---------|----------|--------|
| A | Elevar umbrales cobertura en phpunit.xml (`lowUpperBound` ≥ 50%, `highLowerBound` ≥ 70%) | `phpunit.xml` | 10 min | - [x] ✅ |
| B | Limpiar namespace legacy `tests/Unit/Controllers/` (migrar a `tests/Unit/Http/Controllers/`) | `tests/Unit/Controllers/` | 1-2h | - [ ] (parcial — 8 archivos pendientes: Api/V1, Manager, Supervisor) |
| C | Crear `.github/workflows/ci.yml` (phpstan + test básico) | `.github/workflows/ci.yml` (nuevo) | 1h | - [x] ✅ |
| D | **QoL T0.4** — Loyalty card: eliminar dead code `$tierEmojis` | `resources/views/public/loyalty/card.php` | 15 min | - [x] ✅ |
| E | **QoL T0.5** — `onerror` fallback en imágenes + crear `placeholder.svg` | Vistas públicas + `public/images/ui/placeholder.svg` | 30 min | - [x] ✅ |

---

## Orden de ejecución recomendado

```
DÍA 1  → BLOQUE 1 (paralelo, ~2h) + BLOQUE 2 (manual, ~1h)
DÍA 2  → BLOQUE 3 (B1→B2, resto paralelo, ~2-3h) + BLOQUE 4 (~1h)
DÍA 3  → BLOQUE 5 (secuencial, ~3h) + BLOQUE 6 (~2h) + Tareas D y E
DÍA 4  → BLOQUE 7 (paralelo por módulo) + BLOQUE 8 FASE 0.7 + Tarea A
Opcional → BLOQUE 8 FASE 5 (Worker Mode) + Tareas B y C
```

---

## Planes de implementación detallados

| Plan | Archivo | Estado |
|------|---------|--------|
| FASE 1 — Seguridad de Datos + DTOs | `2026-04-13-seguridad-datos-dto.md` | 🟡 En implementación — Bloques 4 pendientes |
| FASE 3 — UI/UX Vistas Públicas | `2026-04-13-uiux-vistas-publicas.md` | 🟡 En implementación — Bloque 5 pendiente |
| Brand Visual Unification | `2026-04-14-brand-visual-unification.md` | 🟡 En implementación — Bloque 6 pendiente |
| Sprint QoL Holístico | `2026-04-14-qol-holistic-sprint.md` | � En implementación — T3.1✅, T0.4✅, T0.5✅; pendientes T0.1/T0.2/T0.3 (OLA 0), OLAs 1-3 |
| Infra + Calidad Integral | `2026-04-15-infra-calidad-integral.md` | 🟡 En implementación — A1✅ A2✅ A3✅; pendientes B1/B2, A5, C1-C3, D1-D5 |
| FrankenPHP + Stack Optimization | `2026-04-15-frankenphp-stack-optimization.md` | 🔵 Pendiente inicio — Bloque 8 |
| Cierre de Gaps Arquitectónicos | `2026-04-13-cierre-gaps-arquitectonicos.md` | ✅ Completado — GAPs 1-4 resueltos |
| FASE 0 — Principios Arquitectónicos | `2026-04-12-principios-arquitectonicos.md` | ✅ Implementado — decisiones en `docs/ARCHITECTURE.md` |
| Pre-defensa TFG | *(eliminado)* | ✅ Completado — PHPStan L5 0 errores, PSR-12 0 violaciones |
| Ecosystem Cleanup | *(eliminado)* | ✅ Completado — commit `ecbae94` |
