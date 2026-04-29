---
description: "Reglas de invocación de Skills, MCPs y Subagentes para cualquier tarea en este proyecto. Consultar antes de responder o actuar."
applyTo: "**"
---

# Workflow de Skills — Komorebi Café

La tabla completa de skills y su contexto en el proyecto está en `AGENTS.md` → sección **Skills de IA Disponibles**.
La tabla completa de MCPs disponibles está en `AGENTS.md` → sección **MCP Servers Configurados**.

## Regla de oro

> Cuando haya ≥ 1 % de probabilidad de que una skill aplique, **invócala**. Sin excepciones.

---

## Flujo obligatorio por tipo de tarea

### Feature nueva / componente / cambio de comportamiento

1. `brainstorming` — explorar requisitos y diseño (sin código hasta aprobación)
2. `writing-plans` — crear plan en `docs/plans/` tras aprobación del diseño
    - Si la tarea es extensa o requiere investigación autónoma → usar **subagente `Plan`** antes de `writing-plans`
3. `test-driven-development` — escribir tests ANTES del código de producción
4. `verification-before-completion` — ejecutar evidencia real antes de declarar completado
5. `requesting-code-review` → `finishing-a-development-branch` — antes de merge

### Bug / test fallido / comportamiento inesperado

1. `systematic-debugging` — antes de proponer cualquier fix
    - Usar **`chrome-devtools` MCP** (`chr_*`) para auditorías Lighthouse, consola de errores JS y trazas de red
    - Usar **`playwright` MCP** (`pla_browser_*`) para capturar estado visual del bug
2. `test-driven-development` — reproducir el fallo con un test
3. `verification-before-completion` — confirmar corrección con evidencia

### Vistas / UI nueva o rediseñada (`resources/views/`)

- **Cualquier tarea visual** (colores, tipografía, espaciado, dark mode, accesibilidad, placeholders, iconos) →
  `ui-ux-pro-max` (skill primaria)
- Vistas con lógica interactiva compleja (Alpine.js, animaciones CSS custom, micro-interacciones) → `frontend-design`
- Dashboards y paneles admin/operativos (backoffice, KDS, recepción, keeper) → `interface-design`
- Tras implementar cualquier vista → auditar con **`chrome-devtools` MCP** (`chr_lighthouse_audit`)

### Marca e identidad visual

| Necesidad                                                   | Skill                    |
|-------------------------------------------------------------|--------------------------|
| Estrategia de marca (propósito, valores, voz, narrativa)    | `branding`               |
| Identidad visual (colores, tipografía, tokens, guía estilo) | `brand-visual-generator` |
| Colocar o ajustar logo en vistas web                        | `logo-generator`         |
| Crear logos o iconos vectoriales SVG                        | `svg-logo-designer`      |

### API REST (nuevas rutas o modificaciones)

- Revisar `docs/openapi.yaml` e invocar `api-design-principles` antes de escribir código
- Consultar **`context7` MCP** para documentación actualizada de PSR-7, PSR-15 o librerías usadas

### Consulta de documentación de librerías

Usar **`context7` MCP** (`resolve-library-id` → `get-library-docs`) ANTES de usar APIs de:

- Alpine.js, PHP 8.4, PHPUnit, Symfony EventDispatcher, Symfony Console
- PSR-7, PSR-14, PSR-15, cualquier dependencia de `composer.json`

### Ciclo de rama

| Momento                         | Skill (Copilot)                  | Skill (Claude Code CLI)  |
|---------------------------------|----------------------------------|--------------------------|
| Completar implementación        | `verification-before-completion` | `/simplify`              |
| Antes de merge / PR             | `requesting-code-review`         | `/security-review`       |
| Abrir / revisar PR              | `requesting-code-review`         | `/review`                |
| Al recibir feedback de revisión | `receiving-code-review`          | `/caveman-review`        |
| Decisión de integración / PR    | `finishing-a-development-branch` | —                        |

### Tareas paralelas o desglosadas

| Situación                                        | Skill / Herramienta           |
|--------------------------------------------------|-------------------------------|
| Investigar y desglosar tarea compleja en plan    | Subagente **`Plan`**          |
| Plan con 3+ tareas independientes en esta sesión | `subagent-driven-development` |
| 2+ tareas sin estado compartido simultáneas      | `dispatching-parallel-agents` |

### Feature con especificacion formal (spec-driven)

Usar cuando la feature tiene invariantes claros, multiples implementadores o riesgo de drift:

1. `/ck:spec` — crear/amend `SPEC.md` con goal, constraints, interfaces, invariantes, tareas
2. `/ck:build` — implementar tarea por tarea contra la spec
3. `/ck:check` — verificar drift tras cambios; si hay violaciones, volver a `/ck:spec`

### Utilidades Claude Code CLI

| Situacion                                           | Skill Claude Code        |
|-----------------------------------------------------|--------------------------|
| No existe `CLAUDE.md` o esta desactualizado         | `/init`                  |
| Documentacion del proyecto necesita mejora          | `/claude-md-improver`    |
| Muchos prompts de permiso en sesion                 | `/fewer-permission-prompts` |
| Monitorear proceso en background                    | `/loop`                  |
| Automatizar tarea recurrente                        | `/schedule`              |

### Cuándo usar cada MCP de browser

| Necesidad                                            | MCP                      | Tool prefix                                              |
|------------------------------------------------------|--------------------------|----------------------------------------------------------|
| Navegar, snapshots, clicks, formularios, screenshots | `playwright` (Microsoft) | `pla_browser_*`                                          |
| Lighthouse (accesibilidad, SEO, best practices)      | `chrome-devtools`        | `chr_lighthouse_audit`                                   |
| Performance traces, Core Web Vitals (LCP, INP, CLS)  | `chrome-devtools`        | `chr_performance_*`                                      |
| Heap snapshots, análisis de memoria                  | `chrome-devtools`        | `chr_take_memory_snapshot`                               |
| Errores JS en consola, peticiones de red             | `chrome-devtools`        | `chr_list_console_messages`, `chr_list_network_requests` |

### Contexto ampliado / arquitectura del proyecto

| Situación                                                        | Skill / Herramienta              |
|------------------------------------------------------------------|----------------------------------|
| Desconoces un área del código — mapa de módulos y callers        | `zoom-out`                       |
| Detectar módulos superficiales y proponer refactors de profundidad | `improve-codebase-architecture` |
| Consultar glosario de dominio del proyecto                       | `CONTEXT.md` (raíz del repo)     |

> `improve-codebase-architecture` requiere que `CONTEXT.md` exista con el glosario del dominio.
> `zoom-out` usa el vocabulario de `CONTEXT.md` para nombrar módulos y callers.

### Límites de contexto / sesiones largas (suite caveman)

Cuando el contexto de la sesión es muy grande o acercas al límite de tokens:

| Situación                                                    | Skill             | Cómo activar                |
|--------------------------------------------------------------|-------------------|-----------------------------|
| Sesión larga — reducir tokens en respuestas del agente       | `caveman`         | "caveman mode"              |
| Comprimir archivo de memoria (CLAUDE.md, todos, preferencias) | `caveman-compress` | "compress [archivo]"       |
| Ver todos los comandos y modos disponibles del sistema caveman | `caveman-help`   | "caveman help"              |
| Code review con muchos archivos (contexto grande)            | `caveman-review`  | "/caveman-review"           |

> La suite caveman reduce el uso de tokens ~75% manteniendo precisión técnica completa.
> Usar `caveman-compress` **antes** de cerrar sesiones largas para que el contexto en CLAUDE.md
> sea pequeño en la siguiente sesión.

### Contexto ampliado / arquitectura del proyecto

| Situación                                                        | Skill / Herramienta              |
|------------------------------------------------------------------|----------------------------------|
| Desconoces un área del código — mapa de módulos y callers        | `zoom-out`                       |
| Detectar módulos superficiales y proponer refactors de profundidad | `improve-codebase-architecture` |
| Consultar glosario de dominio del proyecto                       | `CONTEXT.md` (raíz del repo)     |

> `improve-codebase-architecture` requiere que `CONTEXT.md` exista con el glosario del dominio.
> `zoom-out` usa el vocabulario de `CONTEXT.md` para nombrar módulos y callers.

### Límites de contexto / sesiones largas (suite caveman)

Cuando el contexto de la sesión es muy grande o acercas al límite de tokens:

| Situación                                                    | Skill             | Cómo activar                |
|--------------------------------------------------------------|-------------------|-----------------------------|
| Sesión larga — reducir tokens en respuestas del agente       | `caveman`         | "caveman mode"              |
| Comprimir archivo de memoria (CLAUDE.md, todos, preferencias) | `caveman-compress` | "compress [archivo]"       |
| Ver todos los comandos y modos disponibles del sistema caveman | `caveman-help`   | "caveman help"              |
| Code review con muchos archivos (contexto grande)            | `caveman-review`  | "/caveman-review"           |

> La suite caveman reduce el uso de tokens ~75% manteniendo precisión técnica completa.
> Usar `caveman-compress` **antes** de cerrar sesiones largas para que el contexto en CLAUDE.md
> sea pequeño en la siguiente sesión.

### Utilidades

| Situación                                                  | Skill / Herramienta   |
|------------------------------------------------------------|-----------------------|
| Inicio de conversación / sesión de trabajo                 | `using-superpowers`   |
| Crear o editar `SKILL.md`                                  | `writing-skills`      |
| Buscar una skill instalable para una necesidad nueva       | `find-skills`         |
| Comportamiento inesperado del agente o tools               | `troubleshoot`        |
| Crear/editar `.instructions.md`, `.prompt.md`, `AGENTS.md` | `agent-customization` |

---

## Señales de alerta (STOP — estás racionalizando)

- "Esto es demasiado simple para un skill" → Los proyectos simples son donde los supuestos no examinados causan más
  trabajo perdido.
- "Solo necesito más contexto primero" → La comprobación de skills viene **antes** de buscar contexto.
- "Ya conozco esta skill" → Las skills evolucionan. Lee la versión actual.
- "Déjame explorar el código primero" → Las skills te dicen **cómo** explorar.
- "No necesito Context7, conozco la API" → Las APIs evolucionan. Consulta la documentación actualizada.
- "No hace falta auditar con Lighthouse" → La accesibilidad y el rendimiento son requisitos, no opcionales.

---

## Dónde viven los archivos de skills

| Tipo              | Ubicación                          | Registrado en      |
|-------------------|------------------------------------|--------------------|
| Proyecto          | `.agents/skills/<nombre>/SKILL.md` | `skills-lock.json` |
| Usuario/global    | `~/.agents/skills/`                | No en el lock file |
| Extensión VS Code | Extensión GitHub Copilot Chat      | No en el lock file |
