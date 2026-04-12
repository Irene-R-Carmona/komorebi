# AGENTS.md — Komorebi Café

Custom PHP 8.4 MVC framework (no Laravel/Symfony app layer). Everything runs inside Docker.
**All commands must be run inside the container** via `docker compose exec app <cmd>` or via `make` targets.

## Architecture Overview

```text
public/index.php          → Front controller (12-Factor bootstrap)
bootstrap/container.php   → Service Providers (register → boot lifecycle)
app/routes.php            → All route definitions (PSR-7/PSR-15)
app/Core/                 → Custom framework (Router, Container, View, DB, Cache, Queue…)
app/Http/Controllers/     → Grouped by role: Admin/, Auth/, Manager/, Reception/, Kitchen/, Keeper/, Public/, Shared/, Api/
app/Services/             → Business logic (injected via Container)
app/Repositories/         → Data access layer (extends AbstractRepository)
app/Events/ + Listeners/  → PSR-14 async events (Symfony EventDispatcher)
app/Jobs/ + Workers/      → Async queue jobs consumed by bin/email-worker.php
app/Providers/            → ServiceProviders bootstrapped in bootstrap/container.php
migrations/               → Plain SQL files (apply with scripts/apply-db.php)
resources/views/          → Templates grouped by role; layouts/ holds main, backoffice, kds, mobile, reception, errors
```

## Critical Patterns

**Every PHP file** must start with `declare(strict_types=1);`.

**Result pattern** — all service methods return `Result`, never throw for expected failures:

```php
return Result::ok($data);
return Result::fail('Mensaje', 'error_code');
// In controller:
if (!$result->ok) { Flash::error($result->getMessage()); return $this->response->redirect('/back'); }
$data = $result->data;
```

**Controller return type** — methods return `?ResponseInterface`.
Return `null` when calling `View::render()` directly (it echoes); return a `ResponseInterface` for redirects/JSON:

```php
public function show(ServerRequestInterface $request): ?ResponseInterface
{
    View::render('public/cafe/show', ['cafe' => $cafe], ['cafe.css']); // null implied
    return null;
}
public function store(ServerRequestInterface $request): ResponseInterface
{
    return $this->response->redirect('/admin/cafes');
}
```

**ResponseFactory** — inject via constructor; key methods:

```php
$this->response->redirect('/path', 302);
$this->response->json(['ok' => true], 200);
$this->response->html($htmlString, 200);
```

**XSS escaping** — `View::render()` auto-escapes all `$data` values. To pass raw HTML or JSON:

```php
'jsonData'    => Raw::json($array),   // safe JSON for Alpine x-data
'htmlSnippet' => Raw::html($safe),    // pre-sanitized HTML
// In views, use e() helper for manual escaping:
echo e($variable);
```

**Flash messages** — use semantic helpers, not `Flash::set()`:

```php
Flash::success('Guardado correctamente.');
Flash::error($result->getMessage());
Flash::warning('Quedan pocos lugares.');
Flash::info('Sesión cerrada.');
```

**Routing** — controllers are `'SubNamespace\ClassName@method'` strings resolved from `App\Http\Controllers\`:

```php
$router->get('/cafes/{slug}', 'Public\CafeController@show');
// Route groups with prefix + middleware array:
$adminMiddleware = [$mw->auth(), $mw->role('admin')];
$router->group(['prefix' => '/admin', 'middleware' => $adminMiddleware], function (Router $r) use ($mw) {
    $r->get('/dashboard', 'Admin\DashboardController@index');
    $r->post('/users', 'Admin\UserController@store', [$mw->csrf()]);
});
```

**Middleware** — use `MiddlewareFactory` (`$mw`) in `routes.php`; PSR-15 objects go in the third argument array:

```php
$mw->auth()               // requires session
$mw->role('admin')        // RBAC check (roles: admin, manager, supervisor, reception, kitchen, keeper, user)
$mw->csrf()               // CSRF validation — required on ALL mutating routes (POST/PUT/PATCH/DELETE)
$mw->guest()              // redirect if already authenticated
$mw->api()                // JSON-only API gate
$mw->rateLimit('key')     // Redis-backed rate limiting per named bucket
```

**Repositories** — extend `AbstractRepository`; must implement two abstract methods:

```php
final class CafeRepository extends AbstractRepository
{
    #[\Override] protected function getTable(): string { return 'cafes'; }
    #[\Override] protected function getSelectFields(): array { return ['id', 'slug', 'name', 'is_active']; }
    // Custom queries go here using $this->db (PDO)
}
// Obtain PDO directly: Database::getConnection()  (not Container::make(PDO::class))
```

**Async jobs** — push to Redis queue; worker processes them:

```php
Queue::push(SendEmailJob::class, ['to' => 'u@example.com', 'subject' => '…', 'body' => '…']); // queue: 'emails'
Queue::push(SomeOtherJob::class, $payload);  // queue: 'default'
```

**Events** — fire via Symfony EventDispatcher registered in `EventServiceProvider`;
add new listeners there in `boot()`:

```php
$dispatcher = Container::make(EventDispatcherInterface::class);
$dispatcher->dispatch(new UserRegisteredEvent($id, $email, $name, new DateTimeImmutable()));
```

**Logging** — always use static proxy; logs go to stdout (12-Factor XI):

```php
Logger::info('[ServiceName] context message', ['key' => $value]);
Logger::error('[ServiceName] failure', ['exception' => $e->getMessage()]);
```

**Service registration** — add new `ServiceProvider` to `$providers` list in `bootstrap/container.php`:

```php
Container::singleton(MyService::class, fn() => new MyService(Container::make(PDO::class)));
```

**Value objects** are `final readonly` classes: `Result`, `Raw`, all `app/Events/*`.

**`#[\Override]`** attribute is required on every method that overrides a parent or implements an interface.

**DTOs** — live in `app/Domain/DTO/`, are `final readonly` classes. Always implement `fromArray()` + `toViewArray()`:

```php
final readonly class ProductDTO
{
    public static function fromArray(array $data): self { /* … */ }
    public function toViewArray(): array { return ['id' => $this->id, 'name' => $this->name]; }
}
```

**View contract** — `View::render()` accepts only scalar/array/Raw values in `$data`. No objects allowed:

```php
// CORRECT — call toViewArray() before render
View::render('public/menu/show', ['product' => $dto->toViewArray()]);
// WRONG — passing a DTO object directly (PHPStan will catch this)
View::render('public/menu/show', ['product' => $dto]);
```

**Service interfaces** — controllers inject the interface from `app/Services/Contracts/`, not the concrete class:

```php
// In ServiceProvider:
Container::singleton(ProductServiceInterface::class, fn() => new ProductService(…));
// In Controller constructor:
public function __construct(private readonly ProductServiceInterface $service) {}
```

**API versioning** — all REST API routes use the `/api/v1/` prefix and `App\Http\Controllers\Api\V1\` namespace:

```php
$router->group(['prefix' => '/api/v1', 'middleware' => [$mw->cors()]], function (Router $r) {
    $r->get('/menu/alergenos', 'Api\V1\MenuController@allergens');
});
```

## Developer Workflows

```bash
make dev              # Start Docker stack (app + mysql + redis + mailpit)
make bash             # Shell inside app container
make logs-app         # Tail app container logs
make clean            # Clear storage/cache/* and storage/logs/*

make db-migrate       # Apply SQL migrations only
make db-seed          # Run seeders only
make db-reset         # Drop + recreate volumes (destructive, asks confirmation)
make db-verify        # Verify current schema state

make test             # Full test cycle (build image → migrate → phpunit → down)
make test-unit        # Unit tests in parallel (requires dev stack running)
make test-integration # Integration tests with ephemeral DB
make test-coverage    # HTML + Clover coverage report

make phpstan          # PHPStan level 5 (baseline: phpstan-baseline.neon)
make psalm            # Psalm static analysis
make cs-check         # PSR-12 dry-run
make cs-fix           # Auto-fix PSR-12 style
make ci               # Full quality gate: phpstan + psalm + test + cs-check
make audit            # Composer security audit

make e2e              # Playwright end-to-end tests
make e2e-a11y         # Accessibility tests only (WCAG 2.1 AA)
```

Config is read from environment variables only (12-Factor III). Secrets use `SecretLoader::require('db_password')`
which looks for env var `DB_PASSWORD` then `/run/secrets/db_password`.

**Env helpers** — never use `getenv()` directly; use the typed wrappers:

```php
Env::get('KEY', 'default');  // string
Env::int('PORT', 8080);      // int
Env::bool('DEBUG', false);   // bool
```

**Feature flags** — optional modules gated by env vars; disabled flags skip provider registration:

```bash
FEATURE_OPS=1         # Ops module: shifts, supervisor assignments
FEATURE_BACKOFFICE=1  # Backoffice admin module
FEATURE_KEEPER=1      # Keeper module: animal health checks
```

## Testing Conventions

Every test file **must** include this docblock (enforced by `tests/bootstrap.php`):

```php
/**
 * ¿Qué pruebas aquí?
 * ¿Qué me quieres demostrar?
 * ¿Qué va a fallar en este test si se cambia el código?
 */
```

- `tests/bootstrap.php` promotes `E_NOTICE`/`E_WARNING`/`E_DEPRECATED` to `ErrorException`.
- Unit tests use `$this->createStub(SomeClass::class)` (PHPUnit-native); inject via constructor.
- Integration tests hit real DB via `tests/Integration/`.
- Unit tests live in `tests/Unit/` mirroring `app/` structure (e.g. `tests/Unit/Services/AuthServiceTest.php`).

## Key Files to Read

| File                                      | Why                                             |
|-------------------------------------------|-------------------------------------------------|
| `app/routes.php`                          | All routes, middleware wiring, route groups     |
| `app/Core/Result.php`                     | Return type for all service methods             |
| `app/Core/Raw.php`                        | Bypass auto-escaping (use carefully)            |
| `app/Core/Middleware.php`                 | Role constants (`ROLE_ADMIN`, etc.)             |
| `app/Core/Http/ResponseFactory.php`       | PSR-7 response helpers used in every controller |
| `app/Repositories/AbstractRepository.php` | Base CRUD + required abstract methods           |
| `app/Providers/EventServiceProvider.php`  | Where to register new event listeners           |
| `bootstrap/container.php`                 | Service Provider boot order                     |
| `Makefile`                                | All dev commands                                |

## Reference Docs

| Doc                     | Topic                                          |
|-------------------------|------------------------------------------------|
| `README.md`             | Quick-start, env vars, local Docker setup      |
| `CONTRIBUTING.md`       | Branch naming, PR process, local workflow      |
| `DEFINITION_OF_DONE.md` | Acceptance criteria by change type             |
| `docs/ARCHITECTURE.md`  | 12-Factor layers, RBAC, dependencies, patterns |
| `docs/DEPLOYMENT.md`    | Production secrets, scaling, ops guide         |
| `docs/openapi.yaml`     | REST API specification                         |
| `docs/diagrams/`        | C4, request lifecycle, auth flow, ER diagrams  |
| `SECURITY.md`           | Vulnerability reporting policy                 |

## MCP Servers Configurados

Los servidores MCP amplían las capacidades del agente con acceso a herramientas externas.
Configurados en `.vscode/mcp.json` (compatible con VS Code ≥ 1.99 y JetBrains + GitHub Copilot plugin).
Requieren **Node.js ≥ 20 en el host** (no dentro de Docker).

### Configurados en `.vscode/mcp.json`

| Servidor              | Paquete                                            | Cuándo es útil                                                                             |
|-----------------------|----------------------------------------------------|---------------------------------------------------------------------------------------------|
| `sequential-thinking` | `@modelcontextprotocol/server-sequential-thinking` | Razonamiento encadenado para tareas complejas. Skills: `writing-plans`, `executing-plans`.  |
| `filesystem`          | `@modelcontextprotocol/server-filesystem`          | Lectura estructurada del workspace (docs/plans/, .agents/skills/, migrations/, views/).     |

### Disponibles vía extensión GitHub Copilot Chat (no requieren mcp.json)

| Herramienta                   | Cuándo es útil                                                                                                                          |
|-------------------------------|-----------------------------------------------------------------------------------------------------------------------------------------|
| `github` (`git_*`)            | Crear PRs, listar issues, revisar diffs. Skills: `requesting-code-review`, `finishing-a-development-branch`.                            |
| `playwright` (`pla_browser_*`)| Screenshots, árbol de accesibilidad, acciones de navegador. Skills: `ui-ux-pro-max`, `frontend-design`, `interface-design`, `systematic-debugging`. |

> **Figma MCP eliminado** — superó el límite de llamadas a la API. Las referencias visuales
> se gestionan ahora en `docs/design-system/` y via screenshots de Playwright.

**Variable de entorno requerida:** `GITHUB_TOKEN` (PAT con scopes `repo`, `read:org`).
Añádela a `.env` (no se compromete al repositorio — ver `.gitignore`).

## Skills de IA Disponibles

Las skills amplían el comportamiento del agente con flujos de trabajo disciplinados.
Todas las skills del proyecto están en `.agents/skills/` y registradas en `skills-lock.json`.
Las skills globales/extensión (`find-skills`, `troubleshoot`, `agent-customization`) viven
en el perfil de usuario o extensión — no se registran en el lock file del proyecto.

> **Regla de oro:** Antes de cualquier acción o respuesta, consulta `.github/instructions/ai-workflow.instructions.md`
> para saber qué skill invocar. Cuando haya duda (≥ 1% de probabilidad), invoca la skill.

### Planificación y Diseño

| Skill                         | Cuándo invocarla en Komorebi Café                                                                                           |
|-------------------------------|-----------------------------------------------------------------------------------------------------------------------------|
| `brainstorming`               | ANTES de cualquier feature, componente nuevo o cambio de comportamiento. Explora requisitos y diseño antes de tocar código. |
| `writing-plans`               | Tras brainstorming aprobado, para crear el plan de implementación paso a paso en `docs/plans/`.                             |
| `executing-plans`             | Para ejecutar un plan ya escrito en `docs/plans/` con checkpoints de revisión.                                              |
| `subagent-driven-development` | Cuando el plan del proyecto tiene 3+ tareas independientes dentro de la misma sesión.                                       |
| `dispatching-parallel-agents` | Cuando hay 2+ tareas completamente independientes (sin estado compartido) ejecutables en paralelo.                          |

### Desarrollo

| Skill                     | Cuándo invocarla en Komorebi Café                                                                                                      |
|---------------------------|----------------------------------------------------------------------------------------------------------------------------------------|
| `test-driven-development` | Al implementar cualquier feature o bugfix — escribe el test antes del código. Todos los tests en `tests/Unit/` o `tests/Integration/`. |
| `systematic-debugging`    | Ante cualquier bug, test fallido o comportamiento inesperado. ANTES de proponer un fix.                                                |
| `ui-ux-pro-max`           | **Skill primaria para TODO trabajo visual**: componentes, layouts, dark mode, accesibilidad, colores, tipografía, animaciones, formularios, placeholders. Incluye 99 guías UX, 50+ estilos y checklists de accesibilidad WCAG 2.1. |
| `frontend-design`         | Vistas con lógica interactiva compleja (Alpine.js, animaciones CSS custom, micro-interacciones).                                       |
| `interface-design`        | Dashboards y paneles admin/operativos con alta densidad de información: backoffice, KDS, recepción, keeper.                            |
| `api-design-principles`   | Al modificar o añadir rutas en la API REST — revisar `docs/openapi.yaml` y seguir convenciones PSR-7.                                  |

### Calidad y Revisión

| Skill                            | Cuándo invocarla en Komorebi Café                                                                                                 |
|----------------------------------|-----------------------------------------------------------------------------------------------------------------------------------|
| `verification-before-completion` | ANTES de afirmar que algo está hecho, arreglado o que los tests pasan. Requiere evidencia real (`make test-unit`, PHPStan, etc.). |
| `requesting-code-review`         | Tras completar la implementación y antes de hacer merge — verifica que el trabajo cumple la Definition of Done.                   |
| `receiving-code-review`          | Al recibir feedback en una PR — exige rigor técnico antes de aceptar o rechazar cambios.                                          |
| `finishing-a-development-branch` | Cuando la implementación está completa y todos los tests pasan — guía el proceso de integración o PR.                             |

### Utilidades

| Skill                 | Cuándo invocarla en Komorebi Café                                                                                  |
|-----------------------|--------------------------------------------------------------------------------------------------------------------|
| `using-superpowers`   | Al iniciar cada sesión de trabajo — establece qué skills están disponibles y cuándo usarlas.                       |
| `writing-skills`      | Al crear o editar archivos `SKILL.md` en `.agents/skills/`. Aplica TDD a documentación de procesos.                |
| `find-skills`         | Cuando se identifica una necesidad que podría tener una skill instalable (`npx skills find`).                      |
| `troubleshoot`        | Cuando el comportamiento del agente es inesperado (tools ignoradas, skills no cargadas, instrucciones omitidas).   |
| `agent-customization` | Al crear o editar archivos de configuración del agente: `.instructions.md`, `.prompt.md`, `AGENTS.md`, `SKILL.md`. |
