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

| Doc                             | Topic                                           |
|---------------------------------|-------------------------------------------------|
| `README.md`                     | Quick-start, env vars, local Docker setup       |
| `CONTRIBUTING.md`               | Branch naming, PR process, local workflow       |
| `DEFINITION_OF_DONE.md`         | Acceptance criteria by change type              |
| `docs/ARCHITECTURE.md`          | 12-Factor layers, RBAC, dependencies, patterns  |
| `docs/DEPLOYMENT.md`            | Production secrets, scaling, ops guide          |
| `docs/openapi.yaml`             | REST API specification                          |
| `docs/diagrams/`                | C4, request lifecycle, auth flow, ER diagrams   |
| `SECURITY.md`                   | Vulnerability reporting policy                  |
