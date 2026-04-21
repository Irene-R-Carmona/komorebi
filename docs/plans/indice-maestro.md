# Índice Maestro — Komorebi Café (Prioridades Operativas)

**Fecha:** 20 de abril de 2026
**Estado:** índice actualizado por niveles de prioridad (P0-P4)
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
| P4 | Optimización de rendimiento y stack | `2026-04-15-frankenphp-stack-optimization.md` | 🔵 Plan creado — pendiente inicio |

---

## Planes Activos Necesarios

| Prioridad | Plan | Archivo | Estado actual | Alcance inmediato |
|-----------|------|---------|---------------|-------------------|
| P0 | Railway Readiness | ~~`docs/plans/2026-04-20-railway-readiness.md`~~ | ✅ Completado y eliminado (2026-04-20) | R1-R10 + validación mínima |
| P1 | Business Rules Hardening (Seguridad) | `docs/plans/2026-04-17-business-rules-hardening.md` | ✅ Completo | S0, S1, S2 |
| P2 | Business Rules Hardening (Calidad funcional) | `docs/plans/2026-04-17-business-rules-hardening.md` | ✅ Completo | S3, S4, S5 |
| P3 | Infra + Calidad Integral | `docs/plans/2026-04-15-infra-calidad-integral.md` | 🟡 En implementación | A5 + C1-C3 + D1-D5 + E1-E2 |
| P4 | FrankenPHP + Stack Optimization | `docs/plans/2026-04-15-frankenphp-stack-optimization.md` | 🔵 Plan creado | Fase 5 primero |

---

## Planes de Calidad y Testing

| Prioridad | Plan | Archivo | Estado actual | Alcance inmediato |
|-----------|------|---------|---------------|-------------------|
| P3b | Fix bugs diseño controllers | `docs/plans/2026-04-20-fix-controller-design-bugs.md` | ✅ Completo | Fases 1-5 (13 archivos) |
| P3c | Cobertura de tests al 85% | `docs/plans/2026-04-20-cobertura-85-porciento.md` | 🟡 En implementación — FASE 0 ✅, FASE 1 🟡 (804 tests OK; 7/8 nuevos servicios + 8 expansiones; pendiente: 12 items FASE 1 + FASES 2-7) | FASE 1 restante → FASE 2 (modelos) |
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
