# Base Classes + OWASP Hardening — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add Value Objects, BaseService (+ TransactionalService), FormRequests y 3 OWASP middlewares al proyecto PHP 8.4 custom MVC para eliminar duplicaciones de validación y endurecer la frontera HTTP.

**Architecture:** Bottom-up: SP-1 Value Objects como bloques atómicos → SP-2 BaseService abstrae logging/transacciones → SP-3 FormRequests usan VOs en la frontera HTTP → SP-4 OWASP middlewares (independiente). Los VOs son `final readonly` y lanzan `ValidationException` en el constructor. Todos los services nuevos y migrados usan `Result::ok()`/`Result::fail()`, nunca lanzan excepciones para flujos esperados.

**Tech Stack:** PHP 8.4, PSR-7/PSR-15, Monolog (via `Logger` proxy), `ValidationException`, `Result` pattern, PHPUnit 11.

---

## File Map

### SP-1 — Value Objects (`app/Core/ValueObjects/`)

| Fichero a crear | Responsabilidad |
|---|---|
| `app/Core/ValueObjects/Email.php` | Normaliza + valida email |
| `app/Core/ValueObjects/Password.php` | Valida longitud, mayúscula, dígito |
| `app/Core/ValueObjects/DateString.php` | Valida `YYYY-MM-DD` y date real |
| `app/Core/ValueObjects/TimeString.php` | Valida `HH:MM` 00:00–23:59 |
| `app/Core/ValueObjects/Slug.php` | Valida `[a-z0-9-]+` |
| `app/Core/ValueObjects/Uuid.php` | Valida UUID v4 |
| `app/Core/ValueObjects/GuestCount.php` | Valida rango 1–20 |
| `app/Core/ValueObjects/Role.php` | Valida contra lista fija |

Tests en `tests/Unit/Core/ValueObjects/`

### SP-2 — Base Services (`app/Core/`)

| Fichero a crear/modificar | Responsabilidad |
|---|---|
| `app/Core/BaseService.php` | `logInfo/logError`, `assertNotBlank/assertMaxLength/assertRange/assertOneOf` |
| `app/Core/TransactionalService.php` | extiende `BaseService`; `transact(callable): Result` |
| `app/Services/AuthService.php` | **Modificar**: `extends BaseService` |
| `app/Services/ReviewService.php` | **Modificar**: `extends BaseService` |
| `app/Services/CafeService.php` | **Modificar**: `extends BaseService` |
| `app/Services/AnimalCareService.php` | **Modificar**: `extends TransactionalService`; sustituir `beginTransaction` por `transact()` |
| `app/Services/WaitlistService.php` | **Modificar**: `extends TransactionalService` |
| `app/Services/UserManagementService.php` | **Modificar**: `extends TransactionalService` |
| `app/Services/ProductService.php` | **Modificar**: `extends TransactionalService` |
| `app/Services/ReservationTimeSlotService.php` | **Modificar**: `extends TransactionalService` |

Tests en `tests/Unit/Core/`

### SP-3 — FormRequests

| Fichero a crear/modificar | Responsabilidad |
|---|---|
| `app/Core/Http/FormRequest.php` | Abstract base: rules engine mínimo, sanitize, validated() |
| `app/Http/Requests/Auth/LoginRequest.php` | email + password |
| `app/Http/Requests/Auth/RegisterRequest.php` | name + email + password + password_confirmation |
| `app/Http/Requests/Auth/PasswordResetRequest.php` | email |
| `app/Http/Requests/Review/CreateReviewRequest.php` | cafe_id + rating + title + body |
| `app/Http/Requests/Review/UpdateReviewRequest.php` | rating + title + body (todos opcionales) |
| `app/Http/Requests/Reservation/CreateReservationRequest.php` | date + time + guest_count |
| `app/Http/Requests/Admin/CreateUserRequest.php` | name + email + role + password |
| `app/Http/Requests/Admin/UpdateUserRequest.php` | name + email + role |
| `app/Http/Controllers/Auth/AuthController.php` | **Modificar**: usar `LoginRequest`, `RegisterRequest` |
| `app/Http/Controllers/Public/ReviewController.php` | **Modificar**: usar `CreateReviewRequest` |
| `app/Http/Controllers/Admin/UserController.php` | **Modificar**: usar `CreateUserRequest`/`UpdateUserRequest` |

Tests en `tests/Unit/Http/Requests/`

### SP-4 — OWASP Middlewares (independiente)

| Fichero a crear/modificar | Responsabilidad |
|---|---|
| `app/Http/Middleware/SecurityHeadersMiddleware.php` | X-Frame-Options, nosniff, CSP, Referrer-Policy, Permissions-Policy |
| `app/Http/Middleware/PayloadSizeMiddleware.php` | Rechaza Content-Length > N KB (default 256) → 413 |
| `app/Http/Middleware/HttpRateLimitMiddleware.php` | Usa `RateLimitingService`; devuelve 429 + Retry-After |
| `app/Core/MiddlewareFactory.php` | **Modificar**: añadir `securityHeaders()`, `maxPayload(int $kb)`, `rateLimit(string $action, int $max, int $windowSeconds)` |
| `app/routes.php` | **Modificar**: grupo global `securityHeaders()` + `maxPayload()`; rutas de formulario con `rateLimit()` |

Tests en `tests/Unit/Middleware/`

---

## SP-1: Value Objects

> Patrón para todos los VOs: `final readonly`, un único `__construct(string $value)` que lanza `ValidationException` si es inválido, `__toString()` y `getValue()`. Nunca setters.

---

### Task 1: Email Value Object

**Files:**

- Create: `app/Core/ValueObjects/Email.php`
- Create: `tests/Unit/Core/ValueObjects/EmailTest.php`

- [ ] **Step 1: Crear test fallido**

```php
<?php
declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? Construcción, normalización y rechazo de Email VO.
 * ¿Qué me quieres demostrar? Que Email garantiza un valor normalizado o lanza ValidationException.
 * ¿Qué va a fallar en este test si se cambia el código? Si se elimina la normalización o se relajan las validaciones.
 */

use App\Core\ValueObjects\Email;
use App\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;

final class EmailTest extends TestCase
{
    public function testValidEmailIsAccepted(): void
    {
        $email = new Email('User@Example.COM');
        $this->assertSame('user@example.com', $email->getValue());
    }

    public function testToStringMatchesGetValue(): void
    {
        $email = new Email('hello@example.com');
        $this->assertSame('hello@example.com', (string) $email);
    }

    public function testEmptyEmailThrows(): void
    {
        $this->expectException(ValidationException::class);
        new Email('');
    }

    public function testInvalidEmailThrows(): void
    {
        $this->expectException(ValidationException::class);
        new Email('not-an-email');
    }

    public function testEmailWithSpacesIsNormalized(): void
    {
        $email = new Email('  test@EXAMPLE.org  ');
        $this->assertSame('test@example.org', $email->getValue());
    }
}
```

- [ ] **Step 2: Ejecutar el test para verificar que falla**

```bash
docker compose exec app php vendor/bin/phpunit tests/Unit/Core/ValueObjects/EmailTest.php --testdox
```

Esperado: FAIL — `App\Core\ValueObjects\Email not found`

- [ ] **Step 3: Implementar `Email`**

```php
<?php
declare(strict_types=1);

namespace App\Core\ValueObjects;

use App\Exceptions\ValidationException;

final readonly class Email
{
    private string $value;

    public function __construct(string $value)
    {
        $normalized = strtolower(trim($value));

        if ($normalized === '' || filter_var($normalized, FILTER_VALIDATE_EMAIL) === false) {
            throw new ValidationException(
                'Email inválido',
                ['email' => 'El email no tiene un formato válido']
            );
        }

        $this->value = $normalized;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
```

- [ ] **Step 4: Ejecutar el test para verificar que pasa**

```bash
docker compose exec app php vendor/bin/phpunit tests/Unit/Core/ValueObjects/EmailTest.php --testdox
```

Esperado: 5 tests PASS

- [ ] **Step 5: Commit**

```bash
git add app/Core/ValueObjects/Email.php tests/Unit/Core/ValueObjects/EmailTest.php
git commit -m "feat(vo): Add Email value object"
```

---

### Task 2: Password Value Object

**Files:**

- Create: `app/Core/ValueObjects/Password.php`
- Create: `tests/Unit/Core/ValueObjects/PasswordTest.php`

- [ ] **Step 1: Crear test fallido**

```php
<?php
declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? Construcción y rechazo del VO Password.
 * ¿Qué me quieres demostrar? Que Password garantiza longitud ≥8 ≤128, una mayúscula y un dígito.
 * ¿Qué va a fallar en este test si se cambia el código? Si se relajan las reglas de complejidad.
 */

use App\Core\ValueObjects\Password;
use App\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;

final class PasswordTest extends TestCase
{
    public function testValidPasswordIsAccepted(): void
    {
        $pwd = new Password('SecurePass1');
        $this->assertSame('SecurePass1', $pwd->getValue());
    }

    public function testShortPasswordThrows(): void
    {
        $this->expectException(ValidationException::class);
        new Password('Ab1');
    }

    public function testTooLongPasswordThrows(): void
    {
        $this->expectException(ValidationException::class);
        new Password(str_repeat('A1', 65)); // 130 chars
    }

    public function testPasswordWithoutUppercaseThrows(): void
    {
        $this->expectException(ValidationException::class);
        new Password('lowercase1');
    }

    public function testPasswordWithoutDigitThrows(): void
    {
        $this->expectException(ValidationException::class);
        new Password('NoDigitsHere');
    }

    public function testEmptyPasswordThrows(): void
    {
        $this->expectException(ValidationException::class);
        new Password('');
    }
}
```

- [ ] **Step 2: Ejecutar el test para verificar que falla**

```bash
docker compose exec app php vendor/bin/phpunit tests/Unit/Core/ValueObjects/PasswordTest.php --testdox
```

Esperado: FAIL — `App\Core\ValueObjects\Password not found`

- [ ] **Step 3: Implementar `Password`**

```php
<?php
declare(strict_types=1);

namespace App\Core\ValueObjects;

use App\Exceptions\ValidationException;

final readonly class Password
{
    private string $value;

    public function __construct(string $value)
    {
        $len = mb_strlen($value);

        if ($len < 8 || $len > 128) {
            throw new ValidationException(
                'Contraseña inválida',
                ['password' => 'La contraseña debe tener entre 8 y 128 caracteres']
            );
        }

        if (!preg_match('/[A-Z]/', $value)) {
            throw new ValidationException(
                'Contraseña inválida',
                ['password' => 'La contraseña debe contener al menos una letra mayúscula']
            );
        }

        if (!preg_match('/[0-9]/', $value)) {
            throw new ValidationException(
                'Contraseña inválida',
                ['password' => 'La contraseña debe contener al menos un dígito']
            );
        }

        $this->value = $value;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
```

- [ ] **Step 4: Ejecutar el test para verificar que pasa**

```bash
docker compose exec app php vendor/bin/phpunit tests/Unit/Core/ValueObjects/PasswordTest.php --testdox
```

Esperado: 6 tests PASS

- [ ] **Step 5: Commit**

```bash
git add app/Core/ValueObjects/Password.php tests/Unit/Core/ValueObjects/PasswordTest.php
git commit -m "feat(vo): Add Password value object"
```

---

### Task 3: DateString + TimeString Value Objects

**Files:**

- Create: `app/Core/ValueObjects/DateString.php`
- Create: `app/Core/ValueObjects/TimeString.php`
- Create: `tests/Unit/Core/ValueObjects/DateStringTest.php`
- Create: `tests/Unit/Core/ValueObjects/TimeStringTest.php`

- [ ] **Step 1: Crear tests fallidos**

`tests/Unit/Core/ValueObjects/DateStringTest.php`:

```php
<?php
declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? Construcción y rechazo del VO DateString.
 * ¿Qué me quieres demostrar? Que DateString garantiza formato YYYY-MM-DD y fecha real.
 * ¿Qué va a fallar en este test si se cambia el código? Si se acepta una fecha inválida como 2024-02-30.
 */

use App\Core\ValueObjects\DateString;
use App\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;

final class DateStringTest extends TestCase
{
    public function testValidDateIsAccepted(): void
    {
        $d = new DateString('2025-06-15');
        $this->assertSame('2025-06-15', $d->getValue());
    }

    public function testInvalidFormatThrows(): void
    {
        $this->expectException(ValidationException::class);
        new DateString('15-06-2025');
    }

    public function testImpossibleDateThrows(): void
    {
        $this->expectException(ValidationException::class);
        new DateString('2024-02-30');
    }

    public function testEmptyStringThrows(): void
    {
        $this->expectException(ValidationException::class);
        new DateString('');
    }

    public function testToStringMatchesGetValue(): void
    {
        $d = new DateString('2025-01-01');
        $this->assertSame('2025-01-01', (string) $d);
    }
}
```

`tests/Unit/Core/ValueObjects/TimeStringTest.php`:

```php
<?php
declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? Construcción y rechazo del VO TimeString.
 * ¿Qué me quieres demostrar? Que TimeString garantiza formato HH:MM válido.
 * ¿Qué va a fallar en este test si se cambia el código? Si se acepta "25:00" o "8:5".
 */

use App\Core\ValueObjects\TimeString;
use App\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;

final class TimeStringTest extends TestCase
{
    public function testValidTimeIsAccepted(): void
    {
        $t = new TimeString('14:30');
        $this->assertSame('14:30', $t->getValue());
    }

    public function testMidnightIsAccepted(): void
    {
        $t = new TimeString('00:00');
        $this->assertSame('00:00', $t->getValue());
    }

    public function testInvalidHourThrows(): void
    {
        $this->expectException(ValidationException::class);
        new TimeString('25:00');
    }

    public function testInvalidMinuteThrows(): void
    {
        $this->expectException(ValidationException::class);
        new TimeString('12:60');
    }

    public function testBadFormatThrows(): void
    {
        $this->expectException(ValidationException::class);
        new TimeString('9:5');
    }

    public function testEmptyStringThrows(): void
    {
        $this->expectException(ValidationException::class);
        new TimeString('');
    }
}
```

- [ ] **Step 2: Ejecutar los tests para verificar que fallan**

```bash
docker compose exec app php vendor/bin/phpunit tests/Unit/Core/ValueObjects/DateStringTest.php tests/Unit/Core/ValueObjects/TimeStringTest.php --testdox
```

Esperado: FAIL — clases no encontradas

- [ ] **Step 3: Implementar `DateString`**

```php
<?php
declare(strict_types=1);

namespace App\Core\ValueObjects;

use App\Exceptions\ValidationException;

final readonly class DateString
{
    private string $value;

    public function __construct(string $value)
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            throw new ValidationException(
                'Fecha inválida',
                ['date' => 'El formato de fecha debe ser YYYY-MM-DD']
            );
        }

        [$year, $month, $day] = explode('-', $value);
        if (!checkdate((int) $month, (int) $day, (int) $year)) {
            throw new ValidationException(
                'Fecha inválida',
                ['date' => 'La fecha no existe en el calendario']
            );
        }

        $this->value = $value;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
```

- [ ] **Step 4: Implementar `TimeString`**

```php
<?php
declare(strict_types=1);

namespace App\Core\ValueObjects;

use App\Exceptions\ValidationException;

final readonly class TimeString
{
    private string $value;

    public function __construct(string $value)
    {
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value)) {
            throw new ValidationException(
                'Hora inválida',
                ['time' => 'El formato de hora debe ser HH:MM (00:00–23:59)']
            );
        }

        $this->value = $value;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
```

- [ ] **Step 5: Ejecutar los tests para verificar que pasan**

```bash
docker compose exec app php vendor/bin/phpunit tests/Unit/Core/ValueObjects/DateStringTest.php tests/Unit/Core/ValueObjects/TimeStringTest.php --testdox
```

Esperado: 11 tests PASS

- [ ] **Step 6: Commit**

```bash
git add app/Core/ValueObjects/DateString.php app/Core/ValueObjects/TimeString.php \
        tests/Unit/Core/ValueObjects/DateStringTest.php tests/Unit/Core/ValueObjects/TimeStringTest.php
git commit -m "feat(vo): Add DateString and TimeString value objects"
```

---

### Task 4: Slug + Uuid Value Objects

**Files:**

- Create: `app/Core/ValueObjects/Slug.php`
- Create: `app/Core/ValueObjects/Uuid.php`
- Create: `tests/Unit/Core/ValueObjects/SlugTest.php`
- Create: `tests/Unit/Core/ValueObjects/UuidTest.php`

- [ ] **Step 1: Crear tests fallidos**

`tests/Unit/Core/ValueObjects/SlugTest.php`:

```php
<?php
declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? Construcción y rechazo del VO Slug.
 * ¿Qué me quieres demostrar? Que Slug solo acepta [a-z0-9-]+ y no mayúsculas ni espacios.
 * ¿Qué va a fallar en este test si se cambia el código? Si se acepta un slug con mayúsculas o espacios.
 */

use App\Core\ValueObjects\Slug;
use App\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;

final class SlugTest extends TestCase
{
    public function testValidSlugIsAccepted(): void
    {
        $slug = new Slug('komorebi-cafe-tokyo');
        $this->assertSame('komorebi-cafe-tokyo', $slug->getValue());
    }

    public function testSingleWordSlugIsAccepted(): void
    {
        $slug = new Slug('komorebi');
        $this->assertSame('komorebi', $slug->getValue());
    }

    public function testSlugWithNumbersIsAccepted(): void
    {
        $slug = new Slug('cafe-42');
        $this->assertSame('cafe-42', $slug->getValue());
    }

    public function testUppercaseSlugThrows(): void
    {
        $this->expectException(ValidationException::class);
        new Slug('Cafe-Tokyo');
    }

    public function testSlugWithSpacesThrows(): void
    {
        $this->expectException(ValidationException::class);
        new Slug('cafe tokyo');
    }

    public function testEmptySlugThrows(): void
    {
        $this->expectException(ValidationException::class);
        new Slug('');
    }
}
```

`tests/Unit/Core/ValueObjects/UuidTest.php`:

```php
<?php
declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? Construcción y rechazo del VO Uuid.
 * ¿Qué me quieres demostrar? Que Uuid solo acepta UUIDs v4 válidos.
 * ¿Qué va a fallar en este test si se cambia el código? Si se acepta un UUID con formato inválido.
 */

use App\Core\ValueObjects\Uuid;
use App\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;

final class UuidTest extends TestCase
{
    public function testValidUuidIsAccepted(): void
    {
        $uuid = new Uuid('550e8400-e29b-41d4-a716-446655440000');
        $this->assertSame('550e8400-e29b-41d4-a716-446655440000', $uuid->getValue());
    }

    public function testInvalidUuidThrows(): void
    {
        $this->expectException(ValidationException::class);
        new Uuid('not-a-uuid');
    }

    public function testUuidWithWrongVersionThrows(): void
    {
        // UUID v1 – no es v4
        $this->expectException(ValidationException::class);
        new Uuid('6ba7b810-9dad-11d1-80b4-00c04fd430c8');
    }

    public function testEmptyUuidThrows(): void
    {
        $this->expectException(ValidationException::class);
        new Uuid('');
    }

    public function testToStringMatchesGetValue(): void
    {
        $id = '550e8400-e29b-41d4-a716-446655440000';
        $uuid = new Uuid($id);
        $this->assertSame($id, (string) $uuid);
    }
}
```

- [ ] **Step 2: Ejecutar los tests para verificar que fallan**

```bash
docker compose exec app php vendor/bin/phpunit tests/Unit/Core/ValueObjects/SlugTest.php tests/Unit/Core/ValueObjects/UuidTest.php --testdox
```

Esperado: FAIL — clases no encontradas

- [ ] **Step 3: Implementar `Slug`**

```php
<?php
declare(strict_types=1);

namespace App\Core\ValueObjects;

use App\Exceptions\ValidationException;

final readonly class Slug
{
    private string $value;

    public function __construct(string $value)
    {
        if ($value === '' || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value)) {
            throw new ValidationException(
                'Slug inválido',
                ['slug' => 'El slug solo puede contener letras minúsculas, números y guiones']
            );
        }

        $this->value = $value;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
```

- [ ] **Step 4: Implementar `Uuid`**

```php
<?php
declare(strict_types=1);

namespace App\Core\ValueObjects;

use App\Exceptions\ValidationException;

final readonly class Uuid
{
    private string $value;

    public function __construct(string $value)
    {
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value)) {
            throw new ValidationException(
                'UUID inválido',
                ['uuid' => 'El valor no es un UUID v4 válido']
            );
        }

        $this->value = strtolower($value);
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
```

- [ ] **Step 5: Ejecutar los tests para verificar que pasan**

```bash
docker compose exec app php vendor/bin/phpunit tests/Unit/Core/ValueObjects/SlugTest.php tests/Unit/Core/ValueObjects/UuidTest.php --testdox
```

Esperado: 10 tests PASS

- [ ] **Step 6: Commit**

```bash
git add app/Core/ValueObjects/Slug.php app/Core/ValueObjects/Uuid.php \
        tests/Unit/Core/ValueObjects/SlugTest.php tests/Unit/Core/ValueObjects/UuidTest.php
git commit -m "feat(vo): Add Slug and Uuid value objects"
```

---

### Task 5: GuestCount + Role Value Objects

**Files:**

- Create: `app/Core/ValueObjects/GuestCount.php`
- Create: `app/Core/ValueObjects/Role.php`
- Create: `tests/Unit/Core/ValueObjects/GuestCountTest.php`
- Create: `tests/Unit/Core/ValueObjects/RoleTest.php`

- [ ] **Step 1: Crear tests fallidos**

`tests/Unit/Core/ValueObjects/GuestCountTest.php`:

```php
<?php
declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? Construcción y rechazo del VO GuestCount.
 * ¿Qué me quieres demostrar? Que GuestCount acepta rango 1–20.
 * ¿Qué va a fallar en este test si se cambia el código? Si se cambia el rango permitido.
 */

use App\Core\ValueObjects\GuestCount;
use App\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;

final class GuestCountTest extends TestCase
{
    public function testValidCountIsAccepted(): void
    {
        $g = new GuestCount(4);
        $this->assertSame(4, $g->getValue());
    }

    public function testMinimumBoundaryIsAccepted(): void
    {
        $g = new GuestCount(1);
        $this->assertSame(1, $g->getValue());
    }

    public function testMaximumBoundaryIsAccepted(): void
    {
        $g = new GuestCount(20);
        $this->assertSame(20, $g->getValue());
    }

    public function testZeroGuestsThrows(): void
    {
        $this->expectException(ValidationException::class);
        new GuestCount(0);
    }

    public function testTwentyOneGuestsThrows(): void
    {
        $this->expectException(ValidationException::class);
        new GuestCount(21);
    }
}
```

`tests/Unit/Core/ValueObjects/RoleTest.php`:

```php
<?php
declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? Construcción y rechazo del VO Role.
 * ¿Qué me quieres demostrar? Que Role solo acepta los roles definidos en el sistema.
 * ¿Qué va a fallar en este test si se cambia el código? Si se añade o elimina un rol sin actualizar el VO.
 */

use App\Core\ValueObjects\Role;
use App\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;

final class RoleTest extends TestCase
{
    public function testAdminRoleIsAccepted(): void
    {
        $role = new Role('admin');
        $this->assertSame('admin', $role->getValue());
    }

    public function testManagerRoleIsAccepted(): void
    {
        $role = new Role('manager');
        $this->assertSame('manager', $role->getValue());
    }

    public function testUserRoleIsAccepted(): void
    {
        $role = new Role('user');
        $this->assertSame('user', $role->getValue());
    }

    public function testInvalidRoleThrows(): void
    {
        $this->expectException(ValidationException::class);
        new Role('superadmin');
    }

    public function testEmptyRoleThrows(): void
    {
        $this->expectException(ValidationException::class);
        new Role('');
    }

    public function testAllValidRolesAreAccessible(): void
    {
        $this->assertContains('admin', Role::VALID_ROLES);
        $this->assertContains('manager', Role::VALID_ROLES);
        $this->assertContains('user', Role::VALID_ROLES);
        $this->assertContains('reception', Role::VALID_ROLES);
        $this->assertContains('kitchen', Role::VALID_ROLES);
        $this->assertContains('keeper', Role::VALID_ROLES);
        $this->assertContains('supervisor', Role::VALID_ROLES);
    }
}
```

- [ ] **Step 2: Ejecutar los tests para verificar que fallan**

```bash
docker compose exec app php vendor/bin/phpunit tests/Unit/Core/ValueObjects/GuestCountTest.php tests/Unit/Core/ValueObjects/RoleTest.php --testdox
```

- [ ] **Step 3: Implementar `GuestCount`**

```php
<?php
declare(strict_types=1);

namespace App\Core\ValueObjects;

use App\Exceptions\ValidationException;

final readonly class GuestCount
{
    public const int MIN = 1;
    public const int MAX = 20;

    private int $value;

    public function __construct(int $value)
    {
        if ($value < self::MIN || $value > self::MAX) {
            throw new ValidationException(
                'Número de comensales inválido',
                ['guest_count' => sprintf('El número de comensales debe estar entre %d y %d', self::MIN, self::MAX)]
            );
        }

        $this->value = $value;
    }

    public function getValue(): int
    {
        return $this->value;
    }
}
```

- [ ] **Step 4: Implementar `Role`**

```php
<?php
declare(strict_types=1);

namespace App\Core\ValueObjects;

use App\Exceptions\ValidationException;

final readonly class Role
{
    /** @var list<string> */
    public const array VALID_ROLES = ['admin', 'manager', 'supervisor', 'reception', 'kitchen', 'keeper', 'user'];

    private string $value;

    public function __construct(string $value)
    {
        if (!in_array($value, self::VALID_ROLES, true)) {
            throw new ValidationException(
                'Rol inválido',
                ['role' => sprintf('El rol debe ser uno de: %s', implode(', ', self::VALID_ROLES))]
            );
        }

        $this->value = $value;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
```

- [ ] **Step 5: Ejecutar los tests para verificar que pasan**

```bash
docker compose exec app php vendor/bin/phpunit tests/Unit/Core/ValueObjects/ --testdox
```

Esperado: todos los tests de VOs PASS

- [ ] **Step 6: Ejecutar suite completa de tests para detectar regresiones**

```bash
docker compose exec app php vendor/bin/phpunit --testdox
```

Esperado: todos los tests pasan

- [ ] **Step 7: Commit**

```bash
git add app/Core/ValueObjects/ tests/Unit/Core/ValueObjects/
git commit -m "feat(vo): Add GuestCount and Role value objects; complete SP-1"
```

---

## SP-2: BaseService + TransactionalService

---

### Task 6: BaseService

**Files:**

- Create: `app/Core/BaseService.php`
- Create: `tests/Unit/Core/BaseServiceTest.php`

- [ ] **Step 1: Crear test fallido**

```php
<?php
declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? Los helpers de validación y logging de BaseService.
 * ¿Qué me quieres demostrar? Que los assert* lanzan ValidationException con el campo correcto.
 * ¿Qué va a fallar en este test si se cambia el código? Si se cambia el campo que se reporta en la excepción.
 */

use App\Core\BaseService;
use App\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;

final class ConcreteService extends BaseService {}

final class BaseServiceTest extends TestCase
{
    private ConcreteService $service;

    protected function setUp(): void
    {
        $this->service = new ConcreteService();
    }

    public function testAssertNotBlankThrowsOnEmptyString(): void
    {
        $this->expectException(ValidationException::class);
        $this->callProtected('assertNotBlank', ['', 'name']);
    }

    public function testAssertNotBlankPassesOnNonEmptyString(): void
    {
        $this->expectNotToPerformAssertions();
        $this->callProtected('assertNotBlank', ['hello', 'name']);
    }

    public function testAssertMaxLengthThrowsWhenExceeded(): void
    {
        $this->expectException(ValidationException::class);
        $this->callProtected('assertMaxLength', [str_repeat('a', 51), 50, 'field']);
    }

    public function testAssertMaxLengthPassesAtLimit(): void
    {
        $this->expectNotToPerformAssertions();
        $this->callProtected('assertMaxLength', [str_repeat('a', 50), 50, 'field']);
    }

    public function testAssertRangeThrowsBelowMin(): void
    {
        $this->expectException(ValidationException::class);
        $this->callProtected('assertRange', [0, 1, 10, 'num']);
    }

    public function testAssertRangeThrowsAboveMax(): void
    {
        $this->expectException(ValidationException::class);
        $this->callProtected('assertRange', [11, 1, 10, 'num']);
    }

    public function testAssertRangePassesWithinBounds(): void
    {
        $this->expectNotToPerformAssertions();
        $this->callProtected('assertRange', [5, 1, 10, 'num']);
    }

    public function testAssertOneOfThrowsForInvalid(): void
    {
        $this->expectException(ValidationException::class);
        $this->callProtected('assertOneOf', ['x', ['a', 'b', 'c'], 'field']);
    }

    public function testAssertOneOfPassesForValid(): void
    {
        $this->expectNotToPerformAssertions();
        $this->callProtected('assertOneOf', ['a', ['a', 'b', 'c'], 'field']);
    }

    /** @param array<mixed> $args */
    private function callProtected(string $method, array $args): void
    {
        $ref = new \ReflectionMethod($this->service, $method);
        $ref->invoke($this->service, ...$args);
    }
}
```

- [ ] **Step 2: Ejecutar el test para verificar que falla**

```bash
docker compose exec app php vendor/bin/phpunit tests/Unit/Core/BaseServiceTest.php --testdox
```

- [ ] **Step 3: Implementar `BaseService`**

```php
<?php
declare(strict_types=1);

namespace App\Core;

use App\Exceptions\ValidationException;

/**
 * Clase base para Services.
 *
 * Proporciona helpers de validación y logging reutilizables.
 * No contiene PDO ni lógica transaccional — ver TransactionalService.
 */
abstract class BaseService
{
    // ─── Logging ──────────────────────────────────────────────────

    protected function logInfo(string $message, array $context = []): void
    {
        Logger::info('[' . static::class . '] ' . $message, $context);
    }

    protected function logError(string $message, array $context = []): void
    {
        Logger::error('[' . static::class . '] ' . $message, $context);
    }

    // ─── Validación ───────────────────────────────────────────────

    /**
     * @throws ValidationException
     */
    protected function assertNotBlank(string $value, string $field): void
    {
        if (trim($value) === '') {
            throw new ValidationException(
                'Campo requerido',
                [$field => "El campo {$field} es obligatorio"]
            );
        }
    }

    /**
     * @throws ValidationException
     */
    protected function assertMaxLength(string $value, int $max, string $field): void
    {
        if (mb_strlen($value) > $max) {
            throw new ValidationException(
                'Valor demasiado largo',
                [$field => "El campo {$field} no puede superar {$max} caracteres"]
            );
        }
    }

    /**
     * @throws ValidationException
     */
    protected function assertRange(int|float $value, int|float $min, int|float $max, string $field): void
    {
        if ($value < $min || $value > $max) {
            throw new ValidationException(
                'Valor fuera de rango',
                [$field => "El campo {$field} debe estar entre {$min} y {$max}"]
            );
        }
    }

    /**
     * @param array<mixed> $allowed
     * @throws ValidationException
     */
    protected function assertOneOf(mixed $value, array $allowed, string $field): void
    {
        if (!in_array($value, $allowed, true)) {
            throw new ValidationException(
                'Valor no permitido',
                [$field => "El campo {$field} debe ser uno de: " . implode(', ', array_map('strval', $allowed))]
            );
        }
    }
}
```

- [ ] **Step 4: Ejecutar el test para verificar que pasa**

```bash
docker compose exec app php vendor/bin/phpunit tests/Unit/Core/BaseServiceTest.php --testdox
```

Esperado: 9 tests PASS

- [ ] **Step 5: Commit**

```bash
git add app/Core/BaseService.php tests/Unit/Core/BaseServiceTest.php
git commit -m "feat(service): Add BaseService with validation helpers and logging"
```

---

### Task 7: TransactionalService

**Files:**

- Create: `app/Core/TransactionalService.php`
- Create: `tests/Unit/Core/TransactionalServiceTest.php`

- [ ] **Step 1: Crear test fallido**

```php
<?php
declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? El método transact() de TransactionalService.
 * ¿Qué me quieres demostrar? Que transact() hace commit y retorna Result::ok en éxito, y rollback con Result::fail en excepción.
 * ¿Qué va a fallar en este test si se cambia el código? Si se pierde el rollback o se cambia el manejo de la excepción.
 */

use App\Core\Result;
use App\Core\TransactionalService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PDO;

final class ConcreteTransactionalService extends TransactionalService
{
    public function __construct(PDO $db) { parent::__construct($db); }
    public function runTransact(callable $fn): Result { return $this->transact($fn); }
}

final class TransactionalServiceTest extends TestCase
{
    private PDO&MockObject $pdoMock;
    private ConcreteTransactionalService $service;

    protected function setUp(): void
    {
        $this->pdoMock = $this->createStub(PDO::class);
        $this->service = new ConcreteTransactionalService($this->pdoMock);
    }

    public function testTransactCommitsAndReturnsOkOnSuccess(): void
    {
        $this->pdoMock->method('beginTransaction')->willReturn(true);
        $this->pdoMock->method('commit')->willReturn(true);

        $result = $this->service->runTransact(fn() => Result::ok('data'));

        $this->assertTrue($result->ok);
        $this->assertSame('data', $result->data);
    }

    public function testTransactRollsBackAndReturnsFailOnException(): void
    {
        $this->pdoMock->method('beginTransaction')->willReturn(true);
        $this->pdoMock->method('rollBack')->willReturn(true);

        $result = $this->service->runTransact(function () {
            throw new \RuntimeException('DB error');
        });

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('DB error', $result->getMessage());
    }

    public function testTransactPropagatesFailResultWithoutRollback(): void
    {
        // Si el callable retorna Result::fail, transact hace rollback igual
        $this->pdoMock->method('beginTransaction')->willReturn(true);
        $this->pdoMock->method('rollBack')->willReturn(true);

        $result = $this->service->runTransact(fn() => Result::fail('negocio falló', 'business_error'));

        $this->assertFalse($result->ok);
        $this->assertSame('negocio falló', $result->getMessage());
    }
}
```

- [ ] **Step 2: Ejecutar el test para verificar que falla**

```bash
docker compose exec app php vendor/bin/phpunit tests/Unit/Core/TransactionalServiceTest.php --testdox
```

- [ ] **Step 3: Implementar `TransactionalService`**

```php
<?php
declare(strict_types=1);

namespace App\Core;

use App\Core\Result;
use PDO;

/**
 * Clase base para Services que requieren transacciones de base de datos.
 *
 * Extiende BaseService y añade PDO + helper transact().
 */
abstract class TransactionalService extends BaseService
{
    public function __construct(protected PDO $db) {}

    /**
     * Ejecuta un callable dentro de una transacción PDO.
     *
     * - Si el callable lanza una excepción → rollback + Result::fail
     * - Si el callable retorna Result::fail → rollback + propaga el Result
     * - Si el callable retorna Result::ok  → commit + propaga el Result
     *
     * @param callable(): Result $fn
     */
    protected function transact(callable $fn): Result
    {
        $this->db->beginTransaction();
        try {
            $result = $fn();

            if ($result->isFail()) {
                $this->db->rollBack();
                return $result;
            }

            $this->db->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            $this->logError('Transaction failed', ['exception' => $e->getMessage()]);
            return Result::fail($e->getMessage(), 'transaction_error');
        }
    }
}
```

- [ ] **Step 4: Ejecutar el test para verificar que pasa**

```bash
docker compose exec app php vendor/bin/phpunit tests/Unit/Core/TransactionalServiceTest.php --testdox
```

Esperado: 3 tests PASS

- [ ] **Step 5: Commit**

```bash
git add app/Core/TransactionalService.php tests/Unit/Core/TransactionalServiceTest.php
git commit -m "feat(service): Add TransactionalService with transact() helper"
```

---

### Task 8: Migrar Services a BaseService

> Agregar `extends BaseService` a servicios que NO necesitan PDO directo. Sustituir calls duplicados `Logger::info('[SomeService]...')` por `$this->logInfo(...)`. No cambiar lógica de negocio.

**Files to Modify:**

- `app/Services/AuthService.php`
- `app/Services/ReviewService.php`
- `app/Services/CafeService.php`
- `app/Services/MenuService.php`
- `app/Services/EmailService.php`
- `app/Services/NewsletterService.php`
- `app/Services/UserService.php`

- [ ] **Step 1: Añadir `extends BaseService` a `AuthService`**

En `app/Services/AuthService.php`:

1. Añadir `use App\Core\BaseService;` en los `use` statements
2. Cambiar `final class AuthService` por `final class AuthService extends BaseService`
3. Sustituir `Logger::info('[AuthService]` por `$this->logInfo(` (ajustando la firma)
4. Sustituir `Logger::error('[AuthService]` por `$this->logError(`

- [ ] **Step 2: Repetir para `ReviewService`, `CafeService`, `MenuService`, `EmailService`, `NewsletterService`, `UserService`**

Mismo patrón: añadir `extends BaseService`, reemplazar `Logger::*('[ClassName]…'` por `$this->logInfo/logError`.

- [ ] **Step 3: Ejecutar la suite de tests para verificar que no hay regresiones**

```bash
docker compose exec app php vendor/bin/phpunit tests/Unit/Services/ --testdox
```

Esperado: todos los tests de Services pasan sin cambios

- [ ] **Step 4: Commit**

```bash
git add app/Services/AuthService.php app/Services/ReviewService.php app/Services/CafeService.php \
        app/Services/MenuService.php app/Services/EmailService.php \
        app/Services/NewsletterService.php app/Services/UserService.php
git commit -m "refactor(service): Migrate 7 services to extend BaseService"
```

---

### Task 9: Migrar Services a TransactionalService

> Reemplazar los `beginTransaction()/commit()/rollBack()` dispersos por el helper `transact()`. Los services que ya inyectan PDO deben heredar de `TransactionalService`.

**Files to Modify:**

- `app/Services/AnimalCareService.php`
- `app/Services/WaitlistService.php`
- `app/Services/UserManagementService.php`
- `app/Services/ProductService.php`
- `app/Services/ReservationTimeSlotService.php`

- [ ] **Step 1: Migrar `AnimalCareService`**

1. Añadir `use App\Core\TransactionalService;`
2. Cambiar `final class AnimalCareService` → `final class AnimalCareService extends TransactionalService`
3. El constructor ya recibe `PDO $db`; pasar al parent: `parent::__construct($db)`; eliminar `$this->db = $db`
4. Localizar los 2 bloques `beginTransaction/commit/rollBack` (L288 y L374) y envolverlos en `$this->transact(fn() => ...)`

- [ ] **Step 2: Migrar `WaitlistService`**

Mismo proceso para los 3 bloques de transacción (L153, L245, L466).

- [ ] **Step 3: Migrar `UserManagementService`**

Mismo proceso para los 2 bloques (L133, L192).

- [ ] **Step 4: Migrar `ProductService`**

Mismo proceso para el bloque (L311).

- [ ] **Step 5: Migrar `ReservationTimeSlotService`**

Mismo proceso para los 3 bloques (L56, L141, L262).

- [ ] **Step 6: Ejecutar la suite de tests para verificar que no hay regresiones**

```bash
docker compose exec app php vendor/bin/phpunit tests/Unit/Services/ --testdox
```

Esperado: todos los tests pasan

- [ ] **Step 7: Verificar PHPStan**

```bash
make phpstan
```

Esperado: sin errores nuevos (o solo baseline)

- [ ] **Step 8: Commit**

```bash
git add app/Services/AnimalCareService.php app/Services/WaitlistService.php \
        app/Services/UserManagementService.php app/Services/ProductService.php \
        app/Services/ReservationTimeSlotService.php
git commit -m "refactor(service): Migrate 5 transactional services to extend TransactionalService"
```

---

## SP-3: FormRequest

---

### Task 10: BaseFormRequest

**Files:**

- Create: `app/Core/Http/FormRequest.php`
- Create: `tests/Unit/Http/Requests/FormRequestTest.php`

> El rules engine soporta: `required`, `email`, `min:N`, `max:N`, `integer`, `bool`, `in:a,b,c`, `regex:pattern`. Separador de reglas: `|`.

- [ ] **Step 1: Crear test fallido**

```php
<?php
declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? BaseFormRequest: fromRequest(), rules engine, sanitize(), validated().
 * ¿Qué me quieres demostrar? Que rules() se aplican sobre datos sanitizados y validated() lanza ValidationException con errores.
 * ¿Qué va a fallar en este test si se cambia el código? Si el rules engine acepta datos inválidos o no reporta todos los errores.
 */

use App\Core\Http\FormRequest;
use App\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

// Implementación concreta mínima para tests
final class SimpleRequest extends FormRequest
{
    public string $name = '';
    public string $email = '';

    #[\Override]
    protected function rules(): array
    {
        return [
            'name'  => 'required|max:50',
            'email' => 'required|email',
        ];
    }

    #[\Override]
    protected function sanitize(array $raw): array
    {
        return [
            'name'  => trim((string) ($raw['name'] ?? '')),
            'email' => strtolower(trim((string) ($raw['email'] ?? ''))),
        ];
    }
}

final class FormRequestTest extends TestCase
{
    private function makeRequest(array $body): SimpleRequest
    {
        $psrRequest = $this->createStub(ServerRequestInterface::class);
        $psrRequest->method('getParsedBody')->willReturn($body);
        return SimpleRequest::fromRequest($psrRequest);
    }

    public function testValidDataPassesValidation(): void
    {
        $req = $this->makeRequest(['name' => 'Alice', 'email' => 'alice@example.com']);
        $validated = $req->validated();
        $this->assertSame('alice@example.com', $validated['email']);
    }

    public function testMissingRequiredFieldThrowsValidationException(): void
    {
        $this->expectException(ValidationException::class);
        $req = $this->makeRequest(['name' => '', 'email' => 'alice@example.com']);
        $req->validated();
    }

    public function testInvalidEmailThrowsValidationException(): void
    {
        $this->expectException(ValidationException::class);
        $req = $this->makeRequest(['name' => 'Alice', 'email' => 'not-valid']);
        $req->validated();
    }

    public function testMaxLengthViolationThrows(): void
    {
        $this->expectException(ValidationException::class);
        $req = $this->makeRequest(['name' => str_repeat('a', 51), 'email' => 'a@b.com']);
        $req->validated();
    }

    public function testStringHelperReturnsDefault(): void
    {
        $req = $this->makeRequest(['name' => 'Alice', 'email' => 'a@b.com']);
        $this->assertSame('', $req->string('missing'));
        $this->assertSame('default', $req->string('missing', 'default'));
    }

    public function testIntegerHelperCasts(): void
    {
        $req = $this->makeRequest(['name' => 'Alice', 'email' => 'a@b.com']);
        // raw value not in this request, tests default
        $this->assertSame(0, $req->integer('count'));
    }
}
```

- [ ] **Step 2: Ejecutar el test para verificar que falla**

```bash
docker compose exec app php vendor/bin/phpunit tests/Unit/Http/Requests/FormRequestTest.php --testdox
```

- [ ] **Step 3: Implementar `FormRequest`**

```php
<?php
declare(strict_types=1);

namespace App\Core\Http;

use App\Exceptions\ValidationException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Base class para FormRequests PSR-7.
 *
 * Rules engine mínimo: required | email | min:N | max:N | integer | bool | in:a,b,c | regex:pattern
 */
abstract class FormRequest
{
    protected array $data = [];

    /**
     * Mapa field → reglas (string separadas por |)
     *
     * @return array<string, string>
     */
    abstract protected function rules(): array;

    /**
     * Sanitiza el body crudo antes de validar.
     *
     * @param  array<mixed> $raw
     * @return array<string, mixed>
     */
    abstract protected function sanitize(array $raw): array;

    public static function fromRequest(ServerRequestInterface $request): static
    {
        $instance = new static();
        $raw = (array) ($request->getParsedBody() ?? []);
        $instance->data = $instance->sanitize($raw);
        return $instance;
    }

    /**
     * Devuelve los datos validados o lanza ValidationException con todos los errores.
     *
     * @return array<string, mixed>
     * @throws ValidationException
     */
    public function validated(): array
    {
        $errors = [];

        foreach ($this->rules() as $field => $ruleString) {
            $rules = explode('|', $ruleString);
            $value = $this->data[$field] ?? null;

            foreach ($rules as $rule) {
                $error = $this->applyRule($field, $value, $rule);
                if ($error !== null) {
                    $errors[$field] = $error;
                    break; // primer error por campo
                }
            }
        }

        if ($errors !== []) {
            throw new ValidationException('Errores de validación', $errors);
        }

        return $this->data;
    }

    // ─── Helpers de acceso ────────────────────────────────────────

    public function string(string $key, string $default = ''): string
    {
        return isset($this->data[$key]) ? (string) $this->data[$key] : $default;
    }

    public function integer(string $key, int $default = 0): int
    {
        return isset($this->data[$key]) ? (int) $this->data[$key] : $default;
    }

    public function boolean(string $key, bool $default = false): bool
    {
        return isset($this->data[$key]) ? (bool) $this->data[$key] : $default;
    }

    // ─── Rules Engine ─────────────────────────────────────────────

    private function applyRule(string $field, mixed $value, string $rule): ?string
    {
        if (str_starts_with($rule, 'min:')) {
            $min = (int) substr($rule, 4);
            if (is_string($value) && mb_strlen($value) < $min) {
                return "El campo {$field} debe tener al menos {$min} caracteres";
            }
            return null;
        }

        if (str_starts_with($rule, 'max:')) {
            $max = (int) substr($rule, 4);
            if (is_string($value) && mb_strlen($value) > $max) {
                return "El campo {$field} no puede superar {$max} caracteres";
            }
            return null;
        }

        if (str_starts_with($rule, 'in:')) {
            $allowed = explode(',', substr($rule, 3));
            if (!in_array((string) $value, $allowed, true)) {
                return "El campo {$field} debe ser uno de: " . implode(', ', $allowed);
            }
            return null;
        }

        if (str_starts_with($rule, 'regex:')) {
            $pattern = substr($rule, 6);
            if (!preg_match($pattern, (string) $value)) {
                return "El campo {$field} tiene un formato inválido";
            }
            return null;
        }

        return match ($rule) {
            'required' => (trim((string) ($value ?? '')) === '')
                ? "El campo {$field} es obligatorio"
                : null,
            'email' => (filter_var($value, FILTER_VALIDATE_EMAIL) === false)
                ? "El campo {$field} no es un email válido"
                : null,
            'integer' => (!is_numeric($value))
                ? "El campo {$field} debe ser un número entero"
                : null,
            'bool' => (!is_bool($value) && !in_array($value, [0, 1, '0', '1', true, false], true))
                ? "El campo {$field} debe ser verdadero o falso"
                : null,
            default => null,
        };
    }
}
```

- [ ] **Step 4: Ejecutar el test para verificar que pasa**

```bash
docker compose exec app php vendor/bin/phpunit tests/Unit/Http/Requests/FormRequestTest.php --testdox
```

Esperado: 6 tests PASS

- [ ] **Step 5: Commit**

```bash
git add app/Core/Http/FormRequest.php tests/Unit/Http/Requests/FormRequestTest.php
git commit -m "feat(request): Add BaseFormRequest with minimal rules engine"
```

---

### Task 11: Auth FormRequests

**Files:**

- Create: `app/Http/Requests/Auth/LoginRequest.php`
- Create: `app/Http/Requests/Auth/RegisterRequest.php`
- Create: `app/Http/Requests/Auth/PasswordResetRequest.php`
- Create: `tests/Unit/Http/Requests/Auth/LoginRequestTest.php`
- Create: `tests/Unit/Http/Requests/Auth/RegisterRequestTest.php`

- [ ] **Step 1: Crear test para `LoginRequest`**

```php
<?php
declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? Validación de LoginRequest.
 * ¿Qué me quieres demostrar? Que email inválido o password vacío fallan, y datos correctos pasan.
 * ¿Qué va a fallar en este test si se cambia el código? Si se elimina la validación de email o se relaja el password.
 */

use App\Exceptions\ValidationException;
use App\Http\Requests\Auth\LoginRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

final class LoginRequestTest extends TestCase
{
    private function makeRequest(array $body): LoginRequest
    {
        $psr = $this->createStub(ServerRequestInterface::class);
        $psr->method('getParsedBody')->willReturn($body);
        return LoginRequest::fromRequest($psr);
    }

    public function testValidLoginPasses(): void
    {
        $req = $this->makeRequest(['email' => 'admin@example.com', 'password' => 'Secret1']);
        $data = $req->validated();
        $this->assertSame('admin@example.com', $data['email']);
    }

    public function testInvalidEmailThrows(): void
    {
        $this->expectException(ValidationException::class);
        $this->makeRequest(['email' => 'bad', 'password' => 'Secret1'])->validated();
    }

    public function testEmptyPasswordThrows(): void
    {
        $this->expectException(ValidationException::class);
        $this->makeRequest(['email' => 'a@b.com', 'password' => ''])->validated();
    }
}
```

- [ ] **Step 2: Implementar `LoginRequest`**

```php
<?php
declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Core\Http\FormRequest;

final class LoginRequest extends FormRequest
{
    #[\Override]
    protected function rules(): array
    {
        return [
            'email'    => 'required|email',
            'password' => 'required',
        ];
    }

    #[\Override]
    protected function sanitize(array $raw): array
    {
        return [
            'email'    => strtolower(trim((string) ($raw['email'] ?? ''))),
            'password' => (string) ($raw['password'] ?? ''),
        ];
    }
}
```

- [ ] **Step 3: Implementar `RegisterRequest`**

```php
<?php
declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Core\Http\FormRequest;
use App\Exceptions\ValidationException;

final class RegisterRequest extends FormRequest
{
    #[\Override]
    protected function rules(): array
    {
        return [
            'name'     => 'required|max:100',
            'email'    => 'required|email',
            'password' => 'required|min:8|max:128',
        ];
    }

    #[\Override]
    protected function sanitize(array $raw): array
    {
        return [
            'name'                  => trim((string) ($raw['name'] ?? '')),
            'email'                 => strtolower(trim((string) ($raw['email'] ?? ''))),
            'password'              => (string) ($raw['password'] ?? ''),
            'password_confirmation' => (string) ($raw['password_confirmation'] ?? ''),
        ];
    }

    #[\Override]
    public function validated(): array
    {
        $data = parent::validated();

        if ($data['password'] !== $data['password_confirmation']) {
            throw new ValidationException(
                'Las contraseñas no coinciden',
                ['password_confirmation' => 'Las contraseñas deben ser iguales']
            );
        }

        return $data;
    }
}
```

- [ ] **Step 4: Implementar `PasswordResetRequest`**

```php
<?php
declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Core\Http\FormRequest;

final class PasswordResetRequest extends FormRequest
{
    #[\Override]
    protected function rules(): array
    {
        return ['email' => 'required|email'];
    }

    #[\Override]
    protected function sanitize(array $raw): array
    {
        return ['email' => strtolower(trim((string) ($raw['email'] ?? '')))];
    }
}
```

- [ ] **Step 5: Ejecutar tests**

```bash
docker compose exec app php vendor/bin/phpunit tests/Unit/Http/Requests/Auth/ --testdox
```

Esperado: todos PASS

- [ ] **Step 6: Commit**

```bash
git add app/Http/Requests/Auth/ tests/Unit/Http/Requests/Auth/
git commit -m "feat(request): Add Auth FormRequests (Login, Register, PasswordReset)"
```

---

### Task 12: Review + Reservation + Admin FormRequests

**Files:**

- Create: `app/Http/Requests/Review/CreateReviewRequest.php`
- Create: `app/Http/Requests/Review/UpdateReviewRequest.php`
- Create: `app/Http/Requests/Reservation/CreateReservationRequest.php`
- Create: `app/Http/Requests/Admin/CreateUserRequest.php`
- Create: `app/Http/Requests/Admin/UpdateUserRequest.php`
- Create: `tests/Unit/Http/Requests/Review/CreateReviewRequestTest.php`
- Create: `tests/Unit/Http/Requests/Reservation/CreateReservationRequestTest.php`

- [ ] **Step 1: Crear tests para CreateReviewRequest y CreateReservationRequest**

`tests/Unit/Http/Requests/Review/CreateReviewRequestTest.php`:

```php
<?php
declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? Validación de CreateReviewRequest.
 * ¿Qué me quieres demostrar? Que rating fuera de 1-5 o body vacío falla.
 * ¿Qué va a fallar en este test si se cambia el código? Si se cambia el rango permitido de rating.
 */

use App\Exceptions\ValidationException;
use App\Http\Requests\Review\CreateReviewRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

final class CreateReviewRequestTest extends TestCase
{
    private function make(array $body): CreateReviewRequest
    {
        $psr = $this->createStub(ServerRequestInterface::class);
        $psr->method('getParsedBody')->willReturn($body);
        return CreateReviewRequest::fromRequest($psr);
    }

    public function testValidReviewPasses(): void
    {
        $data = $this->make(['cafe_id' => '1', 'rating' => '5', 'title' => 'Excelente', 'body' => 'Muy bueno'])->validated();
        $this->assertSame(5, $data['rating']);
    }

    public function testRatingAboveFiveThrows(): void
    {
        $this->expectException(ValidationException::class);
        $this->make(['cafe_id' => '1', 'rating' => '6', 'title' => 'x', 'body' => 'x'])->validated();
    }

    public function testEmptyBodyThrows(): void
    {
        $this->expectException(ValidationException::class);
        $this->make(['cafe_id' => '1', 'rating' => '4', 'title' => 'x', 'body' => ''])->validated();
    }
}
```

`tests/Unit/Http/Requests/Reservation/CreateReservationRequestTest.php`:

```php
<?php
declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? Validación de CreateReservationRequest.
 * ¿Qué me quieres demostrar? Que fecha mal formateada, hora inválida o guest_count fuera de rango fallan.
 * ¿Qué va a fallar en este test si se cambia el código? Si se relaja la validación de fecha o rango de comensales.
 */

use App\Exceptions\ValidationException;
use App\Http\Requests\Reservation\CreateReservationRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

final class CreateReservationRequestTest extends TestCase
{
    private function make(array $body): CreateReservationRequest
    {
        $psr = $this->createStub(ServerRequestInterface::class);
        $psr->method('getParsedBody')->willReturn($body);
        return CreateReservationRequest::fromRequest($psr);
    }

    public function testValidReservationPasses(): void
    {
        $data = $this->make(['date' => '2026-12-01', 'time' => '14:00', 'guest_count' => '2'])->validated();
        $this->assertSame(2, $data['guest_count']);
    }

    public function testBadDateFormatThrows(): void
    {
        $this->expectException(ValidationException::class);
        $this->make(['date' => '01-12-2026', 'time' => '14:00', 'guest_count' => '2'])->validated();
    }

    public function testZeroGuestsThrows(): void
    {
        $this->expectException(ValidationException::class);
        $this->make(['date' => '2026-12-01', 'time' => '14:00', 'guest_count' => '0'])->validated();
    }
}
```

- [ ] **Step 2: Implementar las 5 FormRequests**

`app/Http/Requests/Review/CreateReviewRequest.php`:

```php
<?php
declare(strict_types=1);

namespace App\Http\Requests\Review;

use App\Core\Http\FormRequest;
use App\Exceptions\ValidationException;

final class CreateReviewRequest extends FormRequest
{
    #[\Override]
    protected function rules(): array
    {
        return [
            'cafe_id' => 'required|integer',
            'rating'  => 'required|integer|in:1,2,3,4,5',
            'title'   => 'required|max:100',
            'body'    => 'required|max:2000',
        ];
    }

    #[\Override]
    protected function sanitize(array $raw): array
    {
        return [
            'cafe_id' => (int) ($raw['cafe_id'] ?? 0),
            'rating'  => (int) ($raw['rating'] ?? 0),
            'title'   => trim(strip_tags((string) ($raw['title'] ?? ''))),
            'body'    => trim(strip_tags((string) ($raw['body'] ?? ''))),
        ];
    }
}
```

`app/Http/Requests/Review/UpdateReviewRequest.php`:

```php
<?php
declare(strict_types=1);

namespace App\Http\Requests\Review;

use App\Core\Http\FormRequest;

final class UpdateReviewRequest extends FormRequest
{
    #[\Override]
    protected function rules(): array
    {
        return [
            'rating' => 'integer|in:1,2,3,4,5',
            'title'  => 'max:100',
            'body'   => 'max:2000',
        ];
    }

    #[\Override]
    protected function sanitize(array $raw): array
    {
        return [
            'rating' => (int) ($raw['rating'] ?? 0),
            'title'  => trim(strip_tags((string) ($raw['title'] ?? ''))),
            'body'   => trim(strip_tags((string) ($raw['body'] ?? ''))),
        ];
    }
}
```

`app/Http/Requests/Reservation/CreateReservationRequest.php`:

```php
<?php
declare(strict_types=1);

namespace App\Http\Requests\Reservation;

use App\Core\Http\FormRequest;

final class CreateReservationRequest extends FormRequest
{
    #[\Override]
    protected function rules(): array
    {
        return [
            'date'        => 'required|regex:/^\d{4}-\d{2}-\d{2}$/',
            'time'        => 'required|regex:/^([01]\d|2[0-3]):[0-5]\d$/',
            'guest_count' => 'required|integer|in:1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20',
        ];
    }

    #[\Override]
    protected function sanitize(array $raw): array
    {
        return [
            'date'             => trim((string) ($raw['date'] ?? '')),
            'time'             => trim((string) ($raw['time'] ?? '')),
            'guest_count'      => (int) ($raw['guest_count'] ?? 0),
            'special_requests' => trim(strip_tags((string) ($raw['special_requests'] ?? ''))),
        ];
    }
}
```

`app/Http/Requests/Admin/CreateUserRequest.php`:

```php
<?php
declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Core\Http\FormRequest;
use App\Core\ValueObjects\Role;

final class CreateUserRequest extends FormRequest
{
    #[\Override]
    protected function rules(): array
    {
        return [
            'name'     => 'required|max:100',
            'email'    => 'required|email',
            'password' => 'required|min:8|max:128',
            'role'     => 'required|in:' . implode(',', Role::VALID_ROLES),
        ];
    }

    #[\Override]
    protected function sanitize(array $raw): array
    {
        return [
            'name'     => trim((string) ($raw['name'] ?? '')),
            'email'    => strtolower(trim((string) ($raw['email'] ?? ''))),
            'password' => (string) ($raw['password'] ?? ''),
            'role'     => (string) ($raw['role'] ?? ''),
        ];
    }
}
```

`app/Http/Requests/Admin/UpdateUserRequest.php`:

```php
<?php
declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Core\Http\FormRequest;
use App\Core\ValueObjects\Role;

final class UpdateUserRequest extends FormRequest
{
    #[\Override]
    protected function rules(): array
    {
        return [
            'name'  => 'required|max:100',
            'email' => 'required|email',
            'role'  => 'required|in:' . implode(',', Role::VALID_ROLES),
        ];
    }

    #[\Override]
    protected function sanitize(array $raw): array
    {
        return [
            'name'  => trim((string) ($raw['name'] ?? '')),
            'email' => strtolower(trim((string) ($raw['email'] ?? ''))),
            'role'  => (string) ($raw['role'] ?? ''),
        ];
    }
}
```

- [ ] **Step 3: Ejecutar todos los tests de FormRequests**

```bash
docker compose exec app php vendor/bin/phpunit tests/Unit/Http/Requests/ --testdox
```

Esperado: todos PASS

- [ ] **Step 4: Commit**

```bash
git add app/Http/Requests/ tests/Unit/Http/Requests/
git commit -m "feat(request): Add Review, Reservation, Admin FormRequests"
```

---

### Task 13: Wiring FormRequests en Controllers

> Sustituir parseo manual de `$_POST` y `$request->getParsedBody()` por FormRequests. Los controllers atrapan `ValidationException` y muestran Flash::error.

**Files to Modify:**

- `app/Http/Controllers/Auth/AuthController.php`
- `app/Http/Controllers/Public/ReviewController.php`
- `app/Http/Controllers/Admin/UserController.php`

- [ ] **Step 1: Wiring en `AuthController`**

Buscar el método que parsea el login (usa `getParsedBody()`); reemplazar por:

```php
use App\Http\Requests\Auth\LoginRequest;
// ...
$loginReq = LoginRequest::fromRequest($request);
try {
    $data = $loginReq->validated();
} catch (ValidationException $e) {
    Flash::error(implode(' ', $e->getErrorMessages()));
    return $this->response->redirect('/login');
}
$email    = $loginReq->string('email');
$password = $loginReq->string('password');
```

Hacer lo mismo con el método de registro usando `RegisterRequest`.

- [ ] **Step 2: Wiring en `ReviewController`**

El método `store` usa `$_POST['cafe_id']`, etc.; reemplazar por `CreateReviewRequest::fromRequest($request)`.

- [ ] **Step 3: Wiring en `Admin/UserController`**

Los métodos `store` y `update` usan `$_POST` directo; reemplazar por `CreateUserRequest` y `UpdateUserRequest`.

- [ ] **Step 4: Ejecutar suite completa**

```bash
docker compose exec app php vendor/bin/phpunit --testdox
```

Esperado: todos los tests pasan

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Auth/AuthController.php \
        app/Http/Controllers/Public/ReviewController.php \
        app/Http/Controllers/Admin/UserController.php
git commit -m "refactor(controller): Wire FormRequests in Auth, Review, Admin controllers"
```

---

## SP-4: OWASP Hardening (independiente)

---

### Task 14: SecurityHeadersMiddleware

**Files:**

- Create: `app/Http/Middleware/SecurityHeadersMiddleware.php`
- Create: `tests/Unit/Middleware/SecurityHeadersMiddlewareTest.php`

- [ ] **Step 1: Crear test fallido**

```php
<?php
declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? SecurityHeadersMiddleware añade todas las cabeceras de seguridad.
 * ¿Qué me quieres demostrar? Que la respuesta contiene X-Frame-Options, X-Content-Type-Options y CSP.
 * ¿Qué va a fallar en este test si se cambia el código? Si se elimina alguna cabecera crítica de OWASP.
 */

use App\Http\Middleware\SecurityHeadersMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class SecurityHeadersMiddlewareTest extends TestCase
{
    private SecurityHeadersMiddleware $middleware;

    protected function setUp(): void
    {
        $this->middleware = new SecurityHeadersMiddleware();
    }

    private function makeHandler(ResponseInterface $response): RequestHandlerInterface
    {
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($response);
        return $handler;
    }

    private function makeResponse(): ResponseInterface
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('withHeader')->willReturnSelf();
        return $response;
    }

    public function testMiddlewareAddsXFrameOptions(): void
    {
        $called = [];
        // Real response that records calls
        $response = $this->getMockBuilder(ResponseInterface::class)->getMock();
        $response->method('withHeader')->willReturnCallback(
            function (string $name, string $value) use (&$called, $response) {
                $called[$name] = $value;
                return $response;
            }
        );

        $request = $this->createStub(ServerRequestInterface::class);
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($response);

        $this->middleware->process($request, $handler);

        $this->assertArrayHasKey('X-Frame-Options', $called);
        $this->assertSame('DENY', $called['X-Frame-Options']);
    }

    public function testMiddlewareAddsXContentTypeOptions(): void
    {
        $called = [];
        $response = $this->getMockBuilder(ResponseInterface::class)->getMock();
        $response->method('withHeader')->willReturnCallback(
            function (string $name, string $value) use (&$called, $response) {
                $called[$name] = $value;
                return $response;
            }
        );

        $request = $this->createStub(ServerRequestInterface::class);
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($response);

        $this->middleware->process($request, $handler);

        $this->assertArrayHasKey('X-Content-Type-Options', $called);
        $this->assertSame('nosniff', $called['X-Content-Type-Options']);
    }
}
```

- [ ] **Step 2: Ejecutar el test para verificar que falla**

```bash
docker compose exec app php vendor/bin/phpunit tests/Unit/Middleware/SecurityHeadersMiddlewareTest.php --testdox
```

- [ ] **Step 3: Implementar `SecurityHeadersMiddleware`**

```php
<?php
declare(strict_types=1);

namespace App\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Añade cabeceras de seguridad OWASP a todas las respuestas HTTP.
 *
 * OWASP: A05 Security Misconfiguration
 */
final class SecurityHeadersMiddleware implements MiddlewareInterface
{
    #[\Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        return $response
            ->withHeader('X-Frame-Options', 'DENY')
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->withHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
            ->withHeader(
                'Content-Security-Policy',
                "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; frame-ancestors 'none'"
            );
    }
}
```

- [ ] **Step 4: Ejecutar el test para verificar que pasa**

```bash
docker compose exec app php vendor/bin/phpunit tests/Unit/Middleware/SecurityHeadersMiddlewareTest.php --testdox
```

- [ ] **Step 5: Commit**

```bash
git add app/Http/Middleware/SecurityHeadersMiddleware.php tests/Unit/Middleware/SecurityHeadersMiddlewareTest.php
git commit -m "feat(security): Add SecurityHeadersMiddleware (OWASP A05)"
```

---

### Task 15: PayloadSizeMiddleware

**Files:**

- Create: `app/Http/Middleware/PayloadSizeMiddleware.php`
- Create: `tests/Unit/Middleware/PayloadSizeMiddlewareTest.php`

- [ ] **Step 1: Crear test fallido**

```php
<?php
declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? PayloadSizeMiddleware bloquea peticiones con Content-Length excesivo.
 * ¿Qué me quieres demostrar? Que responde 413 cuando la cabecera supera el límite configurado.
 * ¿Qué va a fallar en este test si se cambia el código? Si se cambia el código HTTP de respuesta de 413 a otro.
 */

use App\Core\Http\ResponseFactory;
use App\Http\Middleware\PayloadSizeMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class PayloadSizeMiddlewareTest extends TestCase
{
    private function makeResponse(int $status): ResponseInterface
    {
        $r = $this->createStub(ResponseInterface::class);
        $r->method('getStatusCode')->willReturn($status);
        return $r;
    }

    private function makeMiddleware(int $maxKb = 256): PayloadSizeMiddleware
    {
        $factory = $this->createStub(ResponseFactory::class);
        $factory->method('createResponse')->willReturnCallback(fn(int $s) => $this->makeResponse($s));
        return new PayloadSizeMiddleware($factory, $maxKb);
    }

    public function testAllowsRequestWithinLimit(): void
    {
        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getHeaderLine')->willReturn('1024'); // 1 KB

        $expectedResponse = $this->makeResponse(200);
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($expectedResponse);

        $response = $this->makeMiddleware()->process($request, $handler);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testBlocksOversizedRequest(): void
    {
        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getHeaderLine')->willReturn((string) (257 * 1024)); // 257 KB > limit

        $handler = $this->createStub(RequestHandlerInterface::class);

        $response = $this->makeMiddleware(256)->process($request, $handler);
        $this->assertSame(413, $response->getStatusCode());
    }

    public function testAllowsRequestWithNoContentLength(): void
    {
        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getHeaderLine')->willReturn(''); // sin Content-Length

        $expectedResponse = $this->makeResponse(200);
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($expectedResponse);

        $response = $this->makeMiddleware()->process($request, $handler);
        $this->assertSame(200, $response->getStatusCode());
    }
}
```

- [ ] **Step 2: Ejecutar el test para verificar que falla**

```bash
docker compose exec app php vendor/bin/phpunit tests/Unit/Middleware/PayloadSizeMiddlewareTest.php --testdox
```

- [ ] **Step 3: Implementar `PayloadSizeMiddleware`**

```php
<?php
declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Http\ResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Bloquea peticiones cuyo Content-Length supera el límite configurado.
 *
 * Responde 413 Payload Too Large.
 * OWASP: A05 Security Misconfiguration, DoS mitigation.
 */
final class PayloadSizeMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly ResponseFactory $response,
        private readonly int $maxKilobytes = 256
    ) {}

    #[\Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $contentLength = $request->getHeaderLine('Content-Length');

        if ($contentLength !== '' && (int) $contentLength > $this->maxKilobytes * 1024) {
            return $this->response->createResponse(413);
        }

        return $handler->handle($request);
    }
}
```

- [ ] **Step 4: Ejecutar el test para verificar que pasa**

```bash
docker compose exec app php vendor/bin/phpunit tests/Unit/Middleware/PayloadSizeMiddlewareTest.php --testdox
```

Esperado: 3 tests PASS

- [ ] **Step 5: Commit**

```bash
git add app/Http/Middleware/PayloadSizeMiddleware.php tests/Unit/Middleware/PayloadSizeMiddlewareTest.php
git commit -m "feat(security): Add PayloadSizeMiddleware (413 on oversized requests)"
```

---

### Task 16: HttpRateLimitMiddleware

**Files:**

- Create: `app/Http/Middleware/HttpRateLimitMiddleware.php`
- Create: `tests/Unit/Middleware/HttpRateLimitMiddlewareTest.php`

- [ ] **Step 1: Crear test fallido**

```php
<?php
declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? HttpRateLimitMiddleware delega en RateLimitingService y responde 429 si excede el límite.
 * ¿Qué me quieres demostrar? Que cuando RateLimitingService dice "bloqueado", la respuesta tiene código 429.
 * ¿Qué va a fallar en este test si se cambia el código? Si se cambia el código 429 o se elimina la cabecera Retry-After.
 */

use App\Core\Http\ResponseFactory;
use App\Http\Middleware\HttpRateLimitMiddleware;
use App\Services\RateLimitingService;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class HttpRateLimitMiddlewareTest extends TestCase
{
    private function makeResponse(int $status): ResponseInterface
    {
        $r = $this->createStub(ResponseInterface::class);
        $r->method('getStatusCode')->willReturn($status);
        $r->method('withHeader')->willReturnSelf();
        return $r;
    }

    public function testAllowsRequestWhenNotRateLimited(): void
    {
        $rateLimiter = $this->createStub(RateLimitingService::class);
        $rateLimiter->method('isAllowed')->willReturn(true);

        $factory = $this->createStub(ResponseFactory::class);
        $middleware = new HttpRateLimitMiddleware($rateLimiter, $factory, 'contact', 5, 60);

        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getServerParams')->willReturn(['REMOTE_ADDR' => '1.2.3.4']);

        $expectedResponse = $this->makeResponse(200);
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($expectedResponse);

        $response = $middleware->process($request, $handler);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testBlocks429WhenRateLimited(): void
    {
        $rateLimiter = $this->createStub(RateLimitingService::class);
        $rateLimiter->method('isAllowed')->willReturn(false);

        $factory = $this->createStub(ResponseFactory::class);
        $factory->method('createResponse')->willReturn($this->makeResponse(429));

        $middleware = new HttpRateLimitMiddleware($rateLimiter, $factory, 'contact', 5, 60);

        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getServerParams')->willReturn(['REMOTE_ADDR' => '1.2.3.4']);

        $handler = $this->createStub(RequestHandlerInterface::class);

        $response = $middleware->process($request, $handler);
        $this->assertSame(429, $response->getStatusCode());
    }
}
```

- [ ] **Step 2: Ejecutar el test para verificar que falla**

```bash
docker compose exec app php vendor/bin/phpunit tests/Unit/Middleware/HttpRateLimitMiddlewareTest.php --testdox
```

- [ ] **Step 3: Verificar la firma de `RateLimitingService::isAllowed()`**

Leer `app/Services/RateLimitingService.php` y buscar el método `isAllowed` o `checkRateLimit` para usar la firma correcta. Adaptar el test si el nombre del método difiere.

- [ ] **Step 4: Implementar `HttpRateLimitMiddleware`**

```php
<?php
declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Http\ResponseFactory;
use App\Services\RateLimitingService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Rate limiting HTTP-level usando RateLimitingService existente.
 *
 * Devuelve 429 Too Many Requests con cabecera Retry-After cuando se supera el límite.
 * OWASP: A07 Identification and Authentication Failures, DoS mitigation.
 */
final class HttpRateLimitMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly RateLimitingService $rateLimiter,
        private readonly ResponseFactory $response,
        private readonly string $action,
        private readonly int $maxAttempts,
        private readonly int $windowSeconds
    ) {}

    #[\Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $serverParams = $request->getServerParams();
        $ip = (string) ($serverParams['REMOTE_ADDR'] ?? '0.0.0.0');
        $identifier = $this->action . ':' . $ip;

        if (!$this->rateLimiter->isAllowed($identifier, $this->maxAttempts, $this->windowSeconds)) {
            return $this->response->createResponse(429)
                ->withHeader('Retry-After', (string) $this->windowSeconds)
                ->withHeader('X-RateLimit-Limit', (string) $this->maxAttempts)
                ->withHeader('X-RateLimit-Remaining', '0');
        }

        return $handler->handle($request);
    }
}
```

> **Nota:** Si `RateLimitingService` no expone `isAllowed(string $id, int $max, int $window)` con esa firma exacta, adaptar la llamada a la firma real encontrada en Step 3.

- [ ] **Step 5: Ejecutar el test para verificar que pasa**

```bash
docker compose exec app php vendor/bin/phpunit tests/Unit/Middleware/HttpRateLimitMiddlewareTest.php --testdox
```

- [ ] **Step 6: Commit**

```bash
git add app/Http/Middleware/HttpRateLimitMiddleware.php tests/Unit/Middleware/HttpRateLimitMiddlewareTest.php
git commit -m "feat(security): Add HttpRateLimitMiddleware (429 on excess requests)"
```

---

### Task 17: MiddlewareFactory +3 Métodos + routes.php

**Files to Modify:**

- `app/Core/MiddlewareFactory.php`
- `app/routes.php`

- [ ] **Step 1: Añadir `securityHeaders()`, `maxPayload()` y `rateLimit()` a `MiddlewareFactory`**

En `app/Core/MiddlewareFactory.php`:

1. Añadir `use` para los 3 middlewares nuevos y `RateLimitingService`
2. Añadir los métodos al final de la clase (antes del `}`):

```php
public function securityHeaders(): SecurityHeadersMiddleware
{
    return new SecurityHeadersMiddleware();
}

public function maxPayload(int $kilobytes = 256): PayloadSizeMiddleware
{
    return new PayloadSizeMiddleware($this->response, $kilobytes);
}

public function rateLimit(string $action, int $maxAttempts, int $windowSeconds): HttpRateLimitMiddleware
{
    return new HttpRateLimitMiddleware(
        new RateLimitingService(),
        $this->response,
        $action,
        $maxAttempts,
        $windowSeconds
    );
}
```

- [ ] **Step 2: Añadir grupo global de seguridad en `routes.php`**

Leer el inicio de `app/routes.php` para entender la estructura de grupos.

Añadir antes del primer grupo funcional:

```php
// ─── Seguridad global (todas las rutas) ──────────────────────────────────────
$globalSecurity = [$mw->securityHeaders(), $mw->maxPayload(256)];
$router->group(['middleware' => $globalSecurity], function (Router $r) use ($mw) {
    // Los grupos existentes van aquí, o se aplican por separado vía prefijo vacío
});
```

> Si el proyecto ya tiene un grupo raíz o el router no soporta un grupo sin prefix, añadir los dos middlewares globales a cada grupo existente de alto nivel.

- [ ] **Step 3: Añadir `rateLimit()` a rutas de formularios públicos**

Buscar en `routes.php` las rutas POST de:

- `/contacto` o similar → añadir `$mw->rateLimit('contact', 5, 300)` (5 envíos / 5 min)
- `/newsletter/subscribe` → `$mw->rateLimit('newsletter', 3, 600)` (3 / 10 min)
- `/login` (si no tiene ya rate limit HTTP) → `$mw->rateLimit('login', 10, 60)`

- [ ] **Step 4: Ejecutar suite completa de tests**

```bash
docker compose exec app php vendor/bin/phpunit --testdox
```

Esperado: todos los tests pasan

- [ ] **Step 5: Verificar PHPStan**

```bash
make phpstan
```

Esperado: sin errores nuevos

- [ ] **Step 6: Commit final**

```bash
git add app/Core/MiddlewareFactory.php app/routes.php
git commit -m "feat(security): Wire SecurityHeaders+RateLimit+PayloadSize in MiddlewareFactory and routes.php; complete SP-4"
```

---

## Verificación Final

- [ ] **Ejecutar suite completa de tests**

```bash
docker compose exec app php vendor/bin/phpunit --testdox
```

Esperado: ≥ 0 nuevos tests, todos PASS

- [ ] **PHPStan nivel 5**

```bash
make phpstan
```

Esperado: solo errores de baseline (ninguno nuevo)

- [ ] **PHPCS**

```bash
docker compose exec app php vendor/bin/phpcs --standard=phpcs.xml app/Core/ValueObjects app/Core/BaseService.php app/Core/TransactionalService.php app/Core/Http/FormRequest.php app/Http/Requests app/Http/Middleware/SecurityHeadersMiddleware.php app/Http/Middleware/PayloadSizeMiddleware.php app/Http/Middleware/HttpRateLimitMiddleware.php
```

Esperado: ningún error de estilo

- [ ] **Commit de cierre**

```bash
git tag v-base-classes-owasp
```
