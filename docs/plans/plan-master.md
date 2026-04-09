# Plan Master — Komorebi Refactor & Quality Initiative

## Índice de Planes

| Plan | Nombre | Prioridad | Riesgo | Duración est. | Dependencias |
|------|--------|-----------|--------|---------------|--------------|
| Plan 1 | Security & Hardening | 🔴 CRÍTICA | Bajo | 2-3 días | Ninguna |
| Plan 2 | PSR-7 Migration (UserController + AnimalController) | 🔴 CRÍTICA | Medio | 2-3 días | Ninguna |
| Plan 3 | Static Services → Injectable (ContextService + NavigationService) | 🟠 ALTA | Alto | 3-4 días | Plan 2 |
| Plan 4 | ReservationService → Repository delegation | 🟠 ALTA | Bajo | 1-2 días | Ninguna |
| Plan 5 | Missing Service Tests (12 services) | 🟠 ALTA | Bajo | 3-4 días | Ninguna |
| Plan 6 | Controller Test Infrastructure (58 controllers) | 🟡 MEDIA | Bajo | 5-7 días | Plan 2 |
| Plan 7 | Keeper/AnimalController Split (SRP) | 🟡 MEDIA | Bajo | 1-2 días | Plan 2 |

## Orden de Ejecución Recomendado (TDD siempre)

```
Paralelo inicial: Plan 1 + Plan 4 + Plan 5
                  (sin dependencias, bajo riesgo)

Siguiente: Plan 2 (PSR-7 migration — crítico)

Después de Plan 2: Plan 3 (static services)
                   Plan 6 (controller tests)
                   Plan 7 (keeper split)
```

## Files de Plan Detallados

- `docs/plans/plan-01-security.md`
- `docs/plans/plan-02-psr7-migration.md`
- `docs/plans/plan-03-static-services.md`
- `docs/plans/plan-04-reservation-repos.md`
- `docs/plans/plan-05-missing-tests.md`
- `docs/plans/plan-06-controller-tests.md`
- `docs/plans/plan-07-keeper-split.md`

## Métricas de Éxito Globales

Antes del refactor:

- Seguridad: 8/10
- Tests controllers: 0/58
- Servicios sin tests: 12/42
- Archivos sin strict_types: ~30
- PSR-7 violations: 2 controllers (UserController, AnimalController)
- Static services no inyectables: 2 (ContextService, NavigationService)

Después (objetivos):

- Seguridad: 10/10 (CSP, charset, CSRF completo)
- Tests controllers: mínimo 5 (Fase 1), target 58 (Fase 2)
- Servicios sin tests: 0/42
- Archivos sin strict_types: 0
- PSR-7 violations: 0
- Static services: solo los justified (Logger, Flash, Csrf como stateless)

## Comandos de Verificación Final

```bash
# Comprobación PSR-7 violations
docker compose exec app grep -rn '\$_POST\|\$_FILES\|\$_GET\|header(\|exit;' app/Http/Controllers/ --include='*.php'

# Comprobación strict_types
docker compose exec app grep -rL 'declare(strict_types=1)' app/ --include='*.php'

# Suite completa
make ci

# Cobertura
make test-coverage
```
