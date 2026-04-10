# Plan 1 — Security & Hardening

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps marked ✅ son confirmaciones de estado — NO re-implementar lo que ya está hecho.

**Goal:** Eliminar tres gaps de seguridad reales que quedan tras la implementación previa: (1) conflicto de headers entre `View::sendSecurityHeaders()` y `SecurityHeadersMiddleware` PSR-7, (2) `RateLimitingServiceInterface` sin binding en DI container, y (3) `AuthService` que crea `RateLimitingService` inline rompiendo la inyección de dependencias.

**Architecture:** `SecurityHeadersMiddleware` ya está aplicado en `public/index.php` L161 vía pipeline PSR-15. Los headers de seguridad se envían correctamente por la vía PSR-7. El problema es que `View.php` también envía headers vía PHP `header()` (camino más antiguo), lo que puede causar duplicación o anulación del CSP con nonce. El DI gap hace que el rate limiter no pueda ser stubbado limpiamente en tests ni reemplazado por configuración.

**Tech Stack:** PHP 8.4, PSR-7/PSR-15, PSR-6 `CacheItemPoolInterface`, DI Container singleton, `View::render()`.

---

## Estado confirmado por auditoría (NO re-implementar)

| Item | Estado | Evidencia |
|---|---|---|
| `SecurityHeadersMiddleware` implementado | ✅ | `app/Middleware/SecurityHeadersMiddleware.php` — CSP nonce, HSTS, 9 headers |
| Aplicado globalmente | ✅ | `public/index.php` L161: `$pipeline->pipe(new SecurityHeadersMiddleware())` |
| Tests del middleware | ✅ | `tests/Unit/Middleware/SecurityHeadersMiddlewareTest.php` con cobertura completa |
| `Database::validateCharset()` whitelist | ✅ | `app/Core/Database.php` L124–133: whitelist + regex validation |
| `RateLimitingService` en Redis/PSR-6 | ✅ | Usa `CacheItemPoolInterface`, sin queries SQL |
| `RateLimitingServiceTest` 7 tests | ✅ | `tests/Unit/Services/RateLimitingServiceTest.php` |
| `declare(strict_types=1)` en todos los archivos | ✅ | Muestra de 10+ archivos: 100% cumplimiento |

---

## Mapa de archivos afectados

| Grupo | Archivo | Acción |
|---|---|---|
| A | `app/Core/View.php` | Auditar y eliminar calls a `sendSecurityHeaders()` |
| A | `resources/views/**/*.php` | Añadir nonce en `<script>` inline si faltan |
| B | `bootstrap/container.php` | Añadir binding `RateLimitingServiceInterface` |
| B | `app/Services/AuthService.php` | Recibir interface inyectada en lugar de `new` inline |
| C | `app/Core/MiddlewareFactory.php` | Usar container para resolver `RateLimitingService` |
| D | `tests/Unit/Services/AuthServiceTest.php` | Actualizar `setUp()` si cambia el constructor |

---

## Grupo A — Resolver conflicto de headers View vs Middleware

### A1: Auditar si `View::sendSecurityHeaders()` se invoca durante el ciclo request

**Problema:** `View.php` L38–L48 tiene un método `sendSecurityHeaders()` que usa PHP `header()` directamente. Si se invoca dentro de `View::render()`, los headers PHP se envían ANTES de que el emitter PSR-7 aplique los de `SecurityHeadersMiddleware` (que incluyen el nonce dinámico). Esto causa:

- Headers duplicados o anulados (PHP procesa el primero en llegar)
- El CSP de `View.php` usa `'unsafe-inline'` — más permisivo y sin nonce — podría sobreescribir el CSP seguro del middleware
- Silencio en errores: PHP ignora `header()` duplicados sin excepción

**Tareas:**

- [ ] Leer `app/Core/View.php` L29–L80 — confirmar si `render()` llama a `sendSecurityHeaders()` internamente
- [ ] Ejecutar `grep -rn "sendSecurityHeaders"` en todo el proyecto — listar todos los call sites
- [ ] Ejecutar `grep -rn "getSecurityHeaders"` en todo el proyecto — listar todos los call sites

---

### A2: Eliminar o marcar obsoleto `sendSecurityHeaders()`

**Condición:** Ejecutar solo si A1 confirma que el método se llama efectivamente durante un request.

**Fix:** `SecurityHeadersMiddleware` ya gestiona los headers vía PSR-7 correctamente. Los headers en `View.php` son un vestigio más débil (sin nonce).

```php
// Si render() llama sendSecurityHeaders() internamente — eliminar esa llamada:
// View::render() debe renderizar HTML, no enviar headers HTTP

// Marcar el método como obsoleto:
/**
 * @deprecated Usar SecurityHeadersMiddleware vía pipeline PSR-15.
 *             Los headers de seguridad se gestionan en public/index.php
 *             a través del pipeline PSR-15, no desde View.
 */
public static function sendSecurityHeaders(): void { ... }
```

- [ ] Si `render()` llama `sendSecurityHeaders()` internamente: eliminar esa llamada de `render()`
- [ ] Añadir `@deprecated` al docblock de `sendSecurityHeaders()` con la nota anterior
- [ ] Eliminar cualquier call site externo encontrado en A1
- [ ] ⚠️ Si NO hay ningún call site activo: añadir solo `@deprecated` y anotar como dead code para limpieza futura

---

### A3: Verificar nonces en scripts inline de vistas

**Contexto:** `SecurityHeadersMiddleware` genera un nonce por request y lo almacena en `$GLOBALS['cspNonce']`. Las vistas con `<script>` inline deben usar `nonce="<?= \App\Middleware\SecurityHeadersMiddleware::getNonce() ?>"` para no ser bloqueadas por CSP.

- [ ] Ejecutar `grep -rn "SecurityHeadersMiddleware::getNonce"` en `resources/views/` — ver qué vistas ya lo usan
- [ ] Ejecutar `grep -rn "<script"` en `resources/views/` — identificar scripts inline sin atributo `nonce`
- [ ] Para cada `<script>` inline sin nonce encontrado: añadir el atributo

  ```html
  <!-- ANTES -->
  <script>
      const config = <?= $alpineConfig ?>;
  </script>

  <!-- DESPUÉS -->
  <script nonce="<?= \App\Middleware\SecurityHeadersMiddleware::getNonce() ?>">
      const config = <?= $alpineConfig ?>;
  </script>
  ```

- [ ] ⚠️ Scripts cargados con `src=""` (externos) no necesitan nonce — solo los inline
- [ ] ⚠️ Atributos Alpine.js `x-data`, `x-bind`, etc. en el HTML no son `<script>` inline — no requieren nonce (y la directiva CSP ya incluye `'unsafe-eval'` para Alpine)

---

## Grupo B — Binding de RateLimitingServiceInterface en DI container

### B1: Registrar singleton en bootstrap/container.php

**Problema:** `RateLimitingServiceInterface` existe en `app/Services/Contracts/` pero no está vinculada al container. Cualquier clase que la declare en su constructor recibiría un error al resolverla via DI.

**Fix:**

```php
// bootstrap/container.php — añadir en la sección de singletons de servicios:
use App\Services\RateLimitingService;
use App\Services\Contracts\RateLimitingServiceInterface;
use Psr\Cache\CacheItemPoolInterface;

Container::singleton(
    RateLimitingServiceInterface::class,
    fn() => new RateLimitingService(
        Container::make(CacheItemPoolInterface::class)
    )
);
```

**Tareas:**

- [ ] Leer `bootstrap/container.php` — identificar la sección donde se registran singletons de servicios (después de los providers)
- [ ] Verificar que `CacheItemPoolInterface` ya está registrado (lo registra `CacheServiceProvider`)
- [ ] Añadir el `Container::singleton(RateLimitingServiceInterface::class, ...)` en la ubicación correcta
- [ ] Añadir los `use` statements necesarios al inicio del archivo
- [ ] Ejecutar `make phpstan` — sin errores nuevos

---

### B2: Actualizar AuthService para recibir interface inyectada

**Archivo:** `app/Services/AuthService.php`

**Problema (L33, L43, L52 aprox.):**

```php
// ANTES — crea la dependencia inline:
private RateLimitingService $rateLimiter;

public function __construct(
    ...,
    ?RateLimitingService $rateLimiter = null,
) {
    $this->rateLimiter = $rateLimiter ?? new RateLimitingService(
        Container::make(CacheItemPoolInterface::class)
    );
}
```

**Fix:**

```php
// DESPUÉS — recibe la interface inyectada:
private RateLimitingServiceInterface $rateLimiter;

public function __construct(
    ...,
    RateLimitingServiceInterface $rateLimiter,
) {
    $this->rateLimiter = $rateLimiter;
}
```

**Tareas:**

- [ ] Leer `app/Services/AuthService.php` constructor completo (primeras ~60 líneas)
- [ ] Cambiar tipo de propiedad: `private RateLimitingService` → `private RateLimitingServiceInterface`
- [ ] Cambiar parámetro constructor: `?RateLimitingService $rateLimiter = null` → `RateLimitingServiceInterface $rateLimiter`
- [ ] Eliminar la lógica inline `$rateLimiter ?? new RateLimitingService(...)` — reemplazar por `$this->rateLimiter = $rateLimiter;`
- [ ] Añadir `use App\Services\Contracts\RateLimitingServiceInterface;` — eliminar el `use` de la clase concreta si ya no se referencia en el archivo
- [ ] Verificar que el ServiceProvider o quien instancie `AuthService` en el container ya le pasa la interface (que ahora está registrada en B1)

---

### B3: MiddlewareFactory — resolver vía container

**Archivo:** `app/Core/MiddlewareFactory.php`

El método `rateLimit()` (~L158–L169) instancia `new RateLimitingService(...)` directamente. Debe resolver via container para respetar el singleton registrado en B1.

```php
// ANTES (aprox. L169):
return new HttpRateLimitMiddleware(
    $this->response,
    new RateLimitingService(Container::make(CacheItemPoolInterface::class)),
    $action
);

// DESPUÉS:
return new HttpRateLimitMiddleware(
    $this->response,
    Container::make(RateLimitingServiceInterface::class),
    $action
);
```

**Tareas:**

- [ ] Leer `app/Core/MiddlewareFactory.php` método `rateLimit()` completo
- [ ] Reemplazar `new RateLimitingService(...)` por `Container::make(RateLimitingServiceInterface::class)`
- [ ] Añadir `use App\Services\Contracts\RateLimitingServiceInterface;` si no existe
- [ ] Verificar que `Container` ya está importado en el archivo

---

## Grupo C — Tests actualizados

### C1: Actualizar AuthServiceTest tras cambio de constructor

**Condición:** Solo si B2 cambió la firma del constructor de `AuthService`.

```php
// setUp() en AuthServiceTest — ANTES:
$this->authService = new AuthService(
    $this->userRepo,
    $this->session,
    // null implícito para $rateLimiter — ya no válido
);

// DESPUÉS:
$this->rateLimiterStub = $this->createStub(RateLimitingServiceInterface::class);
$this->authService = new AuthService(
    $this->userRepo,
    $this->session,
    $this->rateLimiterStub,
);
```

**Tareas:**

- [ ] Leer `tests/Unit/Services/AuthServiceTest.php` `setUp()` — ver cómo se construye `AuthService`
- [ ] Si usa parámetro `null` o valor por defecto para el rateLimiter: actualizar para pasar `$this->createStub(RateLimitingServiceInterface::class)`
- [ ] Añadir `use App\Services\Contracts\RateLimitingServiceInterface;` al archivo de test
- [ ] Ejecutar `make test-unit` — debe pasar en verde

---

## Verificación final

```bash
make phpstan          # sin errores nuevos respecto a la baseline
make test-unit        # todos en verde
```

Verificación manual:

1. Network tab del navegador → cargar cualquier página → los headers de seguridad deben aparecer **una sola vez** en la respuesta
2. `Content-Security-Policy` debe incluir `nonce-<valor>` en la respuesta
3. Consola del navegador sin errores CSP en páginas con scripts inline

---

## Commits esperados

```
fix: eliminar sendSecurityHeaders() calls desde View.php (SecurityHeadersMiddleware gestiona headers)
fix: añadir nonce CSP en scripts inline de vistas que lo requerían
refactor: RateLimitingServiceInterface binding en DI container
refactor: AuthService recibe RateLimitingServiceInterface inyectada, elimina new inline
refactor: MiddlewareFactory usa Container::make para RateLimitingService
test: actualizar AuthServiceTest tras cambio de firma del constructor
```

## Siguiente fase

Una vez completado Plan 1, continuar en paralelo con:

- **Plan 4** (`2026-04-10-plan4-reservation-repos.md`) — ReservationService → Repos
- **Plan 5** (`2026-04-10-plan5-tests.md`) — Cobertura de tests

Los tres planes de Fase 1 son independientes entre sí.
Ver `2026-04-10-plan-maestro.md` para el árbol completo de dependencias.
