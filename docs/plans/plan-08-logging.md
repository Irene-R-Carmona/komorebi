# Logging Infrastructure — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Enriquecer el sistema de logging actual con trazabilidad por request_id, canales funcionales, contexto estructurado y cobertura completa de todos los flujos.

**Architecture:** LogContext como registry estático en memoria proporciona contexto por petición (request_id, method, path). LogContextProcessor enriquece cada log entry con ese contexto vía Monolog. RequestLogMiddleware PSR-15 genera el request_id al inicio y resetea el contexto al final.

**Tech Stack:** PHP 8.4, Monolog 3.10.0 (ya en vendor), PSR-15, PHPStan 5

---

## Contexto: bugs actuales a corregir

1. `Logger.php` L43 dev `LineFormatter`: `"[%datetime%] %level_name%: %message%\n"` — falta `%context% %extra%` → contexto siempre se pierde en local.
2. `ExceptionLogger::writeToLog()` escribe a `storage/logs/*.log` (viola 12-Factor) y luego llama `Logger::*` con un string pre-formateado concatenado (double write + formato roto). Eliminar el método entero.
3. `BaseService` solo tiene `logInfo()` y `logError()`. Faltan `logDebug()`, `logWarning()`, `logCritical()`.

---

## Archivos del plan

| Acción   | Archivo                                         | Responsabilidad                              |
| -------- | ----------------------------------------------- | -------------------------------------------- |
| Crear    | `app/Core/LogContext.php`                       | Registry estático de contexto de request     |
| Crear    | `app/Core/LogContextProcessor.php`              | Monolog ProcessorInterface → inyecta LogContext::all() en `extra` |
| Crear    | `app/Http/Middleware/RequestLogMiddleware.php`  | PSR-15: genera request_id, loguea request    |
| Crear    | `tools/LoggerContextRule.php`                   | PHPStan rule: error/critical sin $context    |
| Crear    | `tests/Unit/Core/LogContextTest.php`            | Tests de LogContext                          |
| Crear    | `tests/Unit/Core/LogContextProcessorTest.php`   | Tests de LogContextProcessor                 |
| Crear    | `tests/Unit/Http/Middleware/RequestLogMiddlewareTest.php` | Tests de RequestLogMiddleware       |
| Modificar | `app/Core/Logger.php`                          | Channels, JsonFormatter prod, LineFormatter dev con contexto, LogContextProcessor |
| Modificar | `app/Core/ExceptionLogger.php`                 | Eliminar writeToLog(), thin wrapper sobre Logger |
| Modificar | `app/Core/BaseService.php`                     | Añadir logDebug, logWarning, logCritical     |
| Modificar | `app/Core/MiddlewareFactory.php`               | Añadir requestLog()                          |
| Modificar | `public/index.php`                              | Insertar requestLog middleware en pipeline   |
| Modificar | `phpstan.neon`                                  | Registrar LoggerContextRule en services:     |
| Modificar | `.github/instructions/php-backend.instructions.md` | Ampliar sección Logging con taxonomía    |

---

## Task 1: Fix Logger.php dev format (Bug #1)

**Files:**
- Modify: `app/Core/Logger.php:43`

- [ ] **Step 1: Arreglar el formato dev**

Cambiar en `Logger.php` el bloque `else`:

```php
// ANTES (pierde contexto):
} else {
    $handler->setFormatter(new LineFormatter(
        "[%datetime%] %level_name%: %message%\n",
        'H:i:s'
    ));
}

// DESPUÉS (muestra contexto y extra en dev):
} else {
    $handler->setFormatter(new LineFormatter(
        "[%datetime%] %level_name%: %message% %context% %extra%\n",
        'H:i:s',
        true,
        true
    ));
}
```

- [ ] **Step 2: Verificar que PHPStan no rompa**

```bash
docker compose exec app vendor/bin/phpstan analyse app/Core/Logger.php --level=5
```

Esperado: 0 errors.

- [ ] **Step 3: Commit**

```bash
git add app/Core/Logger.php
git commit -m "fix: Logger dev format includes context and extra fields"
```

---

## Task 2: Crear LogContext.php + tests

**Files:**
- Create: `app/Core/LogContext.php`
- Create: `tests/Unit/Core/LogContextTest.php`

- [ ] **Step 1: Escribir el test primero**

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\LogContext;
use PHPUnit\Framework\TestCase;

/**
 * ¿Qué pruebas aquí?
 * Comportamiento del registry estático LogContext: set, get, all, reset.
 *
 * ¿Qué me quieres demostrar?
 * Que LogContext almacena pares clave-valor en memoria y reset() los borra.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Cualquier cambio a la mutabilidad del estado o nombres de métodos.
 */
final class LogContextTest extends TestCase
{
    protected function setUp(): void
    {
        LogContext::reset();
    }

    protected function tearDown(): void
    {
        LogContext::reset();
    }

    public function testSetAndGetValue(): void
    {
        LogContext::set('request_id', 'abc123');

        $this->assertSame('abc123', LogContext::get('request_id'));
    }

    public function testGetReturnsDefaultWhenKeyMissing(): void
    {
        $this->assertNull(LogContext::get('missing_key'));
        $this->assertSame('default', LogContext::get('missing_key', 'default'));
    }

    public function testAllReturnsAllEntries(): void
    {
        LogContext::set('request_id', 'abc123');
        LogContext::set('method', 'GET');

        $this->assertSame(['request_id' => 'abc123', 'method' => 'GET'], LogContext::all());
    }

    public function testResetClearsAllEntries(): void
    {
        LogContext::set('request_id', 'abc123');
        LogContext::reset();

        $this->assertSame([], LogContext::all());
    }

    public function testSetOverwritesExistingKey(): void
    {
        LogContext::set('request_id', 'first');
        LogContext::set('request_id', 'second');

        $this->assertSame('second', LogContext::get('request_id'));
    }
}
```

- [ ] **Step 2: Ejecutar test — debe fallar con class not found**

```bash
docker compose exec app vendor/bin/phpunit tests/Unit/Core/LogContextTest.php --colors=always
```

Esperado: FAIL — `Class "App\Core\LogContext" not found`.

- [ ] **Step 3: Crear LogContext.php**

```php
<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Registry estático de contexto de request para logging.
 *
 * Almacena pares clave-valor en memoria para la duración de una petición.
 * RequestLogMiddleware llama a reset() al inicio y al final de cada request.
 * LogContextProcessor inyecta LogContext::all() en el campo `extra` de Monolog.
 *
 * No usa Session: este contexto es de infraestructura, no de usuario.
 * Funciona en requests HTTP, jobs de queue, workers CLI y health checks.
 */
final class LogContext
{
    /** @var array<string, mixed> */
    private static array $context = [];

    public static function set(string $key, mixed $value): void
    {
        self::$context[$key] = $value;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::$context[$key] ?? $default;
    }

    /** @return array<string, mixed> */
    public static function all(): array
    {
        return self::$context;
    }

    public static function reset(): void
    {
        self::$context = [];
    }
}
```

- [ ] **Step 4: Ejecutar test — debe pasar**

```bash
docker compose exec app vendor/bin/phpunit tests/Unit/Core/LogContextTest.php --colors=always
```

Esperado: 5 tests, 0 failures.

- [ ] **Step 5: Commit**

```bash
git add app/Core/LogContext.php tests/Unit/Core/LogContextTest.php
git commit -m "feat: add LogContext static registry for per-request logging context"
```

---

## Task 3: Crear LogContextProcessor.php + tests

**Files:**
- Create: `app/Core/LogContextProcessor.php`
- Create: `tests/Unit/Core/LogContextProcessorTest.php`

- [ ] **Step 1: Escribir el test primero**

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\LogContext;
use App\Core\LogContextProcessor;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

/**
 * ¿Qué pruebas aquí?
 * Que LogContextProcessor inyecta LogContext::all() en el campo extra del record.
 *
 * ¿Qué me quieres demostrar?
 * Que el processor enriquece el log record sin modificar otros campos.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si el processor deja de usar LogContext::all() o escribe en el lugar equivocado.
 */
final class LogContextProcessorTest extends TestCase
{
    protected function setUp(): void
    {
        LogContext::reset();
    }

    protected function tearDown(): void
    {
        LogContext::reset();
    }

    private function makeRecord(array $extra = []): LogRecord
    {
        return new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'test message',
            context: [],
            extra: $extra,
        );
    }

    public function testInjectsLogContextIntoExtra(): void
    {
        LogContext::set('request_id', 'abc123');
        LogContext::set('method', 'GET');

        $processor = new LogContextProcessor();
        $result = $processor($this->makeRecord());

        $this->assertSame('abc123', $result->extra['request_id']);
        $this->assertSame('GET', $result->extra['method']);
    }

    public function testDoesNotOverwriteExistingExtraKeys(): void
    {
        LogContext::set('request_id', 'from_context');

        $processor = new LogContextProcessor();
        $result = $processor($this->makeRecord(['existing' => 'value']));

        $this->assertSame('value', $result->extra['existing']);
        $this->assertSame('from_context', $result->extra['request_id']);
    }

    public function testEmptyContextProducesNoExtra(): void
    {
        $processor = new LogContextProcessor();
        $result = $processor($this->makeRecord());

        $this->assertSame([], $result->extra);
    }
}
```

- [ ] **Step 2: Ejecutar test — debe fallar**

```bash
docker compose exec app vendor/bin/phpunit tests/Unit/Core/LogContextProcessorTest.php --colors=always
```

Esperado: FAIL — `Class "App\Core\LogContextProcessor" not found`.

- [ ] **Step 3: Crear LogContextProcessor.php**

```php
<?php

declare(strict_types=1);

namespace App\Core;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Monolog processor que inyecta LogContext::all() en el campo extra de cada log record.
 *
 * Registrado en Logger::get() y Logger::channel() para que todos los logs lleven
 * automáticamente el request_id, method y path del request en curso.
 */
final class LogContextProcessor implements ProcessorInterface
{
    #[\Override]
    public function __invoke(LogRecord $record): LogRecord
    {
        $extra = LogContext::all();

        if ($extra === []) {
            return $record;
        }

        return $record->with(extra: array_merge($record->extra, $extra));
    }
}
```

- [ ] **Step 4: Ejecutar test — debe pasar**

```bash
docker compose exec app vendor/bin/phpunit tests/Unit/Core/LogContextProcessorTest.php --colors=always
```

Esperado: 3 tests, 0 failures.

- [ ] **Step 5: Commit**

```bash
git add app/Core/LogContextProcessor.php tests/Unit/Core/LogContextProcessorTest.php
git commit -m "feat: add LogContextProcessor to enrich Monolog records with request context"
```

---

## Task 4: Refactorizar Logger.php con canales + JsonFormatter + processor

**Files:**
- Modify: `app/Core/Logger.php`

Cambios:
- Añadir `use` de `JsonFormatter`, `LogContextProcessor`, `UidProcessor`
- Reemplazar `$instance` singleton por `$channels` array
- Añadir `channel(string $name): LoggerInterface`
- Usar `JsonFormatter` en prod, `LineFormatter` con `%context% %extra%` en dev
- Registrar `LogContextProcessor` en todos los canales

- [ ] **Step 1: Reemplazar el contenido completo de Logger.php**

```php
<?php

declare(strict_types=1);

namespace App\Core;

use Monolog\Formatter\JsonFormatter;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger as Monolog;
use Psr\Log\LoggerInterface;

/**
 * Logger 12-Factor: streams a stdout, nunca archivos en contenedores.
 *
 * Canales disponibles: app (default), http, db, queue, auth
 * En desarrollo: LineFormatter legible con contexto visible
 * En producción: JsonFormatter estructurado para ingesta por ELK/Loki
 *
 * Todos los logs llevan automáticamente el contexto del request (request_id,
 * method, path) inyectado por LogContextProcessor.
 */
final class Logger
{
    /** @var array<string, LoggerInterface> */
    private static array $channels = [];

    private static bool $isProd = false;
    private static string $level = 'info';

    public static function get(): LoggerInterface
    {
        return self::channel('app');
    }

    public static function channel(string $name = 'app'): LoggerInterface
    {
        if (isset(self::$channels[$name])) {
            return self::$channels[$name];
        }

        $level = Config::getString('logging.level', 'info');
        $isProd = Config::getString('app.env', 'production') === 'production';

        $log = new Monolog($name);

        $handler = new StreamHandler('php://stdout', self::parseLevel($level));

        if ($isProd) {
            $formatter = new JsonFormatter();
        } else {
            $formatter = new LineFormatter(
                "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
                'H:i:s',
                true,
                true
            );
        }

        $handler->setFormatter($formatter);
        $log->pushHandler($handler);
        $log->pushProcessor(new LogContextProcessor());

        self::$channels[$name] = $log;

        return $log;
    }

    /**
     * Borra los canales cacheados. Útil en tests para aislar configuración.
     */
    public static function reset(): void
    {
        self::$channels = [];
    }

    // Proxies estáticos (canal 'app' por defecto)

    public static function emergency(string $msg, array $ctx = []): void
    {
        self::get()->emergency($msg, $ctx);
    }

    public static function alert(string $msg, array $ctx = []): void
    {
        self::get()->alert($msg, $ctx);
    }

    public static function critical(string $msg, array $ctx = []): void
    {
        self::get()->critical($msg, $ctx);
    }

    public static function error(string $msg, array $ctx = []): void
    {
        self::get()->error($msg, $ctx);
    }

    public static function warning(string $msg, array $ctx = []): void
    {
        self::get()->warning($msg, $ctx);
    }

    public static function notice(string $msg, array $ctx = []): void
    {
        self::get()->notice($msg, $ctx);
    }

    public static function info(string $msg, array $ctx = []): void
    {
        self::get()->info($msg, $ctx);
    }

    public static function debug(string $msg, array $ctx = []): void
    {
        self::get()->debug($msg, $ctx);
    }

    private static function parseLevel(string $level): Level
    {
        $name = strtoupper($level);
        try {
            return Level::fromName($name);
        } catch (\TypeError | \ValueError $e) {
            return Level::Info;
        }
    }
}
```

- [ ] **Step 2: Verificar PHPStan**

```bash
docker compose exec app vendor/bin/phpstan analyse app/Core/Logger.php --level=5
```

Esperado: 0 errors.

- [ ] **Step 3: Ejecutar tests de unit existentes**

```bash
docker compose exec app vendor/bin/phpunit tests/Unit/ --colors=always
```

Esperado: mismo número de tests que antes, 0 failures.

- [ ] **Step 4: Commit**

```bash
git add app/Core/Logger.php
git commit -m "feat: Logger with channels, JsonFormatter prod, LogContextProcessor"
```

---

## Task 5: Crear RequestLogMiddleware + tests

**Files:**
- Create: `app/Http/Middleware/RequestLogMiddleware.php`
- Create: `tests/Unit/Http/Middleware/RequestLogMiddlewareTest.php`

- [ ] **Step 1: Escribir el test primero**

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Middleware;

use App\Core\LogContext;
use App\Http\Middleware\RequestLogMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * ¿Qué pruebas aquí?
 * Que RequestLogMiddleware genera un request_id, popula LogContext y lo resetea al final.
 *
 * ¿Qué me quieres demostrar?
 * Que cada request tiene un request_id único y que LogContext queda limpio al terminar.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si el middleware deja de generar request_id, de usar LogContext o de llamar reset().
 */
final class RequestLogMiddlewareTest extends TestCase
{
    protected function tearDown(): void
    {
        LogContext::reset();
    }

    private function makeRequest(string $method = 'GET', string $path = '/test'): ServerRequestInterface
    {
        $uri = $this->createStub(UriInterface::class);
        $uri->method('getPath')->willReturn($path);

        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn($method);
        $request->method('getUri')->willReturn($uri);

        return $request;
    }

    private function makeHandler(?callable $onHandle = null): RequestHandlerInterface
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);

        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturnCallback(function () use ($response, $onHandle) {
            if ($onHandle !== null) {
                ($onHandle)();
            }
            return $response;
        });

        return $handler;
    }

    public function testSetsRequestIdInLogContext(): void
    {
        $capturedRequestId = null;

        $handler = $this->makeHandler(function () use (&$capturedRequestId) {
            $capturedRequestId = LogContext::get('request_id');
        });

        $middleware = new RequestLogMiddleware();
        $middleware->process($this->makeRequest(), $handler);

        $this->assertNotNull($capturedRequestId);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{16}$/', (string) $capturedRequestId);
    }

    public function testSetsMethodAndPathInLogContext(): void
    {
        $capturedMethod = null;
        $capturedPath = null;

        $handler = $this->makeHandler(function () use (&$capturedMethod, &$capturedPath) {
            $capturedMethod = LogContext::get('method');
            $capturedPath = LogContext::get('path');
        });

        $middleware = new RequestLogMiddleware();
        $middleware->process($this->makeRequest('POST', '/api/users'), $handler);

        $this->assertSame('POST', $capturedMethod);
        $this->assertSame('/api/users', $capturedPath);
    }

    public function testResetsLogContextAfterResponse(): void
    {
        $middleware = new RequestLogMiddleware();
        $middleware->process($this->makeRequest(), $this->makeHandler());

        $this->assertSame([], LogContext::all());
    }

    public function testReturnsHandlerResponse(): void
    {
        $middleware = new RequestLogMiddleware();
        $response = $middleware->process($this->makeRequest(), $this->makeHandler());

        $this->assertSame(200, $response->getStatusCode());
    }
}
```

- [ ] **Step 2: Ejecutar test — debe fallar**

```bash
docker compose exec app vendor/bin/phpunit tests/Unit/Http/Middleware/RequestLogMiddlewareTest.php --colors=always
```

Esperado: FAIL — class not found.

- [ ] **Step 3: Crear RequestLogMiddleware.php**

```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\LogContext;
use App\Core\Logger;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Middleware PSR-15 de logging de requests.
 *
 * Por cada request:
 * 1. Resetea LogContext (limpia contexto de request anterior)
 * 2. Genera un request_id único (16 hex chars = 64 bits de entropía)
 * 3. Popula LogContext con method, path y request_id
 * 4. Procesa el request y mide duración
 * 5. Loguea el resultado (method, path, status, duration_ms)
 * 6. Resetea LogContext para liberar memoria
 *
 * Debe colocarse después de SecurityHeadersMiddleware y antes de errorHandler
 * para que el request_id esté disponible durante el manejo de errores.
 */
final class RequestLogMiddleware implements MiddlewareInterface
{
    #[\Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        LogContext::reset();

        $requestId = bin2hex(random_bytes(8));
        LogContext::set('request_id', $requestId);
        LogContext::set('method', $request->getMethod());
        LogContext::set('path', $request->getUri()->getPath());

        $start = hrtime(true);

        $response = $handler->handle($request);

        $durationMs = (int) ((hrtime(true) - $start) / 1_000_000);

        Logger::channel('http')->info(
            $request->getMethod() . ' ' . $request->getUri()->getPath(),
            [
                'status'      => $response->getStatusCode(),
                'duration_ms' => $durationMs,
            ]
        );

        LogContext::reset();

        return $response;
    }
}
```

- [ ] **Step 4: Ejecutar test — debe pasar**

```bash
docker compose exec app vendor/bin/phpunit tests/Unit/Http/Middleware/RequestLogMiddlewareTest.php --colors=always
```

Esperado: 4 tests, 0 failures.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Middleware/RequestLogMiddleware.php tests/Unit/Http/Middleware/RequestLogMiddlewareTest.php
git commit -m "feat: RequestLogMiddleware generates request_id and logs HTTP requests"
```

---

## Task 6: Añadir requestLog() a MiddlewareFactory + wiring en index.php

**Files:**
- Modify: `app/Core/MiddlewareFactory.php`
- Modify: `public/index.php`

- [ ] **Step 1: Añadir requestLog() a MiddlewareFactory**

Añadir el `use` y el método en `MiddlewareFactory.php`:

```php
// Añadir al bloque de use statements:
use App\Http\Middleware\RequestLogMiddleware;

// Añadir método antes de errorHandler():
/**
 * Middleware de logging de requests.
 * Genera request_id, popula LogContext y loguea method/path/status/duration.
 * Colocar después de SecurityHeaders y antes de errorHandler.
 */
public function requestLog(): RequestLogMiddleware
{
    return new RequestLogMiddleware();
}
```

- [ ] **Step 2: Insertar en el pipeline de index.php**

En `public/index.php`, después de `SecurityHeadersMiddleware` y antes de `errorHandler`:

```php
// ANTES:
// 1. Security headers — siempre en todas las respuestas
$pipeline->pipe(new \App\Middleware\SecurityHeadersMiddleware());
// 2. Error handler — captura excepciones del pipeline y retorna respuestas PSR-7
$mwFactory = new MiddlewareFactory(new ResponseFactory());
$pipeline->pipe($mwFactory->errorHandler());

// DESPUÉS:
// 1. Security headers — siempre en todas las respuestas
$pipeline->pipe(new \App\Middleware\SecurityHeadersMiddleware());
// 2. Request logging — genera request_id, loguea method/path/status/duration
$mwFactory = new MiddlewareFactory(new ResponseFactory());
$pipeline->pipe($mwFactory->requestLog());
// 3. Error handler — captura excepciones del pipeline y retorna respuestas PSR-7
$pipeline->pipe($mwFactory->errorHandler());
```

- [ ] **Step 3: Verificar PHPStan**

```bash
docker compose exec app vendor/bin/phpstan analyse app/Core/MiddlewareFactory.php public/index.php --level=5
```

Esperado: 0 errors.

- [ ] **Step 4: Commit**

```bash
git add app/Core/MiddlewareFactory.php public/index.php
git commit -m "feat: wire RequestLogMiddleware into global PSR-15 pipeline"
```

---

## Task 7: Refactorizar ExceptionLogger — eliminar writeToLog() (Bug #2)

**Files:**
- Modify: `app/Core/ExceptionLogger.php`

Cambios:
- Eliminar constante `LOG_FORMAT` (solo usada en `writeToLog()`)
- Eliminar método `writeToLog()` completo (~50 líneas)
- Reemplazar la llamada a `writeToLog()` en `log()` por llamadas directas a `Logger::channel('app')`
- Mantener: `SEVERITY_MAP`, `getSeverity()`, `extractContext()`, `extractExceptionData()`, `extractSessionData()`, `extractRequestData()`, `anonymizeIp()`, `notifyCriticalError()`, `generateErrorId()`, `recordMetrics()`, `getDailyStats()`

- [ ] **Step 1: Refactorizar el método log()**

Reemplazar el método `log()` en `ExceptionLogger.php`:

```php
public static function log(Throwable $exception, ?string $context = null): void
{
    $severity = self::getSeverity($exception);
    $contextData = array_merge(
        self::extractContext($exception),
        [
            'context'         => $context ?? 'SYSTEM',
            'exception_class' => \get_class($exception),
            'file'            => \basename($exception->getFile()),
            'line'            => $exception->getLine(),
        ]
    );

    $channel = Logger::channel('app');

    match ($severity) {
        'CRITICAL' => $channel->critical($exception->getMessage(), $contextData),
        'ERROR'    => $channel->error($exception->getMessage(), $contextData),
        'WARNING'  => $channel->warning($exception->getMessage(), $contextData),
        default    => $channel->info($exception->getMessage(), $contextData),
    };

    if ($severity === 'CRITICAL') {
        self::notifyCriticalError($exception, $context);
    }
}
```

- [ ] **Step 2: Eliminar LOG_FORMAT y writeToLog()**

Eliminar la constante:
```php
// Eliminar esta línea:
private const string LOG_FORMAT = '[%s] %s | %s: %s | Context: %s | Trace: %s:%d';
```

Eliminar el método `writeToLog()` completo (toda la función desde `private static function writeToLog(` hasta su `}`).

- [ ] **Step 3: Verificar PHPStan**

```bash
docker compose exec app vendor/bin/phpstan analyse app/Core/ExceptionLogger.php --level=5
```

Esperado: 0 errors.

- [ ] **Step 4: Ejecutar tests existentes**

```bash
docker compose exec app vendor/bin/phpunit tests/Unit/ --colors=always
```

Esperado: 0 failures.

- [ ] **Step 5: Commit**

```bash
git add app/Core/ExceptionLogger.php
git commit -m "fix: ExceptionLogger removes file writes, uses Logger::channel('app') directly"
```

---

## Task 8: Completar BaseService (Bug #3)

**Files:**
- Modify: `app/Core/BaseService.php`

- [ ] **Step 1: Añadir los tres métodos faltantes**

```php
// Añadir después de logError():

protected function logDebug(string $message, array $context = []): void
{
    Logger::debug('[' . static::class . '] ' . $message, $context);
}

protected function logWarning(string $message, array $context = []): void
{
    Logger::warning('[' . static::class . '] ' . $message, $context);
}

protected function logCritical(string $message, array $context = []): void
{
    Logger::critical('[' . static::class . '] ' . $message, $context);
}
```

- [ ] **Step 2: Verificar PHPStan**

```bash
docker compose exec app vendor/bin/phpstan analyse app/Core/BaseService.php --level=5
```

Esperado: 0 errors.

- [ ] **Step 3: Commit**

```bash
git add app/Core/BaseService.php
git commit -m "feat: BaseService adds logDebug, logWarning, logCritical helpers"
```

---

## Task 9: PHPStan rule — detectar error/critical sin contexto

**Files:**
- Create: `tools/LoggerContextRule.php`
- Modify: `phpstan.neon`

- [ ] **Step 1: Crear la rule**

```php
<?php

declare(strict_types=1);

namespace App\Tools\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * PHPStan rule: Logger::error/critical/alert/emergency requieren $context array.
 *
 * Estos niveles describen condiciones anómalas. Sin contexto estructurado,
 * son imposibles de diagnosticar. Esta rule obliga a siempre pasar $context.
 *
 * @implements Rule<StaticCall>
 */
final class LoggerContextRule implements Rule
{
    private const array REQUIRED_CONTEXT_METHODS = ['error', 'critical', 'alert', 'emergency'];

    #[\Override]
    public function getNodeType(): string
    {
        return StaticCall::class;
    }

    /**
     * @param StaticCall $node
     * @return list<\PHPStan\Rules\RuleError>
     */
    #[\Override]
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->class instanceof Node\Name) {
            return [];
        }

        $className = $node->class->toString();

        if ($className !== 'App\\Core\\Logger' && $className !== 'Logger') {
            return [];
        }

        if (!$node->name instanceof Node\Identifier) {
            return [];
        }

        $methodName = $node->name->toString();

        if (!\in_array($methodName, self::REQUIRED_CONTEXT_METHODS, true)) {
            return [];
        }

        // Verificar que se pasa al menos 2 argumentos (message + context)
        if (\count($node->args) >= 2) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                \sprintf(
                    'Logger::%s() requires a $context array as second argument. ' .
                    'Error-level logs without context are undiagnosable.',
                    $methodName
                )
            )->build(),
        ];
    }
}
```

- [ ] **Step 2: Registrar en phpstan.neon**

Añadir al final de `phpstan.neon`:

```yaml
services:
    -
        class: App\Tools\PHPStan\LoggerContextRule
        tags:
            - phpstan.rules.rule
```

- [ ] **Step 3: Ejecutar PHPStan para verificar que la rule carga**

```bash
docker compose exec app vendor/bin/phpstan analyse app/Core/Logger.php --level=5
```

Esperado: 0 errors (Logger.php no llama error/critical sin contexto).

- [ ] **Step 4: Commit**

```bash
git add tools/LoggerContextRule.php phpstan.neon
git commit -m "feat: PHPStan rule requires context array for error/critical/alert/emergency"
```

---

## Task 10: Actualizar php-backend.instructions.md

**Files:**
- Modify: `.github/instructions/php-backend.instructions.md`

- [ ] **Step 1: Ampliar la sección Logging con taxonomía de niveles y canales**

Localizar la sección `## Logging` en `.github/instructions/php-backend.instructions.md` y reemplazarla con:

```markdown
## Logging

Always use the static proxy. Include `[ClassName]` prefix in every message.

```php
Logger::info('[UserService] Profile updated', ['user_id' => $id]);
Logger::error('[ReservationController] Save failed', ['reason' => $result->getMessage()]);
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
Logger::channel('app')   // business logic (default — same as Logger::info/error/...)
Logger::channel('http')  // HTTP requests and API operations
Logger::channel('db')    // slow queries, DB errors
Logger::channel('queue') // job lifecycle (start, complete, fail)
Logger::channel('auth')  // login, logout, token events
```

### Rules

- Services: use `$this->logInfo()`, `$this->logWarning()`, `$this->logError()` from BaseService.
- Read-only methods with no side effects: do NOT log.
- Never log passwords, tokens, or full email addresses. Use `[REDACTED]` or `hash('sha256', $value)`.
- `Logger::error()` and above **require** a `$context` array (enforced by PHPStan rule).
- Every request carries a `request_id` in `LogContext` — automatically added to all logs via `LogContextProcessor`.
```

- [ ] **Step 2: Commit**

```bash
git add .github/instructions/php-backend.instructions.md
git commit -m "docs: expand Logging section with level taxonomy, channels and rules"
```

---

## Verificación final de infraestructura

- [ ] Ejecutar suite completa de unit tests:

```bash
docker compose exec app vendor/bin/phpunit tests/Unit/ --colors=always
```

Esperado: incluye LogContextTest (5), LogContextProcessorTest (3), RequestLogMiddlewareTest (4) nuevos. 0 failures.

- [ ] Ejecutar PHPStan:

```bash
docker compose exec app vendor/bin/phpstan analyse --level=5
```

Esperado: igual o menos errores que la baseline.

- [ ] Smoke test en dev (opcional — requiere stack corriendo):

```bash
docker compose up -d
curl -s http://localhost:8080/health
# Ver en logs: request_id, method, path, status, duration_ms en cada entrada
docker compose logs app --tail=5
```

---

## Siguiente fase: Cobertura de flujos (plan-08b)

Una vez completada esta infraestructura, el plan de cobertura (`plan-08b-logging-coverage.md`) instrumentará:
- 24+ Services (vía `$this->logInfo/Warning/Error`)
- 49 web controllers (vía `Logger::` directo)
- 8-9 API controllers (vía `Logger::channel('http')`)
- 4 Jobs/Listeners con gaps (RewardUnlockedJob + 3 Telegram Listeners)
