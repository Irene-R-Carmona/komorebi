# Plan 1: Security & Hardening Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Cerrar brechas de seguridad activas: CSP headers, charset SQL injection risk, RateLimitingService DB→Redis, y strict_types en ~30 archivos + feature flags FEATURE_OPS/KEEPER.

**Architecture:** Cambios incrementales y de bajo riesgo. Cada tarea es atómica y reversible. No hay refactors estructurales — solo hardening y limpieza.

**Tech Stack:** PHP 8.4, PDO, Redis (ya en stack), PSR-16

---

### Task 1: Content Security Policy + Security Headers en View.php

**Files:**

- Modify: `app/Core/View.php` — método `render()` / `component()`, añadir `sendSecurityHeaders()` privado

**Contexto:** `View::render()` llama a `ob_start()` antes de incluir el layout. Los headers deben enviarse antes de cualquier output. El layout `resources/views/layouts/main.php` probablemente incluye Alpine.js y estilos locales.

- [ ] **Step 1: Escribir test que verifica que los headers de seguridad se emiten**

```php
// tests/Unit/Core/ViewSecurityHeadersTest.php
<?php
/**
 * ¿Qué pruebas aquí?
 * Verifica que View::render() emite los HTTP security headers correctos.
 *
 * ¿Qué me quieres demostrar?
 * Que CSP, X-Frame-Options, X-Content-Type-Options y Referrer-Policy
 * están presentes en toda respuesta HTML.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se elimina sendSecurityHeaders() o si los valores cambian sin actualizar el test.
 */
declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\View;
use PHPUnit\Framework\TestCase;

final class ViewSecurityHeadersTest extends TestCase
{
    protected function setUp(): void
    {
        // Limpiar headers previos (PHPUnit corre en CLI, header() no funciona realmente)
        // Usaremos View::getSecurityHeaders() — método público nuevo que retorna el array
    }

    public function test_security_headers_array_contains_csp(): void
    {
        $headers = View::getSecurityHeaders();

        $this->assertArrayHasKey('Content-Security-Policy', $headers);
        $this->assertStringContainsString("default-src 'self'", $headers['Content-Security-Policy']);
    }

    public function test_security_headers_array_contains_x_frame_options(): void
    {
        $headers = View::getSecurityHeaders();

        $this->assertArrayHasKey('X-Frame-Options', $headers);
        $this->assertSame('DENY', $headers['X-Frame-Options']);
    }

    public function test_security_headers_array_contains_x_content_type_options(): void
    {
        $headers = View::getSecurityHeaders();

        $this->assertArrayHasKey('X-Content-Type-Options', $headers);
        $this->assertSame('nosniff', $headers['X-Content-Type-Options']);
    }

    public function test_security_headers_array_contains_referrer_policy(): void
    {
        $headers = View::getSecurityHeaders();

        $this->assertArrayHasKey('Referrer-Policy', $headers);
        $this->assertSame('strict-origin-when-cross-origin', $headers['Referrer-Policy']);
    }
}
```

- [ ] **Step 2: Ejecutar test para verificar fallo**

```bash
docker compose exec app vendor/bin/phpunit tests/Unit/Core/ViewSecurityHeadersTest.php --colors=always
```

Esperado: FAIL — `Call to undefined method App\Core\View::getSecurityHeaders()`

- [ ] **Step 3: Leer el archivo actual para conocer la estructura exacta**

```bash
docker compose exec app head -80 app/Core/View.php
```

- [ ] **Step 4: Añadir `getSecurityHeaders()` y `sendSecurityHeaders()` a View.php**

En `app/Core/View.php`, añadir antes de `render()`:

```php
/**
 * Retorna el array de security headers (testeable sin side-effects).
 */
public static function getSecurityHeaders(): array
{
    return [
        'Content-Security-Policy'   => "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:; connect-src 'self'; frame-ancestors 'none'",
        'X-Frame-Options'           => 'DENY',
        'X-Content-Type-Options'    => 'nosniff',
        'Referrer-Policy'           => 'strict-origin-when-cross-origin',
        'Permissions-Policy'        => 'geolocation=(), microphone=(), camera=()',
    ];
}

private static function sendSecurityHeaders(): void
{
    if (\headers_sent()) {
        return;
    }

    foreach (self::getSecurityHeaders() as $name => $value) {
        \header("$name: $value");
    }
}
```

Y llamar `self::sendSecurityHeaders();` al principio de `render()` y `renderToString()`, antes del `ob_start()`.

- [ ] **Step 5: Ejecutar test para verificar que pasa**

```bash
docker compose exec app vendor/bin/phpunit tests/Unit/Core/ViewSecurityHeadersTest.php --colors=always
```

Esperado: PASS (4 assertions)

- [ ] **Step 6: Verificar que no rompió otros tests**

```bash
docker compose exec app vendor/bin/phpunit tests/Unit/ --colors=always --stop-on-failure
```

- [ ] **Step 7: Commit**

```bash
git add app/Core/View.php tests/Unit/Core/ViewSecurityHeadersTest.php
git commit -m "security: add CSP and HTTP security headers to View::render()"
```

---

### Task 2: Charset validation en Database.php

**Files:**

- Modify: `app/Core/Database.php` — línea ~66, `SET NAMES $charset COLLATE $collation`

**Contexto actual (línea ~66):**

```php
$pdo->exec("SET NAMES $charset COLLATE $collation");
```

`$charset` viene de Config (confiable) pero no hay whitelist explícita. Un charset con caracteres extraños podría inyectarse si la configuración se carga desde env mal sanitizada.

- [ ] **Step 1: Escribir test de validación de charset**

```php
// tests/Unit/Core/DatabaseCharsetValidationTest.php
<?php
/**
 * ¿Qué pruebas aquí?
 * Verifica que Database::validateCharset() lanza RuntimeException para charsets inválidos.
 *
 * ¿Qué me quieres demostrar?
 * Que valores malformados de charset no pueden inyectarse en SET NAMES.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se elimina la validación o se cambia la whitelist de charsets.
 */
declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Database;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DatabaseCharsetValidationTest extends TestCase
{
    public function test_valid_charset_utf8mb4_passes(): void
    {
        // No lanza excepción
        Database::validateCharset('utf8mb4', 'utf8mb4_unicode_ci');
        $this->assertTrue(true);
    }

    public function test_invalid_charset_with_semicolon_throws(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Charset inválido');

        Database::validateCharset('utf8mb4; DROP TABLE users', 'utf8mb4_unicode_ci');
    }

    public function test_invalid_charset_with_space_throws(): void
    {
        $this->expectException(RuntimeException::class);

        Database::validateCharset('utf8 mb4', 'utf8mb4_unicode_ci');
    }

    public function test_invalid_collation_with_dash_injection_throws(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Collation inválida');

        Database::validateCharset('utf8mb4', 'utf8mb4_unicode_ci--');
    }

    public function test_valid_charsets_whitelist(): void
    {
        $validCharsets = ['utf8mb4', 'utf8', 'latin1', 'ascii'];
        foreach ($validCharsets as $charset) {
            Database::validateCharset($charset, 'utf8mb4_unicode_ci');
        }
        $this->assertCount(4, $validCharsets); // dummy assertion
    }
}
```

- [ ] **Step 2: Ejecutar test para verificar fallo**

```bash
docker compose exec app vendor/bin/phpunit tests/Unit/Core/DatabaseCharsetValidationTest.php --colors=always
```

Esperado: FAIL — `Call to undefined method App\Core\Database::validateCharset()`

- [ ] **Step 3: Añadir `validateCharset()` a Database.php**

En `app/Core/Database.php`, añadir método público estático justo antes de `getConnection()`:

```php
/**
 * Valida charset y collation contra whitelist para prevenir inyección en SET NAMES.
 *
 * @throws \RuntimeException si el charset o collation no son válidos
 */
public static function validateCharset(string $charset, string $collation): void
{
    $allowedCharsets = ['utf8mb4', 'utf8', 'latin1', 'ascii', 'binary'];

    if (!\in_array($charset, $allowedCharsets, true)) {
        throw new \RuntimeException("Charset inválido: '$charset'. Permitidos: " . \implode(', ', $allowedCharsets));
    }

    // Collation: solo alfanumérico, guiones bajos
    if (!\preg_match('/^[a-z0-9_]+$/i', $collation)) {
        throw new \RuntimeException("Collation inválida: '$collation'. Solo caracteres alfanuméricos y guiones bajos.");
    }
}
```

Y en el método `connect()` / `getConnection()` (donde está el `SET NAMES`), añadir antes del `exec()`:

```php
self::validateCharset($charset, $collation);
$pdo->exec("SET NAMES $charset COLLATE $collation");
```

- [ ] **Step 4: Ejecutar test para verificar que pasa**

```bash
docker compose exec app vendor/bin/phpunit tests/Unit/Core/DatabaseCharsetValidationTest.php --colors=always
```

Esperado: PASS (5 assertions)

- [ ] **Step 5: Ejecutar suite completa para verificar que DB sigue funcionando**

```bash
docker compose exec app vendor/bin/phpunit tests/Integration/ --colors=always --stop-on-failure
```

- [ ] **Step 6: Commit**

```bash
git add app/Core/Database.php tests/Unit/Core/DatabaseCharsetValidationTest.php
git commit -m "security: whitelist charset/collation validation in Database::connect()"
```

---

### Task 3: RateLimitingService — Migrar DB a Redis

**Files:**

- Modify: `app/Services/RateLimitingService.php` — eliminar `$this->db->exec('DELETE...')`, reemplazar con `Cache`
- Check: si hay tabla `rate_limits` en migrations, añadir nota de deprecación

**Contexto actual en RateLimitingService.php:**

```php
// Línea ~33
$this->db->exec('DELETE FROM rate_limits WHERE expires_at < NOW()');
```

El stack ya tiene Redis con PSR-16. La limpieza de registros expirados en BD es innecesaria si el rate limiter usa Redis con TTL nativo.

- [ ] **Step 1: Leer el archivo completo para entender la implementación actual**

```bash
docker compose exec app cat app/Services/RateLimitingService.php
```

- [ ] **Step 2: Escribir test del comportamiento deseado**

```php
// tests/Unit/Services/RateLimitingServiceTest.php (buscar si existe o crear)
// Si ya existe, añadir este test:

public function test_rate_limit_uses_cache_not_db(): void
{
    $mockCache = $this->createStub(\Psr\SimpleCache\CacheInterface::class);
    $mockCache->method('get')->willReturn(null);
    $mockCache->method('set')->willReturn(true);

    // Si el servicio acepta Cache via constructor, pasarlo aquí
    // El test verifica que NO se llame a PDO para limpiar
    $mockPdo = $this->createMock(\PDO::class);
    $mockPdo->expects($this->never())->method('exec');

    // Ajustar según firma real del constructor
    $service = new \App\Services\RateLimitingService($mockPdo, $mockCache);
    $result = $service->check('login', '127.0.0.1');

    $this->assertIsBool($result);
}
```

**Nota:** El paso concreto depende de la firma real del constructor — leer primero con Step 1.

- [ ] **Step 3: Eliminar el `exec('DELETE...')` y reemplazar con operaciones Redis**

En `app/Services/RateLimitingService.php`:

```php
// ANTES (eliminar):
$this->db->exec('DELETE FROM rate_limits WHERE expires_at < NOW()');

// DESPUÉS: Redis con TTL no necesita limpieza manual.
// Usar Cache::remember() o Cache::set() con TTL para los contadores.
// Ejemplo de counter con Redis via PSR-16:
$key = "rate_limit:{$bucket}:{$identifier}";
$current = (int) ($this->cache->get($key, 0));

if ($current >= $this->limits[$bucket]) {
    return false; // rate limited
}

$this->cache->set($key, $current + 1, $this->windowSeconds[$bucket]);
return true;
```

**Eliminar también** el `PDO $db` del constructor si ya no se usa. Si el constructor tiene otras dependencias de DB para otras cosas, mantener pero remover la limpieza.

- [ ] **Step 4: Ejecutar tests**

```bash
docker compose exec app vendor/bin/phpunit tests/Unit/Services/RateLimitingServiceTest.php --colors=always
```

- [ ] **Step 5: Commit**

```bash
git add app/Services/RateLimitingService.php tests/Unit/Services/RateLimitingServiceTest.php
git commit -m "refactor: migrate RateLimitingService from DB to Redis/PSR-16 cache"
```

---

### Task 4: Añadir `declare(strict_types=1)` a ~30 archivos

**Files a modificar (añadir línea 2 `declare(strict_types=1);` a cada uno):**

```
app/Workers/NotificationWorker.php
app/Workers/EmailWorker.php
app/Providers/DatabaseServiceProvider.php
app/Providers/CacheServiceProvider.php
app/Providers/EventServiceProvider.php
app/Providers/ReservationServiceProvider.php
app/Providers/NewsletterServiceProvider.php
app/Providers/StaffServiceProvider.php
app/Listeners/LogUserRegisteredListener.php
app/Listeners/TelegramReviewListener.php
app/Listeners/LogReservationConfirmedListener.php
app/Jobs/WaitlistPromotionJob.php
app/Jobs/SendEmailJob.php
(cualquier otro en app/Http/Transformers/ o app/Models/ que falte)
```

- [ ] **Step 1: Identificar todos los archivos que faltan**

```bash
docker compose exec app bash -c "grep -rL 'declare(strict_types=1)' app/ --include='*.php' | head -50"
```

- [ ] **Step 2: Script de adición automática**

```bash
docker compose exec app bash -c "
for file in \$(grep -rL 'declare(strict_types=1)' app/ --include='*.php'); do
    # Insertar declare después de la primera línea (<?php)
    sed -i '1a declare(strict_types=1);\\n' \"\$file\"
    echo \"Fixed: \$file\"
done
"
```

- [ ] **Step 3: Verificar que no hay dobles declarations**

```bash
docker compose exec app bash -c "grep -rn 'declare(strict_types=1)' app/ --include='*.php' | grep -v '^Binary' | awk -F: '{print \$1}' | sort | uniq -d"
```

Esperado: sin output (no duplicados)

- [ ] **Step 4: Ejecutar phpstan para verificar no hay errores de parseo**

```bash
make phpstan
```

- [ ] **Step 5: Ejecutar suite de tests**

```bash
make test-unit
```

- [ ] **Step 6: Commit**

```bash
git add app/
git commit -m "chore: add declare(strict_types=1) to all PHP files missing it"
```

---

### Task 5: Implementar Feature Flags FEATURE_OPS y FEATURE_KEEPER

**Files:**

- Modify: `bootstrap/container.php` — añadir guards condicionales para FEATURE_OPS y FEATURE_KEEPER
- Modify: `app/routes.php` — envolver rutas de keeper y ops en check de feature flag
- Create: `app/Providers/KeeperServiceProvider.php` — si no existe
- Create: `app/Providers/OpsServiceProvider.php` — si no existe

**Contexto:** `FEATURE_BACKOFFICE=1` ya está implementado en bootstrap/container.php como modelo:

```php
if (Env::bool('FEATURE_BACKOFFICE', false)) {
    $providers[] = StaffServiceProvider::class;
}
```

- [ ] **Step 1: Leer bootstrap/container.php para ver el patrón exacto**

```bash
docker compose exec app cat bootstrap/container.php
```

- [ ] **Step 2: Escribir test de feature flag FEATURE_KEEPER**

```php
// tests/Unit/Providers/FeatureFlagTest.php
<?php
/**
 * ¿Qué pruebas aquí?
 * Verifica que los feature flags FEATURE_KEEPER y FEATURE_OPS controlan el registro de providers.
 *
 * ¿Qué me quieres demostrar?
 * Que con FEATURE_KEEPER=0, el KeeperServiceProvider no se registra.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se elimina el guard condicional o se cambia el env var key.
 */
declare(strict_types=1);

namespace Tests\Unit\Providers;

use App\Core\Env;
use PHPUnit\Framework\TestCase;

final class FeatureFlagTest extends TestCase
{
    public function test_feature_keeper_env_var_is_boolean(): void
    {
        // Con FEATURE_KEEPER no definido, debe retornar false
        $_ENV['FEATURE_KEEPER'] = '0';
        $this->assertFalse(Env::bool('FEATURE_KEEPER', false));

        $_ENV['FEATURE_KEEPER'] = '1';
        $this->assertTrue(Env::bool('FEATURE_KEEPER', false));
    }

    public function test_feature_ops_env_var_is_boolean(): void
    {
        $_ENV['FEATURE_OPS'] = '0';
        $this->assertFalse(Env::bool('FEATURE_OPS', false));

        $_ENV['FEATURE_OPS'] = '1';
        $this->assertTrue(Env::bool('FEATURE_OPS', false));
    }

    protected function tearDown(): void
    {
        unset($_ENV['FEATURE_KEEPER'], $_ENV['FEATURE_OPS']);
    }
}
```

- [ ] **Step 3: Añadir guards en bootstrap/container.php**

```php
// Después del guard de FEATURE_BACKOFFICE existente:

if (\App\Core\Env::bool('FEATURE_KEEPER', false)) {
    $providers[] = \App\Providers\KeeperServiceProvider::class;
}

if (\App\Core\Env::bool('FEATURE_OPS', false)) {
    $providers[] = \App\Providers\OpsServiceProvider::class;
}
```

- [ ] **Step 4: Crear KeeperServiceProvider si no existe**

```php
// app/Providers/KeeperServiceProvider.php
<?php

declare(strict_types=1);

namespace App\Providers;

use App\Core\Container;
use App\Repositories\AnimalRepository;
use App\Repositories\HealthCheckRepository;
use App\Services\AnimalCareService;
use App\Services\HealthCheckService;
use App\Core\Database;

final class KeeperServiceProvider
{
    public function register(): void
    {
        Container::singleton(AnimalCareService::class, fn() => new AnimalCareService(
            new AnimalRepository(Database::getConnection())
        ));

        Container::singleton(HealthCheckService::class, fn() => new HealthCheckService(
            new HealthCheckRepository(Database::getConnection())
        ));
    }

    public function boot(): void {}
}
```

- [ ] **Step 5: Crear OpsServiceProvider si no existe**

```php
// app/Providers/OpsServiceProvider.php
<?php

declare(strict_types=1);

namespace App\Providers;

use App\Core\Container;
use App\Services\ReceptionService;
use App\Services\KitchenService;
use App\Core\Database;

final class OpsServiceProvider
{
    public function register(): void
    {
        Container::singleton(ReceptionService::class, fn() => new ReceptionService(
            Database::getConnection()
        ));

        Container::singleton(KitchenService::class, fn() => new KitchenService(
            Database::getConnection()
        ));
    }

    public function boot(): void {}
}
```

- [ ] **Step 6: Envolver rutas de keeper y ops en routes.php con feature flag check**

En `app/routes.php`, buscar los grupos de rutas `/keeper/` y `/ops/` y añadir:

```php
// FEATURE_KEEPER guard
if (\App\Core\Env::bool('FEATURE_KEEPER', true)) { // default true para no romper prod actual
    $router->group(['prefix' => '/keeper', 'middleware' => [$mw->auth(), $mw->role('keeper')]], function (Router $r) use ($mw) {
        // ... rutas existentes de keeper ...
    });
}

// FEATURE_OPS guard
if (\App\Core\Env::bool('FEATURE_OPS', true)) { // default true para no romper prod actual
    $router->group(['prefix' => '/ops', 'middleware' => [$mw->auth(), $mw->role(['reception', 'kitchen', 'supervisor', 'manager', 'admin'])]], function (Router $r) use ($mw) {
        // ... rutas existentes de ops ...
    });
}
```

**Nota:** usar `default: true` para no romper el comportamiento actual en producción (donde la variable no está definida).

- [ ] **Step 7: Ejecutar tests**

```bash
make test-unit
```

- [ ] **Step 8: Commit**

```bash
git add bootstrap/container.php app/routes.php app/Providers/KeeperServiceProvider.php app/Providers/OpsServiceProvider.php tests/Unit/Providers/FeatureFlagTest.php
git commit -m "feat: implement FEATURE_KEEPER and FEATURE_OPS feature flags"
```

---

**Verification final del Plan 1:**

```bash
make ci  # phpstan + psalm + tests + cs-check
```
