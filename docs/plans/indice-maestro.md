# Índice Maestro — Komorebi Café: Plan de Cierre Pre-Defensa TFG

**Fecha:** 17 de abril de 2026
**Estado:** Índice maestro reordenado — **verificado contra código real el 17/04/2026**
**Criterio de orden:** lo que un evaluador puede reproducir o ver en < 5 minutos tiene prioridad absoluta

> ⚠️ **ALERTA CRÍTICA (verificada 17/04):** `Result.php` ya no tiene `isOk()`, `isFail()`, `getDataOr()`,
> pero **21 callers siguen usándolos** → errores fatales en runtime. S0-01 es la tarea más urgente.
> ✅ **S0-01 RESUELTO (17/04):** 21 callers corregidos — 0 ocurrencias de métodos obsoletos en `app/`.

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

| # | Tarea                                                                                                        | Archivo                                            | Estado  |
|---|--------------------------------------------------------------------------------------------------------------|----------------------------------------------------|---------|
| 1 | **T0.1** — Fix claves `$cafeData` en quiz resultado (`nombre`→`name`, `imagen`→`image_url`, etc.)            | `resources/views/public/quiz/resultado.php`        | - [x] ✅ |
| 2 | **T0.2** — Fix JS injection en modal reviews admin (usar `json_encode` en lugar de `e()` + comillas simples) | `resources/views/components/admin/review-card.php` | - [x] ✅ |
| 3 | **T0.3** — Fix KPI cards keeper dashboard (`total_animals`→`healthy`, eliminar `avg_interactions`)           | `resources/views/backoffice/keeper/dashboard.php`  | - [x] ✅ |
| 4 | **T3.1** — Vista 404: añadir enlace "Volver al inicio"                                                       | Vista 404 (localizar con grep)                     | - [x] ✅ |

**Pasos 1–4 son independientes entre sí → ejecutables en paralelo.**

---

## BLOQUE 2 — Documentación de progreso

>
> **Urgencia: ALTA.** La sección `[Unreleased]` vacía dice "este proyecto no se documenta".
> No están en ningún plan existente — son tareas manuales independientes.

| # | Acción                                                                                       | Archivo                | Estado  |
|---|----------------------------------------------------------------------------------------------|------------------------|---------|
| 5 | Rellenar `CHANGELOG.md [Unreleased]` con todo el trabajo desde 12/04 (8 planes, 20+ commits) | `CHANGELOG.md`         | - [x] ✅ |
| 6 | Actualizar `migrations/README.md`: añadir entradas 017, 018 y nota de 019 eliminada          | `migrations/README.md` | - [x] ✅ |

---

## BLOQUE 3 — Integridad de datos (BD y seeders)

>
> **Urgencia: ALTA.** Un `make db-reset` delante del tribunal puede fallar silenciosamente.
> Plan origen: `2026-04-15-infra-calidad-integral.md` — Módulos A y B

| #  | Tarea                                                                                      | Archivo                                                | Estado  |
|----|--------------------------------------------------------------------------------------------|--------------------------------------------------------|---------|
| 7  | **B1** — `016_supervisor_assignments.sql`: cambiar INT → BIGINT UNSIGNED en columnas clave | `migrations/016_supervisor_assignments.sql`            | - [x] ✅ |
| 8  | **B2** — Eliminar `019_fix_supervisor_assignments_bigint.sql` (fusionado en 016 por B1)    | `migrations/019_fix_supervisor_assignments_bigint.sql` | - [x] ✅ |
| 9  | **A2** — WaitlistSeeder: `r.name` → `r.code` en línea 63                                   | `app/Core/Seeders/WaitlistSeeder.php`                  | - [x] ✅ |
| 10 | **A3** — Prereq ReservationSeeder: añadir check `time_slots > 0`                           | `scripts/apply-db.php`                                 | - [x] ✅ |
| 11 | **A5** — Workers: investigar fallo de arranque + aplicar fix                               | `docker/supervisor.conf` + workers                     | - [ ]   |

**B1 debe completarse antes de B2. A2, A3 y A5 son independientes entre sí.**

---

## BLOQUE 4 — Tests positivos de seguridad (completar FASE 1)

>
> **Urgencia: MEDIA.** Los tests actuales verifican que los métodos existen, no que devuelven los campos correctos.
> Plan origen: `2026-04-13-seguridad-datos-dto.md`

| #  | Tarea                                                                         | Archivo                                                                | Estado  |
|----|-------------------------------------------------------------------------------|------------------------------------------------------------------------|---------|
| 12 | **TASK 1** — Test positivo `ReservationRepository::findWithOperationalData()` | `tests/Integration/Repositories/ReservationRepositorySecurityTest.php` | - [x] ✅ |
| 13 | **TASK 2** — Test positivo `ProductRepository::findWithRecipe()`              | `tests/Integration/Repositories/ProductRepositorySecurityTest.php`     | - [x] ✅ |

---

## BLOQUE 5 — UI/UX Vistas Públicas (completar FASE 3)

>
> **Urgencia: MEDIA.** Impacto visual directo en la demo.
> Plan origen: `2026-04-13-uiux-vistas-publicas.md`

| #  | Tarea                                                                     | Estado  |
|----|---------------------------------------------------------------------------|---------|
| 14 | **TASK 1** — Dark mode: filtro CSS en imágenes                            | - [x] ✅ |
| 15 | **TASK 2** — Tipografía: `text-wrap: balance` + `.line-clamp-*`           | - [x] ✅ |
| 16 | **TASK 3+4** — Cafés: `width`/`height` explícitos + `line-clamp` en cards | - [x] ✅ |
| 17 | **TASK 5** — Skeleton loader catálogo                                     | - [x] ✅ |
| 18 | **TASK 6+7** — Menú: dimensiones explícitas + `line-clamp` en productos   | - [x] ✅ |
| 19 | **TASK 8** — Menú: empty state para filtro de tipo de café                | - [x] ✅ |

---

## BLOQUE 6 — Brand Visual (completar lo que queda)

>
> **Urgencia: MEDIA.** Coherencia visual entre site público y backoffice.
> Plan origen: `2026-04-14-brand-visual-unification.md` — Fase D parcial + E3-E7

| #  | Tarea                                                                 | Estado  |
|----|-----------------------------------------------------------------------|---------|
| 20 | **Fase D** (componentes backoffice restantes)                         | - [x] ✅ |
| 21 | **E3** — Loyalty card: reemplazar gradient indigo por paleta de marca | - [x] ✅ |
| 22 | **E4** — Login button: reemplazar `#3B82F6` azul por `--brand-amber`  | - [x] ✅ |
| 23 | **E5-E7** — Font Awesome mezclado: eliminar referencias residuales    | - [x] ✅ |

---

## BLOQUE 7 — Calidad técnica (deuda, no bugs)

>
> **Urgencia: BAJA-MEDIA.** Visible si se muestra logs o se audita el código.
> Plan origen: `2026-04-15-infra-calidad-integral.md` — Módulos C y D

| #  | Tarea                                                                                    | Estado                                                                                               |
|----|------------------------------------------------------------------------------------------|------------------------------------------------------------------------------------------------------|
| 24 | **C1** — `scripts/apply-db.php`: emojis y `\n` literal → ASCII + Logger                  | - [x] ✅                                                                                              |
| 25 | **C2** — 7 seeders: `echo` + emojis → `Logger::*` con prefijo `[ClassName]`              | - [ ] ⚠️ Parcial: 4/9 migrados (Reservation, SystemSettings, TimeSlot, AnimalIncident); 5 pendientes |
| 26 | **C3** — `bin/quality-check.php`: emojis → marcadores ASCII                              | - [x] ✅                                                                                              |
| 27 | **D1** — AbstractRepository: añadir `execTimed()` + envolver 6 métodos CRUD              | - [x] ✅                                                                                              |
| 28 | **D2** — Logger.php: activar StreamHandler en canales `db` y `queue`                     | - [x] ✅                                                                                              |
| 29 | **D3** — WideEvent::setSection en 4 servicios clave (Reservation, Review, Auth, Loyalty) | - [x] ✅                                                                                              |
| 30 | **D4** — Logger::warning tipo B antes de Result::fail en 4 services                      | - [x] ✅                                                                                              |
| 31 | **D5** — Completar `_correlation_id` en re-push de workers                               | - [x] ✅                                                                                              |

---

## BLOQUE 8 — Business Rules Hardening (NUEVO — PRIORITARIO)

>
> **Urgencia: CRÍTICA para defensa.** El tribunal intentará romper la app.
> Plan: `2026-04-17-business-rules-hardening.md`
>
> ⚠️ **S0-01 es un BUG FATAL:** `Result::isOk()` ya no existe pero 14 controllers lo llaman.

| #  | Sprint   | Descripción                                                                               | Estado                                                                                             |
|----|----------|-------------------------------------------------------------------------------------------|----------------------------------------------------------------------------------------------------|
| G0 | Sprint 0 | Zero legacy/deprecated/alias: Result API, password fallback, model injection, #[Override] | - [x] ✅ Sprint 0 completo — S0-01..06 todos ✅                                                      |
| G1 | Sprint 1 | Seguridad HTTP + RBAC: IDOR, open redirect, CSRF, rate limit                              | - [x] ✅ (S1-02 IDOR completado en Sprint 2)                                                        |
| G2 | Sprint 2 | Validación entrada: mb_strlen, htmlspecialchars, fechas, rangos, contraseña               | - [x] ✅ Sprint 2 completo — S2-01..07 todos ✅                                                      |
| G3 | Sprint 3 | Reglas de dominio: reseña única, email verificado, stamp reversal, newsletter             | - [x] ✅ Sprint 3 completo — S3-01..14 todos ✅                                                      |
| G4 | Sprint 4 | Arquitectura: SQL en repos, lazy init, tier constants                                     | - [x] ✅ Sprint 4 completo (S4-01 parcial: AuthService migrado, resto pendiente como deuda técnica) |
| G5 | Sprint 5 | Limpieza P3: logs, rutas legacy, endpoints públicos                                       | - [x] ✅ Sprint 5 completo — S5-01..04 todos ✅                                                      |

---

## TAREAS NUEVAS — Sin plan existente (identificadas en auditoría)

| # | Acción                                                                                       | Archivo                                              | Esfuerzo | Estado                                                                  |
|---|----------------------------------------------------------------------------------------------|------------------------------------------------------|----------|-------------------------------------------------------------------------|
| A | Elevar umbrales cobertura en phpunit.xml (`lowUpperBound` ≥ 50%, `highLowerBound` ≥ 70%)     | `phpunit.xml`                                        | 10 min   | - [x] ✅                                                                 |
| B | Limpiar namespace legacy `tests/Unit/Controllers/` (migrar a `tests/Unit/Http/Controllers/`) | `tests/Unit/Controllers/`                            | 1-2h     | - [x] ✅ (directorio ya inexistente, todos los namespaces son correctos) |
| C | Crear `.github/workflows/ci.yml` (phpstan + test básico)                                     | `.github/workflows/ci.yml` (nuevo)                   | 1h       | - [x] ✅                                                                 |
| D | **QoL T0.4** — Loyalty card: eliminar dead code `$tierEmojis`                                | `resources/views/public/loyalty/card.php`            | 15 min   | - [x] ✅                                                                 |
| E | **QoL T0.5** — `onerror` fallback en imágenes + crear `placeholder.svg`                      | Vistas públicas + `public/images/ui/placeholder.svg` | 30 min   | - [x] ✅                                                                 |

---

## DEUDA TÉCNICA DOCUMENTADA — Pendiente planificación futura

| #  | Deuda                                                                                          | Archivos afectados                                                                   | Impacto | Plan futuro sugerido       |
|----|------------------------------------------------------------------------------------------------|--------------------------------------------------------------------------------------|---------|----------------------------|
| T1 | **Models lanzan `RuntimeException` para errores de negocio** — deben devolver `Result::fail()` | `Reservation.php` (×8), `Cafe.php` (×2), `Role.php`, `Permission.php`, `Tracker.php` | Medio   | `models-result-pattern.md` |
| T2 | **Models sin interfaces de contrato** — solo `User` implementa interfaz; resto son concretos   | Todos los Models excepto `User`                                                      | Bajo    | `models-contracts.md`      |

---

## Orden de ejecución recomendado

```
DÍA 1  → BLOQUE 1 (paralelo, ~2h) + BLOQUE 2 (manual, ~1h)
DÍA 2  → BLOQUE 3 (B1→B2, resto paralelo, ~2-3h) + BLOQUE 4 (~1h)
DÍA 3  → BLOQUE 5 (secuencial, ~3h) + BLOQUE 6 (~2h) + Tareas D y E
DÍA 4  → BLOQUE 8 Sprint 0+1+2 (~4h)
DÍA 5  → BLOQUE 8 Sprint 3+4+5 (~4h)
Opcional → BLOQUE 7 restante + BLOQUE 9 (FrankenPHP)
```

---

## Planes de implementación detallados

| Plan                                 | Archivo                                             | Estado                                                                                                   |
|--------------------------------------|-----------------------------------------------------|----------------------------------------------------------------------------------------------------------|
| **Deploy en Railway (Defensa TFG)**  | `2026-04-17-deploy-railway.md`                      |  Plan creado — ÚLTIMO plan, ejecutar tras verificación completa                                         |
| **Preparación Entorno Inicial**      | `2026-04-17-preparacion-entorno-inicial.md`         |  Plan creado — ejecutar para verificar Docker stack completo                                            |
| **Business Rules Hardening**         | `2026-04-17-business-rules-hardening.md`            |  Sprint 0-5 completos — verificación grep OK; phpstan/cs-check/test-unit pendientes (requieren Docker) |
| **Unificación Estilos Globales PHP** | `2026-04-17-unificacion-estilos-globales-php.md`    |  cs-fix ejecutado (262 archivos); pendiente cs-check + phpstan + DateTime manual                       |
| **Auditoría de Reglas de Negocio**   | `docs/business-rules-audit.md` (documento, no plan) |  Investigación completa — sirvió de input para Business Rules Hardening                                 |
| FASE 1 — Seguridad de Datos + DTOs   | `2026-04-13-seguridad-datos-dto.md`                 |  Implementación completa — pendiente verificación final                                                |
| FASE 3 — UI/UX Vistas Públicas       | `2026-04-13-uiux-vistas-publicas.md`                |  Implementación completa — todas las tareas ✅; pendiente verificación                                  |
| Brand Visual Unification             | `2026-04-14-brand-visual-unification.md`            |  Implementación completa — A-D ✅; E3-E7 verificación visual pendiente                                  |
| Sprint QoL Holístico                 | `2026-04-14-qol-holistic-sprint.md`                 |  Implementación completa — OLA 0 ✅, OLA 1 pendiente (T1.1-T1.3), OLA 2 ✅, OLA 3 ✅                      |
| Infra + Calidad Integral             | `2026-04-15-infra-calidad-integral.md`              |  Parcial — B1/B2 ✅, C1/C3 ✅, **C2 parcial** (5 seeders con echo), D1-D5 ✅; A4/A5/E/F pendientes Docker |
| FrankenPHP + Stack Optimization      | `2026-04-15-frankenphp-stack-optimization.md`       |  Pendiente inicio — Bloque 9 (opcional, solo si hay tiempo)                                            |
| **Consolidación Arquitectura Repos** | `2026-04-18-consolidacion-arquitectura-repositorios.md` |  BC allergen + hotfixes seguridad completos — BCs cafe/animal/user/loyalty pendientes            |
| Observabilidad — Logging (FASE 2)    | *(eliminado 17/04)*                                 | ✅ Completado y eliminado                                                                                 |
| Refuerzo Pre-Defensa TFG             | *(eliminado 17/04)*                                 | ✅ Completado y eliminado                                                                                 |
| Cierre de Gaps Arquitectónicos       | *(eliminado 17/04)*                                 | ✅ Completado y eliminado — GAPs 1-4 resueltos                                                            |
| FASE 0 — Principios Arquitectónicos  | *(eliminado 17/04)*                                 | ✅ Completado y eliminado — decisiones en `docs/ARCHITECTURE.md`                                          |
| Pre-defensa TFG                      | *(eliminado)*                                       | ✅ Completado — PHPStan L5 0 errores, PSR-12 0 violaciones                                                |
| Ecosystem Cleanup                    | *(eliminado)*                                       | ✅ Completado — commit `ecbae94`                                                                          |
