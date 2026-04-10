---
description: "Reglas de invocación de Skills para cualquier tarea en este proyecto. Consultar antes de responder o actuar. Define qué skill invocar según el tipo de tarea."
applyTo: "**"
---

# Workflow de Skills — Komorebi Café

La tabla completa de skills y su contexto en el proyecto está en `AGENTS.md` → sección **Skills de IA Disponibles**.

## Regla de oro

> Cuando haya ≥ 1 % de probabilidad de que una skill aplique, **invócala**. Sin excepciones.

---

## Flujo obligatorio por tipo de tarea

### Feature nueva / componente / cambio de comportamiento

1. `brainstorming` — explorar requisitos y diseño (sin código hasta aprobación)
2. `writing-plans` — crear plan en `docs/plans/` tras aprobación del diseño
3. `test-driven-development` — escribir tests ANTES del código de producción
4. `verification-before-completion` — ejecutar evidencia real antes de declarar completado
5. `requesting-code-review` → `finishing-a-development-branch` — antes de merge

### Bug / test fallido / comportamiento inesperado

1. `systematic-debugging` — antes de proponer cualquier fix
2. `test-driven-development` — reproducir el fallo con un test
3. `verification-before-completion` — confirmar corrección con evidencia

### Vistas / UI nueva o rediseñada (`resources/views/`)

- Vistas con lógica visual rica (Alpine.js, animaciones, layouts) → `frontend-design`
- Dashboards y paneles admin/operativos (backoffice, KDS, recepción, keeper) → `interface-design`

### API REST (nuevas rutas o modificaciones)

- Revisar `docs/openapi.yaml` e invocar `api-design-principles` antes de escribir código

### Ciclo de rama

| Momento                         | Skill                            |
| ------------------------------- | -------------------------------- |
| Completar implementación        | `verification-before-completion` |
| Antes de merge / PR             | `requesting-code-review`         |
| Al recibir feedback de revisión | `receiving-code-review`          |
| Decisión de integración / PR    | `finishing-a-development-branch` |

### Tareas paralelas o desglosadas

| Situación                                        | Skill                         |
| ------------------------------------------------ | ----------------------------- |
| Plan con 3+ tareas independientes en esta sesión | `subagent-driven-development` |
| 2+ tareas sin estado compartido simultáneas      | `dispatching-parallel-agents` |

### Utilidades

| Situación                                                  | Skill                 |
| ---------------------------------------------------------- | --------------------- |
| Inicio de conversación / sesión de trabajo                 | `using-superpowers`   |
| Crear o editar `SKILL.md`                                  | `writing-skills`      |
| Buscar una skill instalable para una necesidad nueva       | `find-skills`         |
| Comportamiento inesperado del agente o tools               | `troubleshoot`        |
| Crear/editar `.instructions.md`, `.prompt.md`, `AGENTS.md` | `agent-customization` |

---

## Señales de alerta (STOP — estás racionalizando)

- "Esto es demasiado simple para un skill" → Los proyectos simples son donde los supuestos no examinados causan más trabajo perdido.
- "Solo necesito more contexto primero" → La comprobación de skills viene **antes** de buscar contexto.
- "Ya conozco esta skill" → Las skills evolucionan. Lee la versión actual.
- "Déjame explorar el código primero" → Las skills te dicen **cómo** explorar.

---

## Dónde viven los archivos de skills

| Tipo              | Ubicación                          | Registrado en      |
| ----------------- | ---------------------------------- | ------------------ |
| Proyecto          | `.agents/skills/<nombre>/SKILL.md` | `skills-lock.json` |
| Usuario/global    | `~/.agents/skills/`                | No en el lock file |
| Extensión VS Code | Extensión GitHub Copilot Chat      | No en el lock file |
