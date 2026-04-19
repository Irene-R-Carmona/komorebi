# FrankenPHP + Stack — Optimización de Features Nativas

> **Objetivo:** Activar todas las features nativas del stack que están disponibles pero no usadas.
> Cubre FrankenPHP, PHP 8.4, PHP-DI, Symfony Cache, MySQL 8.4, Redis y Docker.
> El cambio de mayor impacto es Worker Mode (5–10× rendimiento). Todo lo demás lo complementa.

**Fecha creación:** 15 de abril de 2026
**Estado:** 🔵 Plan creado — pendiente inicio
**Dependencias:** ninguna (trabaja sobre infra y core, sin romper contratos entre capas)

---

## Diagnóstico de partida

| Feature | Estado actual | Impacto |
|---------|--------------|---------|
| Worker Mode (`frankenphp_handle_request`) | ❌ Modo clásico | 🔴 Máximo |
| PHP-DI compilation (`enableCompilation`) | ❌ No activado | 🟠 Alto |
| Alpine → Debian en Dockerfile.prod | ❌ Alpine (musl lento en PHP-ZTS) | 🟠 Alto |
| OPcache Preloading | ❌ No configurado | 🟠 Alto |
| Symfony Cache TagAwareAdapter | ❌ Sin tags | 🟠 Alto |
| X-Sendfile para facturas PDF | ❌ PHP sirve readfile() | 🟠 Alto |
| 103 Early Hints | ❌ No implementado | 🟠 Alto UX |
| BuildKit cache mounts (Composer) | ❌ Cada build re-descarga | 🟡 Medio |
| GOMEMLIMIT + max_threads auto | ❌ No configurado | 🟡 Medio |
| Prometheus Metrics (admin API) | ❌ Sin observabilidad | 🟡 Medio |
| Caddy Structured Access Logs | ❌ Sin logs HTTP | 🟡 Medio |
| Symfony Cache stampede prevention | ❌ Thundering herd posible | 🟡 Medio |
| MySQL Generated Column (loyalty tier) | ❌ Calculado en PHP | 🟡 Medio |
| MySQL Event Scheduler (expiración rewards) | ❌ No configurado | 🟡 Medio |
| OPcache `interned_strings_buffer` | ❌ Falta directiva | 🟡 Medio |
| MySQL CHECK Constraints | ❌ Solo validado en PHP | 🟡 Medio |
| MySQL indexes compuestos (loyalty_rewards) | ❌ Faltan | 🟡 Medio |
| Redis Streams (reemplazar LISTS) | ❌ Sin ACK, jobs se pierden | 🟡 Medio |
| Mercure Hub (SSE real-time) | ❌ No configurado | 🟠 Alto (negocio) |
| Thread Pool Splitting | ❌ No configurado | 🟡 Medio |
| PHP 8.4 `array_find/any/all` | ❌ No usado | 🟢 Bajo |

---

## Mapa de archivos afectados por fase

| Fase | Archivos |
|------|---------|
| 0.1 | `docker/php/Dockerfile.prod` |
| 0.2 | `docker/php/Dockerfile.prod`, `docker/php/Dockerfile.worker` |
| 0.3 | `docker-compose.yml` |
| 0.4–0.5 | `frankenphp.Caddyfile` |
| 0.6 | `docker/php/ini/opcache.ini`, `docker/php/ini/php.ini` |
| 0.7 | `migrations/020_integrity_indexes.sql` (nuevo) |
| 1.1 | `migrations/021_loyalty_generated_tier.sql` (nuevo), `app/Services/LoyaltyService.php`, `app/Models/LoyaltyCard.php` |
| 1.2 | `migrations/022_loyalty_expiration_event.sql` (nuevo) |
| 1.3 | Repositorio de loyalty (método `getLeaderboard` nuevo) |
| 2.1–2.2 | `app/Core/Cache.php`, servicios con cache |
| 3.1 | `app/Core/Container.php`, `public/index.php`, `docker/php/Dockerfile.prod` |
| 4.1 | `scripts/opcache-preload.php` (nuevo) |
| 4.2 | `docker/php/ini/opcache.ini` |
| 5.1–5.4 | `public/index.php`, `frankenphp.Caddyfile`, `docker-compose.yml`, `bin/*.php` |
| 6.1 | Controllers de vistas principales |
| 6.2 | `frankenphp.Caddyfile`, `app/Services/InvoicePDFService.php` |
| 7.1 | `frankenphp.Caddyfile`, `docker-compose.yml`, `app/Services/MercurePublisherService.php` (nuevo) |
| 7.2 | `app/Core/Queue.php`, `bin/*.php` |
| 7.3 | `frankenphp.Caddyfile` |
| 7.4 | Repos y servicios varios |

---

## FASE 0 — Infraestructura (zero-risk, todo paralelo)

### 0.1 Alpine → Debian en Dockerfile.prod

- **Archivo:** `docker/php/Dockerfile.prod`
- Cambiar `FROM dunglas/frankenphp:1.12.2-php8.4-alpine` → `FROM dunglas/frankenphp:1.12.2-php8.4`
- Cambiar `apk add --no-cache dos2unix` → `apt-get install -y --no-install-recommends dos2unix && rm -rf /var/lib/apt/lists/*`
- Cambiar `addgroup -S/adduser -S` → `groupadd -r/useradd -r`
- **Verificación:** `docker build -f docker/php/Dockerfile.prod -t komorebi-prod .` sin error

- [ ] 0.1 — Alpine → Debian en Dockerfile.prod

### 0.2 BuildKit cache mounts en Dockerfile.prod y Dockerfile.worker

- **Archivo:** `docker/php/Dockerfile.prod` — Stage 1 vendor-build:
  - `RUN composer install --no-dev ...` → `RUN --mount=type=cache,target=/root/.composer/cache composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader`
  - `RUN composer dump-autoload --optimize` → `RUN --mount=type=cache,target=/root/.composer/cache composer dump-autoload --optimize --no-scripts`
- **Archivo:** `docker/php/Dockerfile.worker` — mismo patrón en Stage 1
- **Verificación:** segundo `docker build` tarda < 5s en la etapa Composer

- [ ] 0.2 — BuildKit cache mounts en Dockerfile.prod y Dockerfile.worker

### 0.3 GOMEMLIMIT + GODEBUG en docker-compose.yml

- **Archivo:** `docker-compose.yml`, sección `x-app-env: &app-env`
  - Añadir `GOMEMLIMIT: "438m"` (85% del límite 512m del container app)
  - Añadir `GODEBUG: "cgocheck=0"`
- **Verificación:** `docker compose exec app env | grep GOMEMLIMIT`

- [ ] 0.3 — GOMEMLIMIT + GODEBUG en docker-compose.yml

### 0.4 max_threads + admin API + metrics en frankenphp.Caddyfile

- **Archivo:** `frankenphp.Caddyfile`
- Añadir bloque global antes del bloque `:80`:

  ```caddy
  {
      admin localhost:2019
      metrics
      frankenphp {
          max_threads auto
      }
  }
  ```

- **Verificación:** `curl http://localhost:2019/metrics | head -20` devuelve métricas Prometheus

- [ ] 0.4 — max_threads + admin API + metrics en Caddyfile

### 0.5 Caddy Structured Access Logs en frankenphp.Caddyfile

- **Archivo:** `frankenphp.Caddyfile`, dentro del bloque `:80 { ... }`
- Añadir:

  ```caddy
  log {
      output stdout
      format json
      level INFO
  }
  ```

- **Verificación:** `docker compose logs app` muestra JSON con `"request"`, `"status"`, `"duration"`

- [ ] 0.5 — Caddy Structured Access Logs

### 0.6 OPcache directives faltantes en opcache.ini (producción)

- **Archivo:** `docker/php/ini/opcache.ini` — añadir al final:

  ```ini
  opcache.interned_strings_buffer=32
  opcache.huge_code_pages=0
  ```

- **Archivo:** `docker/php/php.ini` — añadir:

  ```ini
  realpath_cache_size=4096K
  realpath_cache_ttl=600
  ```

- **Verificación:** `docker compose exec app php -r "var_dump(opcache_get_status()['interned_strings_usage']);"`

- [ ] 0.6 — OPcache directives faltantes

### 0.7 Nueva migración: CHECK Constraints + índices compuestos

- **Archivo nuevo:** `migrations/020_integrity_indexes.sql`
- Contenido:

  ```sql
  ALTER TABLE user_animal_visits
      ADD CONSTRAINT chk_interaction_rating
      CHECK (interaction_rating BETWEEN 1 AND 5 OR interaction_rating IS NULL);

  ALTER TABLE loyalty_rewards ADD INDEX idx_status_expires (status, expires_at);
  ALTER TABLE loyalty_rewards ADD INDEX idx_user_status (user_id, status);
  ALTER TABLE reservations ADD INDEX idx_created_status (created_at, status);
  ```

- **Verificación:** `make db-migrate` sin errores; `SHOW INDEX FROM loyalty_rewards` muestra nuevos índices

- [ ] 0.7 — Migración CHECK Constraints + índices

---

## FASE 1 — MySQL native features

**Dependencia:** Fase 0.7 completada. Pasos 1.1–1.3 son independientes entre sí (paralelos).

### 1.1 Generated Column para `loyalty_cards.current_tier`

- **Archivo nuevo:** `migrations/021_loyalty_generated_tier.sql`
- Pasos SQL:
  1. `ALTER TABLE loyalty_cards DROP INDEX idx_tier;`
  2. `ALTER TABLE loyalty_cards MODIFY current_tier VARCHAR(10) GENERATED ALWAYS AS (CASE WHEN visits_count >= 50 THEN 'platinum' WHEN visits_count >= 20 THEN 'gold' WHEN visits_count >= 10 THEN 'silver' ELSE 'bronze' END) STORED NOT NULL;`
  3. `ALTER TABLE loyalty_cards ADD INDEX idx_tier (current_tier);`
- **Archivo:** `app/Services/LoyaltyService.php` — eliminar el bloque que calcula `$newTier` + `updateTier()` y el método privado `calculateTier()`
- **Archivo:** `app/Models/LoyaltyCard.php` — eliminar/no usar el método `updateTier()`
- **Verificación:** `INSERT INTO loyalty_cards (user_id, visits_count) VALUES (999, 15)` → `current_tier = 'silver'` sin update explícito

- [ ] 1.1 — Generated Column loyalty_cards.current_tier

### 1.2 MySQL Event Scheduler para expiración de `loyalty_rewards`

- **Archivo nuevo:** `migrations/022_loyalty_expiration_event.sql`
- Contenido:

  ```sql
  SET GLOBAL event_scheduler = ON;
  CREATE EVENT IF NOT EXISTS evt_expire_loyalty_rewards
    ON SCHEDULE EVERY 1 HOUR
    DO
      UPDATE loyalty_rewards
         SET status = 'expired', updated_at = NOW()
       WHERE status = 'pending' AND expires_at < NOW();
  ```

- **Verificación:** `SHOW EVENTS` muestra el evento; `SELECT @@event_scheduler` devuelve `ON`

- [ ] 1.2 — MySQL Event Scheduler loyalty_rewards

### 1.3 Window Functions en queries de estadísticas (leaderboard)

- Añadir `getLeaderboard(int $limit = 10): array` en el repositorio de loyalty usando `RANK() OVER (ORDER BY stamps DESC)`
- **Verificación:** query devuelve filas con columna `rank` sin procesamiento PHP

- [ ] 1.3 — Window Functions leaderboard loyalty

---

## FASE 2 — Cache layer (independiente)

**Dependencia:** ninguna. Pasos 2.1 → 2.2 son secuenciales.

### 2.1 TagAwareAdapter en `app/Core/Cache.php`

- Añadir `use Symfony\Component\Cache\Adapter\TagAwareAdapter;` y `use Symfony\Component\Cache\TagAwareCache;`
- En `init()`: envolver `RedisAdapter` en `new TagAwareAdapter(new RedisAdapter($r))`
- Añadir `Cache::invalidateTags(array $tags): bool`
- Añadir `Cache::setWithTags(string $key, mixed $value, array $tags, int $ttl = 0): bool`
- Definir constantes de tags en clase `CacheTags` (`CacheTags::MENU`, `CacheTags::CAFE`, etc.)
- **Verificación:** unit test — set con tag 'menu', invalidate tag 'menu', get devuelve null

- [ ] 2.1 — TagAwareAdapter en Cache.php

### 2.2 Stampede Prevention: PSR-6 callback pattern

- Añadir `Cache::computeIfAbsent(string $key, callable $fn, int $ttl, array $tags = []): mixed`
- Reemplazar patrón `get → null check → compute → set` en servicios críticos (menú, listado de cafés)
- **Verificación:** solo 1 llamada costosa en acceso concurrente

- [ ] 2.2 — Stampede prevention computeIfAbsent

---

## FASE 3 — PHP-DI Container Compilation

**Dependencia:** ninguna para añadir el método. **Debe completarse ANTES de Fase 5.**

### 3.1 Añadir `enableCompilation()` en `Container.php` y activar en producción

- **Archivo:** `app/Core/Container.php`
  - Añadir `private static ?string $compilationPath = null;`
  - Añadir `public static function enableCompilation(string $path): void { self::$compilationPath = $path; }`
  - En `ensureBuild()`, antes de `$builder->build()`:

    ```php
    if (self::$compilationPath !== null) {
        $builder->enableCompilation(self::$compilationPath);
        $builder->writeProxiesToFile(true, self::$compilationPath);
    }
    ```

- **Archivo:** `public/index.php`, antes de `require bootstrap/container.php`:

  ```php
  if ($isProduction) {
      \App\Core\Container::enableCompilation(__DIR__ . '/../storage/cache/di');
  }
  ```

- **Archivo:** `docker/php/Dockerfile.prod` — añadir warmup paso:

  ```dockerfile
  RUN mkdir -p /app/storage/cache/di && \
      APP_ENV=production php -r "require '/app/public/index.php';" || true
  ```

- **Verificación:** `ls storage/cache/di/` muestra `CompiledContainer.php` tras primer request

- [ ] 3.1 — PHP-DI Container Compilation

---

## FASE 4 — OPcache Preloading (precondición para Worker Mode)

**Dependencia:** Fase 3 completada.

### 4.1 Crear `scripts/opcache-preload.php`

- Archivo nuevo: precompila con `opcache_compile_file()` todas las clases de `app/Core/`, `app/Domain/DTO/`, `app/Services/Contracts/`, y `app/Repositories/AbstractRepository.php`

- [ ] 4.1 — Crear scripts/opcache-preload.php

### 4.2 Activar preloading en `opcache.ini` de producción

- Añadir al `docker/php/ini/opcache.ini`:

  ```ini
  opcache.preload=/app/scripts/opcache-preload.php
  opcache.preload_user=www-data
  ```

- **Verificación:** `docker compose exec app php -r "print_r(opcache_get_status()['preload_statistics']);"` lista clases precargadas

- [ ] 4.2 — Activar preloading en opcache.ini

---

## FASE 5 — Worker Mode (cambio principal)

**Dependencia:** Fases 3 y 4 completadas. Este es el cambio de mayor impacto.

### 5.1 Adaptar `public/index.php` para `frankenphp_handle_request`

**Sección A — Bootstrap (ejecuta UNA SOLA VEZ al arrancar el worker):**

- error_reporting, autoload, `Config::init()`, `Container::enableCompilation()`, `ExceptionHandler::register()`, `date_default_timezone_set()`
- `require bootstrap/container.php` ← DI container UNA VEZ
- Configuración session driver ← UNA VEZ
- `$router = require app/routes.php` ← UNA VEZ
- `$mwFactory = new MiddlewareFactory(new ResponseFactory())` ← UNA VEZ

**Sección B — Handler per-request (closure):**

```php
$handler = static function() use ($router, $mwFactory, $isProduction): void {
    if (!ob_get_level()) { ob_start(); }
    if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
    Csrf::init();
    try {
        $request = RequestFactory::fromGlobals();
        $pipeline = new MiddlewarePipeline($router);
        $pipeline->pipe(new SecurityHeadersMiddleware());
        $pipeline->pipe($mwFactory->requestLog());
        $pipeline->pipe($mwFactory->errorHandler());
        $response = $pipeline->handle($request);
        (new ResponseEmitter())->emit($response);
    } catch (Throwable $e) {
        ExceptionHandler::handle($e);
    } finally {
        if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }
        if (ob_get_level()) { ob_end_flush(); }
    }
};
```

**Sección C — Loop worker con fallback:**

```php
if (function_exists('frankenphp_handle_request')) {
    $maxRequests = (int)($_SERVER['MAX_REQUESTS'] ?? 500);
    for ($i = 0; !$maxRequests || $i < $maxRequests; $i++) {
        $keepRunning = frankenphp_handle_request($handler);
        gc_collect_cycles();
        if (!$keepRunning) break;
    }
} else {
    $handler(); // fallback: modo clásico (tests, debug)
}
```

- [ ] 5.1 — Adaptar public/index.php para worker mode

### 5.2 Configurar Worker Mode en `frankenphp.Caddyfile`

- Modificar el bloque global (añadido en 0.4):

  ```caddy
  {
      admin localhost:2019
      metrics
      frankenphp {
          max_threads auto
          worker {
              file /app/public/index.php
              num 4
              max_consecutive_failures 3
              env MAX_REQUESTS 500
          }
      }
  }
  ```

- `num 4` — ajustar según CPUs del container; 1 CPU → usar `num 2`

- [ ] 5.2 — Worker Mode en Caddyfile

### 5.3 Variables de entorno worker en `docker-compose.yml`

- Añadir al servicio `app`: `MAX_REQUESTS: 500`

- [ ] 5.3 — MAX_REQUESTS en docker-compose.yml

### 5.4 Verificar `session_write_close()` en workers de cola

- `bin/worker.php`, `bin/email-worker.php`, `bin/notification-worker.php` — confirmar que no usan sesión; si la usan, añadir `session_write_close()` tras cada job

- [ ] 5.4 — session_write_close en workers de cola

**Verificación de Fase 5:**

- `docker compose exec app curl -v http://localhost/health` → 200
- `make test-unit` → 0 errores (tests usan fallback modo clásico vía `function_exists`)
- `make test-integration` → verde con BD real
- Load test k6: 100 req/s durante 30s → p99 < 50ms

---

## FASE 6 — HTTP layer improvements (independientes entre sí)

**Dependencia:** ninguna bloqueante.

### 6.1 103 Early Hints en controllers de vistas principales

- En métodos que hacen queries lentas (menú público, dashboard):

  ```php
  header('Link: </css/main.css>; rel=preload; as=style');
  header('Link: </js/app.js>; rel=preload; as=script');
  headers_send(103); // FrankenPHP envía esto inmediatamente
  // ... queries lentas ...
  View::render(...);
  ```

- Solo en vistas que tarden > 100ms
- **Verificación:** `curl -I http://localhost/menu` → ver `HTTP/1.1 103 Early Hints`

- [ ] 6.1 — 103 Early Hints en vistas principales

### 6.2 X-Sendfile para descarga de facturas

- **Archivo:** `frankenphp.Caddyfile`, dentro del bloque `:80`:

  ```caddy
  intercept {
      @accel header X-Accel-Redirect *
      handle_response @accel {
          root /app/
          rewrite * {resp.header.X-Accel-Redirect}
          method * GET
          header -X-Accel-Redirect
          file_server
      }
  }
  ```

- **Archivo:** `app/Services/InvoicePDFService.php` o su controller
  - Reemplazar `readfile()` → `header('X-Accel-Redirect: /storage/invoices/' . $filename);`
- **Verificación:** descarga de factura no bloquea un thread PHP

- [ ] 6.2 — X-Sendfile para facturas PDF

---

## FASE 7 — Features avanzadas (mayor esfuerzo)

### 7.1 Mercure Hub (Real-time SSE)

- Casos de uso: KDS (topic: `kds/{cafe_id}/orders`), recepción (topic: `reception/{cafe_id}/reservations`), waitlist
- Añadir bloque `mercure { ... }` en Caddyfile con publisher/subscriber JWT
- Crear `app/Services/MercurePublisherService.php`
- Frontend: `EventSource` JS en vistas KDS/recepción

- [ ] 7.1 — Mercure Hub SSE

### 7.2 Redis Streams (queue resiliente)

- Migrar `app/Core/Queue.php` de `RPUSH/BLPOP` → `XADD/XREADGROUP/XACK`
- Añadir dead-letter stream para jobs fallidos
- `XGROUP CREATE stream workers $ MKSTREAM` en bootstrap del worker

- [ ] 7.2 — Redis Streams reemplaza LISTS

### 7.3 Thread Pool Splitting

- Solo tras Worker Mode activo (Fase 5)
- Workers separados para `/admin/*` (2 threads) vs rutas públicas (4 threads)

- [ ] 7.3 — Thread Pool Splitting

### 7.4 PHP 8.4 `array_find/any/all`

- Reemplazar `array_filter(...)[0] ?? null` y loops de búsqueda con funciones nativas PHP 8.4
- Solo en `app/Repositories/` y `app/Services/`

- [ ] 7.4 — PHP 8.4 array_find/any/all

---

## Orden de implementación

```
Semana 1:
  Día 1-2: Fase 0 completa (0.1–0.7) — todo en paralelo, sin riesgo
  Día 3:   Fase 1 (1.1 + 1.2) || Fase 2 (2.1) — paralelos
  Día 4:   Fase 3 (Container compilation)
  Día 5:   Fase 4 (OPcache preloading)

Semana 2:
  Día 1-2: Fase 5 (Worker Mode) — test completo antes de declarar completado
  Día 3:   Fase 6 (Early Hints + X-Sendfile)
  Día 4+:  Fase 7 según prioridad de negocio
```

---

## Verificación final

1. `make ci` → PHPStan + tests + cs-check todo en verde
2. `make e2e` → Playwright sin regresiones
3. `docker compose exec app curl http://localhost:2019/metrics` → métricas Prometheus activas
4. `docker compose exec app php -r "var_dump(function_exists('frankenphp_handle_request'));"` → `true`
5. Load test k6: 200 req/s × 1 min → p99 < 50ms, 0 errores 5xx
