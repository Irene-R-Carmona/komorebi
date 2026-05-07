---
mode: agent
description: "Flujo completo para implementar una nueva feature: brainstorming → plan → TDD → verificación → PR. Usar para cualquier feature, componente o cambio de comportamiento."
---

# Nueva Feature — Flujo Completo

Voy a implementar una nueva feature siguiendo el flujo obligatorio del proyecto.

## Información necesaria

**Feature a implementar:** ${input:feature_description:Describe la feature que quieres implementar}

## Flujo que voy a ejecutar

### Paso 1 — Brainstorming (skill obligatoria)

Invocar la skill `brainstorming` para explorar requisitos, casos de uso, impacto en el sistema
y posibles decisiones de diseño antes de tocar cualquier código.

Preguntas a responder en el brainstorming:
- ¿Qué problema resuelve exactamente?
- ¿Qué módulos/capas del proyecto toca? (Controller, Service, Repository, View, Event, Job)
- ¿Hay casos de borde o estados de error que manejar?
- ¿Requiere migración SQL nueva?
- ¿Afecta a la API REST? (revisar `docs/openapi.yaml`)
- ¿Necesita tests unitarios y/o de integración?
- ¿Hay librerías involucradas que necesiten consultar Context7?

### Paso 2 — Plan de implementación

Tras aprobación del brainstorming, invocar `writing-plans` para crear el plan en `docs/plans/`.

Si la feature tiene 3+ tareas independientes: usar `subagent-driven-development`.
Si hay 2+ tareas sin estado compartido: usar `dispatching-parallel-agents`.

### Paso 3 — TDD

Invocar `test-driven-development`. Tests ANTES del código de producción:
- Unit tests en `tests/Unit/` (sin DB, usando `createStub()`)
- Integration tests en `tests/Integration/` (si toca BD real)

### Paso 4 — Implementación

Seguir los patrones obligatorios del proyecto:
- `declare(strict_types=1)` en cada archivo PHP
- Services devuelven `Result::ok()` / `Result::fail()` 
- Controllers inyectan interfaces de `app/Services/Contracts/`
- CSRF en todas las rutas mutantes
- `#[\Override]` en todos los métodos que implementan/sobreescriben
- `View::render()` solo recibe escalares/arrays/Raw — usar `$dto->toViewArray()`

Si hay vistas → invocar `ui-ux-pro-max` (primaria para TODO trabajo visual).

### Paso 5 — Verificación

Invocar `verification-before-completion`:
```bash
make test-unit
make phpstan
make cs-check
```
Si hay vistas nuevas: auditar con `chr_lighthouse_audit`.

### Paso 6 — PR

Invocar `requesting-code-review` y luego `finishing-a-development-branch`.

---
**Referencia:** `.github/instructions/ai-workflow.instructions.md` | `AGENTS.md`

