---
description: "Use when writing, editing, or reviewing PHPUnit test files: unit tests, integration tests, test setup, stubs, assertions, and test structure. Enforces required docblock, strict error handling, unit/integration placement, createStub usage, and Result assertion patterns."
applyTo: "tests/**/*.php"
---

# Testing Conventions

## Required docblock

**Every test class must include this docblock** — enforced at runtime by `tests/bootstrap.php`.
Missing it causes the entire test suite bootstrap to fail.

```php
/**
 * ¿Qué pruebas aquí?
 * ¿Qué me quieres demostrar?
 * ¿Qué va a fallar en este test si se cambia el código?
 */
```

Fill it in honestly — these three questions document intent and expected failure modes.

## File structure

```php
<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * ¿Qué me quieres demostrar?
 * ¿Qué va a fallar en este test si se cambia el código?
 */

namespace Tests\Unit\Services;  // or Tests\Integration\

use PHPUnit\Framework\TestCase;
```

## Error level

`tests/bootstrap.php` promotes `E_NOTICE`, `E_WARNING`, and `E_DEPRECATED` to `ErrorException`.
Type mismatches, undefined properties, and deprecated calls will fail tests — keep code clean.

## Test placement

| Type        | Location                                                                        | DB access                                 |
|-------------|---------------------------------------------------------------------------------|-------------------------------------------|
| Unit        | `tests/Unit/` mirroring `app/` (e.g. `tests/Unit/Services/AuthServiceTest.php`) | No                                        |
| Integration | `tests/Integration/`                                                            | Yes — real DB via ephemeral compose stack |

## Unit tests

Use `$this->createStub()` (PHPUnit-native) for dependencies — not `getMock()`, not Mockery:

```php
final class UserServiceTest extends TestCase
{
    private UserService $service;
    private UserRepository $repoStub;

    protected function setUp(): void
    {
        $this->repoStub  = $this->createStub(UserRepository::class);
        $this->service   = new UserService($this->repoStub);
    }

    public function testGetProfileReturnsExpectedKeys(): void
    {
        $this->repoStub->method('findById')->willReturn([
            'id' => 1, 'name' => 'Ana', 'email' => 'ana@example.com',
        ]);

        $profile = $this->service->getProfile(1);

        $this->assertIsArray($profile);
        $this->assertArrayHasKey('id', $profile);
    }
}
```

Inject all dependencies through the constructor — never instantiate real infrastructure in unit tests.

## Asserting Result

Service methods return `Result`. Assert on `->ok` and `->data` / `->getMessage()`:

```php
$result = $this->service->register('Ana', 'ana@example.com', 'password8');

$this->assertTrue($result->ok);
$this->assertArrayHasKey('user_id', $result->data);

// Failure case:
$failResult = $this->service->register('', '', '');
$this->assertFalse($failResult->ok);
$this->assertNotEmpty($failResult->getMessage());
```

## Integration tests

Extend `Tests\Support\BaseIntegrationTest` for shared DB connection and lifecycle:

```php
final class ReservationIntegrationTest extends BaseIntegrationTest
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();           // boots DB + runs migrations
        $this->seedTestData();     // insert fixtures
    }

    // Use constant IDs that won't collide with seed data:
    private const TEST_USER_ID = 99999;
}
```

- Use high-ID constants for test fixtures to avoid collisions with seeded data.
- Do not call real external services (email, payment): stub or skip via feature flags.

## Test class rules

- Classes must be `final`.
- Test method names: `test<Action><Context>` in camelCase — descriptive, no abbreviations.
- One logical assertion group per test method (AAA: Arrange → Act → Assert).
- Do not share mutable state between test methods.

## Run commands

```bash
make test-unit        # Parallel unit tests (requires dev stack)
make test-integration # Integration tests with ephemeral DB
make test             # Full cycle: build → migrate → phpunit → down
make test-coverage    # Coverage HTML + Clover XML
```
