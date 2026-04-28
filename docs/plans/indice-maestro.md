# Índice Maestro — Komorebi Café (Prioridades Operativas)

**Fecha:** 28 de abril de 2026
**Estado:** índice actualizado — P6-rem implementación completa pendiente verificación final
**Criterio:** primero arranque estable en Railway, después seguridad, luego hardening, deuda técnica y rendimiento.

---

## Propósito

Este índice define el orden de ejecución vigente de los planes del proyecto.
La prioridad se asigna por impacto real en producción, no por afinidad técnica.

---

## Mapa de Prioridades

| Nivel | Objetivo | Plan principal | Estado |
|------|----------|----------------|--------|
| P0 | Railway listo para producción (arranque, persistencia, env, workers) | `2026-04-20-railway-readiness.md` | ✅ Completo |
| P1 | Seguridad activa antes de tráfico real | `2026-04-17-business-rules-hardening.md` (Sprint 0-2) | ✅ Completo |
| P2 | Hardening funcional de reglas de negocio | `2026-04-17-business-rules-hardening.md` (Sprint 3-5) | ✅ Completo |
| P3 | Observabilidad y deuda técnica controlada | `2026-04-15-infra-calidad-integral.md` (C, D, E) | 🟡 En implementación parcial |
| P4 | Optimización de rendimiento y stack | `2026-04-15-frankenphp-stack-optimization.md` | ✅ Implementación completa |

---

## Planes Activos Necesarios

| Prioridad | Plan | Archivo | Estado actual | Alcance inmediato |
|-----------|------|---------|---------------|-------------------|
| P0 | Railway Readiness | ~~`docs/plans/2026-04-20-railway-readiness.md`~~ | ✅ Completado y eliminado (2026-04-20) | R1-R10 + validación mínima |
| P1 | Business Rules Hardening (Seguridad) | `docs/plans/2026-04-17-business-rules-hardening.md` | ✅ Completo | S0, S1, S2 |
| P2 | Business Rules Hardening (Calidad funcional) | `docs/plans/2026-04-17-business-rules-hardening.md` | ✅ Completo | S3, S4, S5 |
| P3 | Infra + Calidad Integral | `docs/plans/2026-04-15-infra-calidad-integral.md` | 🟡 En implementación | A5 + C1-C3 + D1-D5 + E1-E2 |
| P4 | FrankenPHP + Stack Optimization | `docs/plans/2026-04-15-frankenphp-stack-optimization.md` | ✅ Implementación completa | Todas las fases ejecutadas |

---

## Planes de Calidad y Testing

| Prioridad | Plan | Archivo | Estado actual | Alcance inmediato |
|-----------|------|---------|---------------|-------------------|
| P3b | Fix bugs diseño controllers | `docs/plans/2026-04-20-fix-controller-design-bugs.md` | ✅ Completo | Fases 1-5 (13 archivos) |
| P3c | Cobertura de tests al 85% | `docs/plans/2026-04-22-cobertura-85-porciento.md` | 🟡 En progreso — **40.44%** (6613/16353 stmts); 1703 tests OK; A.3 ✅, C parcial 6/19 repos ✅ (13 pendientes); gap restante: 7288 stmts | Fase C restante (13 repos) → Fase B (modelos) → D → E → F |
| P3d | Testing quality improvements | `docs/plans/2026-04-21-testing-quality-improvements.md` | ✅ Completado — FASE 1 ✅, FASE 2 ✅, FASE 3 ✅, FASE 4 ❌ N/A (PHPUnit 13) | — |

## Planes de Infraestructura y CI/CD

| Prioridad | Plan | Archivo | Estado actual | Alcance inmediato |
|-----------|------|---------|---------------|-------------------|
| P0-infra | Railway Deploy + GitHub Actions Fix | `docs/plans/2026-04-20-railway-deploy-github-actions.md` | 🟢 Código completado — pendiente pasos manuales Railway/GitHub (Fases 2, 3.1, 3.3, 4) | Fases 2 y 4 (Railway Dashboard + primer deploy) |

---

## Planes de Soporte (No Bloqueantes)

| Plan | Archivo | Estado | Cuándo ejecutar |
|------|---------|--------|-----------------|
| Preparación Entorno Inicial | `docs/plans/2026-04-17-preparacion-entorno-inicial.md` | 🔵 Plan creado — pendiente inicio | Onboarding o reinstalación completa |
| QoL Holístico | `docs/plans/2026-04-14-qol-holistic-sprint.md` | 🟢 Implementación completa — pendiente verificación | Solo si se reabre alcance UI |
| Brand Visual Unification | `docs/plans/2026-04-14-brand-visual-unification.md` | 🟢 Implementación completa — pendiente verificación | Solo si se reabre alcance UI |
| UI/UX Vistas Públicas | `docs/plans/2026-04-13-uiux-vistas-publicas.md` | 🟢 Implementación completa — pendiente verificación | Solo si se detecta regresión |
| Seguridad Datos DTO | `docs/plans/2026-04-13-seguridad-datos-dto.md` | 🟢 Implementación completa — pendiente verificación | Verificación final de cierre |

---

## Plan Arquitectura HDA

| Prioridad | Plan | Archivo | Estado actual | Alcance |
|---|---|---|---|---|
| P5-arq | Migración SSR con Modales — HDA | `docs/plans/2026-04-25-ssr-modales.md` | 🔵 Plan creado — pendiente inicio | F0 (bug fixes) → F1 (infraestructura) → F2 (prototipo) → F3-F4 (CRUD panels) → F5 (wizard) → F6 (docs) |

## Plan API-first

| Prioridad | Plan | Archivo | Estado actual | Alcance |
|---|---|---|---|---|
| P5 | API-first + REST Standards (v5) | `docs/plans/2026-04-22-apifirst.md` | 🟡 En implementación | Fases 0–0E ✅, FASE 1 ✅, FASE 2 ✅, FASE 3 ✅ verificadas (PHPStan OK, 1994/1994); pendiente FASE 4 |

## Plan Testing Layers

| Prioridad | Plan | Archivo | Estado actual | Alcance |
|---|---|---|---|---|
| P3e | Implementación capas de testing | `docs/plans/2026-04-23-testing-layers-implementation.md` | 🔵 Plan creado — pendiente inicio | Faker, E2E auth, contract tests, flujos E2E, CI job E2E |

## Plan Repo + Mapper + DTO Migration

| Prioridad | Plan | Archivo | Estado actual | Alcance |
|---|---|---|---|---|
| P3f | Repo + Mapper + DTO Migration | `docs/plans/2026-04-23-repo-mapper-dto.md` | ✅ Completado y eliminado (2026-04-24) — PHPStan OK, PHPUnit OK (1611 tests, 3186 assertions) | — |

---

## Plan Separación Arquitectura SSR / API REST

| Prioridad | Plan | Archivo | Estado actual | Alcance |
|---|---|---|---|---|
| P5-api | Separación SSR / API REST | `docs/plans/2026-04-27-architecture-separation.md` | ✅ Verificado y cerrado (2026-04-27) — Fases 1-7 + V1-V4 completos; PHPStan 0 errores (745 archivos), PHPUnit 1763 tests, 0 failures | Fases 1-7: separación multi-archivo routes, eliminación 12 rutas POST legacy, restauración grupo supervisor |

---

## Plan Remediación Auditoría Arquitectónica

| Prioridad | Plan | Archivo | Estado actual | Alcance |
|---|---|---|---|---|
| P6-rem | Remediación Auditoría Arquitectónica | `docs/plans/2026-04-28-remediacion-auditoria-arquitectonica.md` | � Implementación completa — pendiente verificación final — F0–F8 commiteados (`9e98721`); T8.5 CI opcional pendiente | F0–F8 completos; pendiente: `make phpstan` + `make test-unit` + `make cs-check` finales; T8.5 (docker-compose.test.yml MySQL ephemeral) opcional |

---

## Planes Cerrados / Históricos

| Plan | Archivo | Estado |
|------|---------|--------|
| Refuerzo Pre-defensa TFG | `docs/plans/2026-04-15-refuerzo-predefensa-tfg.md` | ✅ Verificado y cerrado |
| Principios Arquitectónicos | `docs/plans/2026-04-12-principios-arquitectonicos.md` | ✅ Verificado y cerrado |
| Cierre Gaps Arquitectónicos | `docs/plans/2026-04-13-cierre-gaps-arquitectonicos.md` | ✅ Verificado y cerrado |

---

## Orden de Ejecución Recomendado

```text
Sprint 1: P0 completo (Railway readiness)
Sprint 2: P1 completo (seguridad)
Sprint 3: P2 parcial (Sprint 3-4) + deuda crítica P3
Sprint 4: P2 cierre (Sprint 5) + P3 cierre
Sprint 5+: P4 (optimización de rendimiento)
```

---

## Regla de Mantenimiento del Índice

Cuando una tarea/fase cambie de estado:

1. Marcar el checklist en su plan origen.
2. Actualizar su estado en este índice con el semáforo oficial:

- 🔵 Plan creado — pendiente inicio
- 🟡 En implementación
- 🟢 Implementación completa — pendiente verificación
- ✅ Verificado y cerrado

1. Si un plan termina y se elimina, mantener aquí una entrada histórica con fecha de cierre.
