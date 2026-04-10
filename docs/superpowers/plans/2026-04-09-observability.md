# Observabilidad — Request Logging + Business Events

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Añadir tres capas de observabilidad al proyecto sin romper funcionalidad existente: (A) un middleware que loguea cada request HTTP con método, ruta, status, duración, user_id y request_id; (B) arreglos menores al logger de desarrollo y eliminación de debug logs temporales; (C) tres nuevos eventos de negocio con sus listeners que cubren login, logout y cancelación de reserva.

**Architecture:** Las tres fases son completamente independientes. Fase A (PSR-15 middleware) se inserta en el pipeline existente entre `SecurityHeadersMiddleware` y `ErrorHandlerMiddleware`. Fase B son cambios de una línea. Fase C sigue el patrón ya establecido de `ReservationConfirmedEvent` + `LogReservationConfirmedListener` + registro en `EventServiceProvider`. Todos los tests siguen el patrón TDD del proyecto (docblock obligatorio, `createStub` no `createMock`).

**Tech Stack:** PHP 8.4, PSR-15, Monolog (via `Logger` proxy estático), Symfony EventDispatcher (PSR-14), `Session::` proxy estático, PHPUnit 11.

---

## File Map

### Fase A — RequestLogMiddleware

| Fichero | Acción |
|---|---|
| `app/Http/Middleware/RequestLogMiddleware.php` | CREAR — middleware PSR-15 |
| `app/Core/MiddlewareFactory.php` | MODIFICAR — añadir método `requestLog()` |
| `public/index.php` | MODIFICAR — piping en pipeline |
| `tests/Unit/Http/Middleware/RequestLogMiddlewareTest.php` | CREAR — tests TDD |

### Fase B — Logger fix + limpieza debug logs

| Fichero | Acción |
|---|---|
| `app/Core/Logger.php` | MODIFICAR — añadir `%context%` al formato dev (1 línea) |
| `app/Services/AuthService.php` | MODIFICAR — eliminar ~5 bloques `APP_ENV === 'local'` debug |

### Fase C — Eventos de negocio

| Fichero | Acción |
|---|---|
| `app/Events/UserLoggedInEvent.php` | CREAR |
| `app/Events/UserLoggedOutEvent.php` | CREAR |
| `app/Events/ReservationCancelledEvent.php` | CREAR |
| `app/Listeners/LogUserLoggedInListener.php` | CREAR |
| `app/Listeners/LogUserLoggedOutListener.php` | CREAR |
| `app/Listeners/LogReservationCancelledListener.php` | CREAR |
| `app/Providers/EventServiceProvider.php` | MODIFICAR — registrar 3 listeners |
| `app/Services/AuthService.php` | MODIFICAR — dispatch en `performSuccessfulLogin()` y `logout()` |
| `app/Services/ReservationService.php` | MODIFICAR — dispatch en `cancel()` |
| `tests/Unit/Listeners/LogUserLoggedInListenerTest.php` | CREAR |
| `tests/Unit/Listeners/LogUserLoggedOutListenerTest.php` | CREAR |
| `tests/Unit/Listeners/LogReservationCancelledListenerTest.php` | CREAR |

---

## Tasks

### Fase A — RequestLogMiddleware

- [ ] **A1 — Test:** Crear `tests/Unit/Http/Middleware/RequestLogMiddlewareTest.php`
  - [ ] Docblock obligatorio: qué prueba, qué demuestra, qué falla si se cambia el código
  - [ ] Test `testAddRequestIdHeaderToResponse()`: stub de `RequestHandlerInterface` que devuelve response 200, llamar `process()`, verificar que el response contiene `X-Request-ID` con un string de 8 chars hex
  - [ ] Test `testLogsRequestAndResponse()`: igual que arriba, verificar que `Logger::info` fue llamado dos veces (se puede hacer con un spy o simplemente verificar que no lanza excepción — ver nota de implementación)

  > **Nota de implementación:** `Logger::` es una fachada estática. Para testear las llamadas al logger, usar output buffering o verificar el efecto observable (el header). Si el logger estático no es mockeable, el test de logueo verifica efectos indirectos (header presente, no exceptions). Priorizar el test del header y el test de no-excepción.

- [ ] **A2 — Implementación:** Crear `app/Http/Middleware/RequestLogMiddleware.php`

  ```php
  <?php
  declare(strict_types=1);
  namespace App\Http\Middleware;

  use App\Core\Logger;
  use App\Core\Session;
  use Psr\Http\Message\ResponseInterface;
  use Psr\Http\Message\ServerRequestInterface;
  use Psr\Http\Server\MiddlewareInterface;
  use Psr\Http\Server\RequestHandlerInterface;

  final class RequestLogMiddleware implements MiddlewareInterface
  {
      public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
      {
          $requestId = substr(bin2hex(random_bytes(4)), 0, 8);
          $method    = $request->getMethod();
          $path      = $request->getUri()->getPath();
          $userId    = Session::get('user_id') ?? 0;
          $start     = hrtime(true);

          Logger::info('[HTTP] → ' . $method . ' ' . $path, [
              'req'  => $requestId,
              'user' => $userId,
          ]);

          $response = $handler->handle($request);

          $ms     = (int) round((hrtime(true) - $start) / 1_000_000);
          $status = $response->getStatusCode();

          Logger::info('[HTTP] ← ' . $method . ' ' . $path . ' → ' . $status, [
              'req'  => $requestId,
              'user' => $userId,
              'ms'   => $ms,
          ]);

          return $response->withHeader('X-Request-ID', $requestId);
      }
  }
  ```

- [ ] **A3 — MiddlewareFactory:** Añadir en `app/Core/MiddlewareFactory.php` después del método `securityHeaders()`

  ```php
  use App\Http\Middleware\RequestLogMiddleware;
  // ...
  public function requestLog(): RequestLogMiddleware
  {
      return new RequestLogMiddleware();
  }
  ```

- [ ] **A4 — Pipeline:** En `public/index.php` añadir después de la línea `$pipeline->pipe(new \App\Middleware\SecurityHeadersMiddleware());`

  ```php
  $pipeline->pipe($mwFactory->requestLog());
  ```

---

### Fase B — Logger + limpieza de debug logs

- [ ] **B1 — Logger dev format:** En `app/Core/Logger.php` línea 45, cambiar el formato del `LineFormatter` de desarrollo:
  - De: `"[%datetime%] %level_name%: %message%\n"`
  - A:  `"[%datetime%] %level_name%: %message% %context%\n"`

- [ ] **B2 — Eliminar debug logs temporales:** En `app/Services/AuthService.php`, eliminar todos los bloques de la forma:

  ```php
  if (Env::get('APP_ENV') === 'local') {
      Logger::error('[AuthService::*] ...');
  }
  ```

  Hay ~5 bloques en los métodos `createSession()` y `performSuccessfulLogin()`. No sustituir por nada — simplemente eliminar.

---

### Fase C — Eventos de negocio

- [ ] **C1 — Evento UserLoggedInEvent:** Crear `app/Events/UserLoggedInEvent.php`

  ```php
  <?php
  declare(strict_types=1);
  namespace App\Events;

  use DateTimeImmutable;

  final readonly class UserLoggedInEvent
  {
      public function __construct(
          public int $userId,
          public string $email,
          public string $ipAddress,
          public DateTimeImmutable $occurredAt,
      ) {}
  }
  ```

- [ ] **C2 — Evento UserLoggedOutEvent:** Crear `app/Events/UserLoggedOutEvent.php`

  ```php
  <?php
  declare(strict_types=1);
  namespace App\Events;

  use DateTimeImmutable;

  final readonly class UserLoggedOutEvent
  {
      public function __construct(
          public int $userId,
          public DateTimeImmutable $occurredAt,
      ) {}
  }
  ```

- [ ] **C3 — Evento ReservationCancelledEvent:** Crear `app/Events/ReservationCancelledEvent.php`

  ```php
  <?php
  declare(strict_types=1);
  namespace App\Events;

  use DateTimeImmutable;

  final readonly class ReservationCancelledEvent
  {
      public function __construct(
          public int $reservationId,
          public int $userId,
          public DateTimeImmutable $occurredAt,
      ) {}
  }
  ```

- [ ] **C4 — Test LogUserLoggedInListener:** Crear `tests/Unit/Listeners/LogUserLoggedInListenerTest.php`
  - Docblock obligatorio: qué prueba, qué demuestra, qué falla
  - Test: instanciar listener, crear `UserLoggedInEvent` con datos de prueba, llamar `__invoke($event)`, verificar que no lanza excepciones
  - Patrón idéntico a `tests/Unit/Listeners/LogUserRegisteredListenerTest.php` (si existe) o `LogReservationConfirmedListenerTest.php`

- [ ] **C5 — Test LogUserLoggedOutListener:** Crear `tests/Unit/Listeners/LogUserLoggedOutListenerTest.php`
  - Igual que C4 con `UserLoggedOutEvent`

- [ ] **C6 — Test LogReservationCancelledListener:** Crear `tests/Unit/Listeners/LogReservationCancelledListenerTest.php`
  - Igual que C4 con `ReservationCancelledEvent`

- [ ] **C7 — Listener LogUserLoggedInListener:** Crear `app/Listeners/LogUserLoggedInListener.php`

  ```php
  <?php
  declare(strict_types=1);
  namespace App\Listeners;

  use App\Core\Logger;
  use App\Events\UserLoggedInEvent;
  use Throwable;

  final class LogUserLoggedInListener
  {
      public function __invoke(UserLoggedInEvent $event): void
      {
          try {
              Logger::info('[Auth] Usuario autenticado', [
                  'user_id' => $event->userId,
                  'email'   => $event->email,
                  'ip'      => $event->ipAddress,
              ]);
          } catch (Throwable $e) {
              Logger::error('[LogUserLoggedInListener] Error: ' . $e->getMessage(), [
                  'user_id' => $event->userId,
              ]);
          }
      }
  }
  ```

- [ ] **C8 — Listener LogUserLoggedOutListener:** Crear `app/Listeners/LogUserLoggedOutListener.php`

  ```php
  <?php
  declare(strict_types=1);
  namespace App\Listeners;

  use App\Core\Logger;
  use App\Events\UserLoggedOutEvent;
  use Throwable;

  final class LogUserLoggedOutListener
  {
      public function __invoke(UserLoggedOutEvent $event): void
      {
          try {
              Logger::info('[Auth] Usuario desconectado', [
                  'user_id' => $event->userId,
              ]);
          } catch (Throwable $e) {
              Logger::error('[LogUserLoggedOutListener] Error: ' . $e->getMessage(), [
                  'user_id' => $event->userId,
              ]);
          }
      }
  }
  ```

- [ ] **C9 — Listener LogReservationCancelledListener:** Crear `app/Listeners/LogReservationCancelledListener.php`

  ```php
  <?php
  declare(strict_types=1);
  namespace App\Listeners;

  use App\Core\Logger;
  use App\Events\ReservationCancelledEvent;
  use Throwable;

  final class LogReservationCancelledListener
  {
      public function __invoke(ReservationCancelledEvent $event): void
      {
          try {
              Logger::info('[Reservation] Reserva cancelada', [
                  'reservation_id' => $event->reservationId,
                  'user_id'        => $event->userId,
              ]);
          } catch (Throwable $e) {
              Logger::error('[LogReservationCancelledListener] Error: ' . $e->getMessage(), [
                  'reservation_id' => $event->reservationId,
              ]);
          }
      }
  }
  ```

- [ ] **C10 — Registrar listeners en EventServiceProvider:** En `app/Providers/EventServiceProvider.php`, dentro de `boot()`, añadir:

  ```php
  use App\Events\UserLoggedInEvent;
  use App\Events\UserLoggedOutEvent;
  use App\Events\ReservationCancelledEvent;
  use App\Listeners\LogUserLoggedInListener;
  use App\Listeners\LogUserLoggedOutListener;
  use App\Listeners\LogReservationCancelledListener;
  // ...
  $dispatcher->addListener(UserLoggedInEvent::class, new LogUserLoggedInListener());
  $dispatcher->addListener(UserLoggedOutEvent::class, new LogUserLoggedOutListener());
  $dispatcher->addListener(ReservationCancelledEvent::class, new LogReservationCancelledListener());
  ```

- [ ] **C11 — Dispatch en AuthService::performSuccessfulLogin():** En `app/Services/AuthService.php`, tras `$this->createSession($user);`, añadir:

  ```php
  if ($this->eventDispatcher !== null) {
      $this->eventDispatcher->dispatch(
          new UserLoggedInEvent($userId, $email, $ipAddress, new DateTimeImmutable())
      );
  }
  ```

- [ ] **C12 — Dispatch en AuthService::logout():** En `app/Services/AuthService.php`, antes de `Session::destroy();`, capturar user_id y luego, tras `Session::destroy()` y `Csrf::regenerate()`, añadir:

  ```php
  if ($this->eventDispatcher !== null && $userId !== null) {
      $this->eventDispatcher->dispatch(
          new UserLoggedOutEvent($userId, new DateTimeImmutable())
      );
  }
  ```

  > Donde `$userId` se obtiene de `$user['id']` antes de destruir la sesión (ya se lee en la propia `logout()` actual).

- [ ] **C13 — Dispatch en ReservationService::cancel():** En `app/Services/ReservationService.php`, dentro de `cancel()`, tras confirmar `$success === true`, añadir:

  ```php
  if ($success && $this->eventDispatcher !== null) {
      $this->eventDispatcher->dispatch(
          new ReservationCancelledEvent($reservationId, $userId, new DateTimeImmutable())
      );
  }
  ```

  > Verificar si `ReservationService` ya recibe `eventDispatcher` por constructor. Si no, añadirlo (siguiendo el patrón de `AuthService`).

---

## Checklist de verificación final

- [ ] `make test-unit` → todos los tests pasan, incluyendo los 7 tests nuevos
- [ ] `make phpstan` → sin errores nuevos respecto a la baseline
- [ ] `make cs-check` → PSR-12 OK
- [ ] Manual smoke test: `make dev` → `make logs-app` → hacer login → ver líneas `[HTTP] →`, `[HTTP] ←` y `[Auth] Usuario autenticado` en stdout
- [ ] Manual: header `X-Request-ID` presente en responses HTTP (inspeccionar con devtools o curl)

---

## Scope y decisiones

**Incluido:**

- `RequestLogMiddleware` — una línea de log por request (entrada + salida)
- Fix del formato dev del logger (`%context%` añadido)
- Eliminación de ~5 debug logs temporales en `AuthService`
- Eventos `UserLoggedInEvent`, `UserLoggedOutEvent`, `ReservationCancelledEvent` con listeners

**Excluido (scope futuro):**

- `AdminActionEvent`, `HealthCheckCreatedEvent`
- Propagación del request_id como contexto a listeners/eventos
- Convención `Logger::warning` en controllers para `Result::fail` (práctica de equipo, no código)
- Correlación de logs entre request y eventos del mismo request

**No cambia:**

- `ErrorHandlerMiddleware` y `ExceptionHandler::register()` — correctos y complementarios
- Posición de `SecurityHeadersMiddleware` (sigue siendo el primero)
- Todos los listeners existentes
