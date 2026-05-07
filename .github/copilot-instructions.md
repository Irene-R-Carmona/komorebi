# GitHub Copilot — Instrucciones Globales — Komorebi Café

Proyecto: **Komorebi Café** — PHP 8.4 MVC custom (sin Laravel/Symfony), Docker, PSR-7/PSR-15.

## Regla de oro

Antes de cualquier acción, consulta `.github/instructions/ai-workflow.instructions.md`.
Cuando haya ≥ 1 % de probabilidad de que una skill aplique → **invócala sin excepciones**.

## Contexto del proyecto

- Framework MVC custom en PHP 8.4 — **sin** Laravel, **sin** Symfony como app layer
- Todos los comandos se ejecutan **dentro del contenedor**: `docker compose exec app <cmd>` o `make <target>`
- Arquitectura 12-Factor: config desde env vars, logs a stdout
- Tests: PHPUnit en `tests/Unit/` y `tests/Integration/`
- Vistas: `resources/views/` con Alpine.js para interactividad

## Patrones obligatorios (nunca omitir)

| Patrón                    | Regla                                                                                                |
|---------------------------|------------------------------------------------------------------------------------------------------|
| `declare(strict_types=1)` | **Primera línea** de cada archivo PHP                                                                |
| Imports de clases globales | `use PDO;`, `use Throwable;`, `use Override;` — **nunca** FQFN `\PDO`, `\Throwable` para clases     |
| Funciones globales con `\` | `\time()`, `\trim()`, `\array_map()` — siempre prefijo `\` para funciones nativas                   |
| `Result` pattern          | Todos los servicios devuelven `Result::ok()` / `Result::fail()` — nunca lanzan excepciones esperadas |
| `#[Override]`             | Obligatorio en **todo** método que sobreescribe o implementa interfaz (con `use Override;`)          |
| CSRF                      | Requerido en **todas** las rutas mutantes (POST/PUT/PATCH/DELETE)                                    |
| Interfaces de servicio    | Los controllers inyectan interfaces desde `app/Services/Contracts/`, no clases concretas             |
| DTOs                      | `final readonly class` en `app/Domain/DTO/`, implementan `fromArray()` + `toViewArray()`             |
| `View::render()`          | Solo acepta escalares, arrays y `Raw` — nunca objetos directamente                                   |

## MCPs disponibles en esta sesión

| MCP                                                    | Cuándo usar                                                                |
|--------------------------------------------------------|----------------------------------------------------------------------------|
| `context7` (`resolve-library-id` + `get-library-docs`) | Antes de usar cualquier API de Alpine.js, PHP 8.4, PHPUnit, Symfony, PSR-* |
| `playwright` (`pla_browser_*`)                         | Navegación, screenshots, snapshots de accesibilidad                        |
| `chrome-devtools` (`chr_*`)                            | Lighthouse, performance traces, errores JS, consola de red                 |
| `github` (`git_*`)                                     | PRs, issues, diffs, push de archivos                                       |

## Skills disponibles (invocar por nombre)

**Planificación:** `brainstorming`, `writing-plans`, `executing-plans`, `subagent-driven-development`,
`dispatching-parallel-agents`

**Desarrollo:** `test-driven-development`, `systematic-debugging`, `zoom-out`,
`improve-codebase-architecture`, `ui-ux-pro-max`, `frontend-design`,
`interface-design`, `api-design-principles`

**Calidad:** `verification-before-completion`, `requesting-code-review`, `receiving-code-review`,
`finishing-a-development-branch`

**Marca:** `branding`, `brand-visual-generator`, `logo-generator`, `svg-logo-designer`

**Límites de contexto (suite caveman):** `caveman`, `caveman-compress`, `caveman-help`, `caveman-review`

**Utilidades:** `using-superpowers`, `writing-skills`, `find-skills`, `troubleshoot`, `agent-customization`

## Claude Code Skills (sesiones Claude Code CLI — invocar con `/`)

| Skill | Comando | Cuando invocar |
|-------|---------|----------------|
| Spec-driven: crear/amend spec | `/ck:spec` | Antes de implementar feature — crea/modifica SPEC.md |
| Spec-driven: implementar | `/ck:build` | Ejecutar tareas definidas en SPEC.md |
| Spec-driven: detectar drift | `/ck:check` | Verificar que codigo cumple invariantes de SPEC.md |
| Auditoria seguridad rama | `/security-review` | Antes de PR si hay cambios en auth, rutas, inputs, CSRF |
| Simplificar codigo | `/simplify` | Tras escribir/modificar archivos — calidad y reuso |
| Inicializar CLAUDE.md | `/init` | Si no existe CLAUDE.md o necesita actualizacion |
| Review PR completo | `/review` | Antes de abrir o fusionar pull request |
| Review comprimido | `/caveman-review` | Code review rapido — una linea por problema |
| Commit comprimido | `/caveman-commit` | Generar commit message en Conventional Commits |
| Mejorar CLAUDE.md | `/claude-md-improver` | Auditar y mejorar documentacion del proyecto |
| Reducir permisos | `/fewer-permission-prompts` | Inicio de sesion — optimiza allowlist con historial |
| Tareas recurrentes | `/loop` | Ejecutar prompt en intervalo (ej. monitorear build) |
| Agente programado | `/schedule` | Crear tarea cron con agente Claude Code |

> **Auto-activacion en Claude Code:** hooks en `~/.claude/hooks/` detectan contexto automaticamente.
> Session start: hints de skills relevantes al estado del repo.
> Cada prompt: sugerencias segun keywords (commit, review, security, php, spec...).

## Prompts reutilizables disponibles (`/` en Copilot Chat)

| Prompt                 | Uso                                                        |
|------------------------|------------------------------------------------------------|
| `/nueva-feature`       | Flujo completo: brainstorming → plan → TDD → review        |
| `/debug-bug`           | Debugging sistemático con evidencia antes de cualquier fix |
| `/nuevo-controller`    | Scaffolding de controller PHP con todos los patrones       |
| `/nuevo-service`       | Scaffolding de service + interface + Result pattern        |
| `/nuevo-repository`    | Scaffolding de repository extendiendo AbstractRepository   |
| `/nueva-migracion`     | Plantilla de migración SQL numerada                        |
| `/nuevo-componente-ui` | Componente de vista con ui-ux-pro-max + Lighthouse audit   |
| `/finalizar-rama`      | Verificación + PR siguiendo Definition of Done             |
| `/revision-codigo`     | Code review estructurado con checklist                     |
