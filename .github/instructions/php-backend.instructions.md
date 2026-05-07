---
description: "Use when writing or editing PHP app code: controllers, services, repositories, providers, events, jobs, middleware, value objects. Enforces strict types, Result pattern, controller return contracts, CSRF, XSS boundaries, DI, and logging conventions for this custom PHP 8.4 MVC framework."
applyTo: "app/**/*.php"
---

# PHP Backend Conventions

## File structure

Every PHP file starts with:

```php
<?php

declare(strict_types=1);
```

## Imports convention

**Global classes** — always import with `use` at the top of the file:

```php
use PDO;
use Throwable;
use DateTimeImmutable;
use RuntimeException;
use Override;
```

Then reference them unqualified: `PDO::FETCH_ASSOC`, `catch (Throwable $e)`, `#[Override]`.

**Global functions** — always use `\` prefix inline (enforced by `php-cs-fixer`):

```php
\time(), \trim(), \array_map(), \sprintf()  // ✅
time(), trim()                               // ❌
use function time;                           // ❌
```

**Project classes** — standard `use` imports:

```php
use App\Core\Result;
use App\Core\Logger;
```

## Result pattern

Service methods **never throw for expected failures** — always return `Result`:

```php
// Service:
return Result::ok($data);
return Result::fail('Mensaje de error para el usuario.', 'optional_code');

// Controller consuming it:
$result = $this->myService->doSomething($input);
if (!$result->ok) {
    Flash::error($result->getMessage());
    return $this->response->redirect('/back');
}
$data = $result->data;
```

Exceptions are reserved for truly unexpected failures (catch at the infrastructure boundary).

## Controller return types

Methods return `?ResponseInterface`:

- Return `null` when `View::render()` is the last statement — it echoes directly.
- Return a `ResponseInterface` for redirects, JSON, and HTML responses.

```php
// View path — return null
public function show(ServerRequestInterface $request): ?ResponseInterface
{
    View::render('public/cafe/show', ['cafe' => $cafe], ['cafe.css']);
    return null;
}

// Redirect/JSON path — return ResponseInterface
public function store(ServerRequestInterface $request): ResponseInterface
{
    // ... handle result ...
    return $this->response->redirect('/admin/cafes');
}
```

## Flash messages

Use semantic helpers only — never `Flash::set()`:

```php
Flash::success('Guardado correctamente.');
Flash::error($result->getMessage());
Flash::warning('Quedan pocos lugares.');
Flash::info('Sesión cerrada.');
```

## XSS escaping

`View::render()` auto-escapes all template values. To pass data that must not be escaped:

```php
'jsonData'    => Raw::json($array),  // safe JSON for Alpine x-data attributes
'htmlSnippet' => Raw::html($safe),   // pre-sanitized HTML
```

Use `e($variable)` in view templates for manual escaping of edge cases.

## Routing & middleware

Controllers are registered as `'Namespace\ClassName@method'` strings:

```php
$router->get('/cafes/{slug}', 'Public\CafeController@show');
$router->post('/users', 'Admin\UserController@store', [$mw->auth(), $mw->role('admin'), $mw->csrf()]);
```

**CSRF middleware is required on every mutating route (POST / PUT / PATCH / DELETE) — no exceptions.**

Available middleware from `MiddlewareFactory` (`$mw`):

```php
$mw->auth()            // requires active session
$mw->role('admin')     // RBAC; roles: admin | manager | supervisor | reception | kitchen | keeper | user
$mw->csrf()            // CSRF token validation
$mw->guest()           // redirect if already authenticated
$mw->api()             // JSON API gate
$mw->rateLimit('key')  // Redis-backed rate limiting per named bucket
```

## Repositories

Extend `AbstractRepository`; implement the two required abstract methods:

```php
final class CafeRepository extends AbstractRepository
{
    #[Override]
    protected function getTable(): string
    {
        return 'cafes';
    }

    #[Override]
    protected function getSelectFields(): array
    {
        return ['id', 'slug', 'name', 'is_active'];  // never SELECT *
    }
}
```

Obtain `PDO` via `Database::getConnection()` in repository constructors — not `Container::make(PDO::class)`.

## #[Override] attribute

Required on **every** method that overrides a parent method or implements an interface method. PHPStan level 5 enforces this.
Always import the attribute: `use Override;` — then use `#[Override]` (not `#[\Override]`).

## Dependency injection

Bind singletons in `bootstrap/container.php`:

```php
Container::singleton(MyService::class, fn() => new MyService(Container::make(PDO::class)));
```

Inject dependencies through constructors; never use `Container::make()` inside a method body.

## Environment & secrets

Use typed helpers — never `getenv()` directly:

```php
Env::get('KEY', 'default');   // string
Env::int('PORT', 8080);       // int
Env::bool('DEBUG', false);    // bool
```

Secrets: `SecretLoader::require('db_password')` — reads env var first, then `/run/secrets/db_password`.

## Logging

Always use the static proxy. Include `[ClassName]` prefix in every message:

```php
Logger::info('[UserService] Profile updated', ['user_id' => $id]);
Logger::error('[ReservationController] Save failed', ['reason' => $result->getMessage()]);
```

Services extend `BaseService` — use the typed helpers:

```php
$this->logInfo('Profile updated', ['user_id' => $id]);
$this->logWarning('Slot not available', ['slot_id' => $slotId]);
$this->logError('External call failed', ['service' => 'stripe']);
$this->logDebug('Cache hit', ['key' => $cacheKey]);
$this->logCritical('DB unreachable', ['dsn' => $dsn]);
```

### Level taxonomy

| Level      | When to use                                                        |
| ---------- | ------------------------------------------------------------------ |
| `debug`    | Development detail: SQL queries, cache hits, algorithm steps       |
| `info`     | Normal operation: successful operations with side effects          |
| `warning`  | Expected failures: validation errors, business rule rejections     |
| `error`    | Unexpected failures: catch blocks, Result::fail from external deps |
| `critical` | System integrity: config missing, DB unreachable, unrecoverable    |

### Channels

```php
Logger::channel('app')    // business logic (default — same as Logger::info/error/...)
Logger::channel('http')   // HTTP requests and API operations
Logger::channel('db')     // slow queries, DB errors
Logger::channel('queue')  // job lifecycle (start, complete, fail)
Logger::channel('auth')   // login, logout, token events
```

### Rules

- Read-only methods with no side effects: do NOT log.
- Never log passwords, tokens, or full email addresses. Use `[REDACTED]` or `hash('sha256', $value)`.
- `Logger::error()` and above **require** a `$context` array (enforced by PHPStan rule).
- Every request carries a `request_id` in `LogContext` — automatically added to all logs via `LogContextProcessor`.

Logs go to stdout (12-Factor XI). Never use `error_log()` or `var_dump()`.

## Async jobs & events

Queue a job:

```php
Queue::push(SendEmailJob::class, ['to' => $email, 'subject' => '…', 'body' => '…']); // 'emails' queue
Queue::push(SomeOtherJob::class, $payload);  // 'default' queue
```

Dispatch an event:

```php
$dispatcher = Container::make(EventDispatcherInterface::class);
$dispatcher->dispatch(new UserRegisteredEvent($userId, $email, $name, new DateTimeImmutable()));
```

Register new listeners in `app/Providers/EventServiceProvider.php` → `boot()`.

## Value objects

Declare as `final readonly class`. All event classes, `Result`, and `Raw` follow this rule.
