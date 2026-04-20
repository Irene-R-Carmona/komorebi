# Infraestructura Railway + Limpieza SOLID — SQL en Servicios

**Fecha:** 2026-04-20
**Rama:** develop
**Estado:** Bloques 1–4 completados ✅ | SOLID parcial ✅ | Deuda pendiente documentada

---

## Contexto

Dos objetivos paralelos ejecutados en la misma sesión:

1. **Railway hardening** — corrección de bugs críticos que impedían el despliegue en producción
2. **SOLID cleanup** — eliminación de SQL crudo en servicios (ReviewService y WaitlistService)

---

## BLOQUE 1 — CRÍTICO (sin esto Railway no funciona)

| # | Cambio | Archivo | Estado |
|---|--------|---------|--------|
| 1.1 | Eliminar `scripts/` de `.dockerignore` — sin esto `apply-db.php` no existe en imagen prod | [.dockerignore](.dockerignore) | ✅ |
| 1.2 | `healthcheckPath: /health` (no `/` homepage) + timeout 10s + retries 5 | [railway.json](railway.json) | ✅ |
| 1.3 | `ENV SKIP_COMPOSER=1` — vendor ya pre-compilado en Stage 1 | [docker/php/Dockerfile.prod](docker/php/Dockerfile.prod) | ✅ |

## BLOQUE 2 — ALTO (estabilidad y seguridad)

| # | Cambio | Archivo | Estado |
|---|--------|---------|--------|
| 2.1 | Worker heartbeat: `/tmp/worker-email-heartbeat` cada 60s | [app/Workers/EmailWorker.php](app/Workers/EmailWorker.php) | ✅ |
| 2.1 | Worker heartbeat: `/tmp/worker-notification-heartbeat` cada 60s | [app/Workers/NotificationWorker.php](app/Workers/NotificationWorker.php) | ✅ |
| 2.1 | Healthcheck workers: `test -f /tmp/worker-*-heartbeat && [ $((date-cat)) -lt 120 ]` | [docker-compose.yml](docker-compose.yml) | ✅ |
| 2.3 | SSL/TLS MySQL via `DB_SSL_CA` env var | [app/Core/Database.php](app/Core/Database.php) | ✅ |
| 2.4 | HTTP→HTTPS redirect via `X-Forwarded-Proto` (Railway termina TLS upstream) | [frankenphp.Caddyfile](frankenphp.Caddyfile) | ✅ |

## BLOQUE 3 — MEDIO (observabilidad)

| # | Cambio | Archivo | Estado |
|---|--------|---------|--------|
| 3.1 | Sentry `\Sentry\init()` condicional por `SENTRY_DSN` env var | [public/index.php](public/index.php) | ✅ |
| 3.1 | `\Sentry\captureException($e)` en handler global | [app/Core/ExceptionHandler.php](app/Core/ExceptionHandler.php) | ✅ |
| 3.2 | `/health` endpoint: `version`, `failed_jobs`, alert degraded si >50 failed | [app/routes.php](app/routes.php) | ✅ |
| 3.3 | Nuevas vars: `APP_HTTPS`, `SKIP_COMPOSER`, `DB_SSL_CA`, `SENTRY_DSN`, `APP_VERSION` | [.env.example](.env.example) | ✅ |

## BLOQUE 4 — BAJO (polish operacional)

| # | Cambio | Archivo | Estado |
|---|--------|---------|--------|
| 4.1 | `chmod -R 755` + `u+w` en subdirs de escritura (era `775`) | [docker/php/docker-entrypoint.sh](docker/php/docker-entrypoint.sh) | ✅ |
| 4.2 | Idempotency key en `SendEmailJob` via `Cache::has/set` + `_correlation_id` | [app/Jobs/SendEmailJob.php](app/Jobs/SendEmailJob.php) | ✅ |

---

## SOLID — Servicios sin SQL crudo

### Completado

| Servicio | Cambio | Estado |
|----------|--------|--------|
| `ReviewService` | Reemplaza `PDO $db` + SQL crudo por `ReservationRepositoryInterface::hasCompletedReservation()` | ✅ |
| `WaitlistService::getPosition()` | Reemplaza `COUNT(*)` raw por `WaitlistRepository::countByTimeSlotAndStatus()` | ✅ |
| `WaitlistService::cancelWaitlist()` | Reemplaza `SELECT * WHERE id=? AND user_id=?` raw por `WaitlistRepository::findByIdAndUser()` | ✅ |
| `ReservationRepository` | Nuevo método `hasCompletedReservation(int $userId, int $cafeId): bool` | ✅ |
| `WaitlistRepository` | Nuevos métodos `findByIdAndUser()` + `countByTimeSlotAndStatus()` | ✅ |
| `ReviewServiceTest` | Reescrito: mock correcto `UserRepositoryInterface` + 3er arg `ReservationRepositoryInterface` | ✅ |
| `ReviewIntegrationTest` | Constructor actualizado con `new ReservationRepository(self::$db)` | ✅ |
| `SharedServiceProvider` | Binding `ReviewService` actualizado con 3 dependencias | ✅ |

### Deuda restante (pendiente)

| Servicio | SQL pendiente | Acción sugerida |
|----------|---------------|-----------------|
| `AuthTokenService` | ~11 queries en tablas `email_verification_tokens` + `password_reset_tokens` | Crear `AuthTokenRepository` + interface |
| `Manager/DashboardService` | ~11 queries de KPIs de manager | Crear `ManagerDashboardRepository` |
| `ProductService` | ~9 queries (JOIN categorías, toggle, search, allergens) | Añadir métodos a `ProductRepository` |
| `TimeSlotService` | 1 query (línea ~66) | Verificar y mover a `TimeSlotRepository` |

---

## Verificación de tecnologías (2026-04-20)

Ejecutado con `docker compose run --rm app php -r "require '/app/vendor/autoload.php'; ..."`:

| Tecnología | Versión | Estado |
|------------|---------|--------|
| PHP | 8.4.20 | ✅ |
| PDO MySQL driver | — | ✅ |
| ext-redis | — | ✅ |
| ext-gd | — | ✅ |
| ext-mbstring | — | ✅ |
| ext-curl | — | ✅ |
| ext-json, zip, iconv, posix, pcntl | — | ✅ |
| monolog/monolog | ^3.0 | ✅ |
| symfony/cache | ^8.0 | ✅ |
| symfony/event-dispatcher | ^8.0 | ✅ |
| nyholm/psr7 + psr7-server | ^1.x | ✅ |
| php-di/php-di | ^7.0 | ✅ |
| phpmailer/phpmailer | ^7.0 | ✅ |
| vlucas/phpdotenv | ^5.5 | ✅ |
| setasign/tfpdf | ^1.33 | ✅ |
| chillerlan/php-qrcode | ^5.0 | ✅ (namespace: `chillerlan\QRCode`) |
| phpunit/phpunit | ^13 | ✅ |
| phpstan/phpstan + phpunit ext | ^2.0 | ✅ |
| friendsofphp/php-cs-fixer | ^3.0 | ✅ |
| brianium/paratest | ^7.22 | ✅ |
| nunomaduro/pao + collision | ^1.0, ^8.9 | ✅ |
| qossmic/deptrac | ^2.0 | ✅ |

> **Nota:** `chillerlan/php-qrcode` usa namespace con `c` minúscula: `chillerlan\QRCode\QRCode`.
> El app ya lo referencia correctamente con FQFN `\chillerlan\QRCode\...` en `InvoicePDFService`.

---

## Commit de referencia

`0bcc4e1 feat(infra+solid): Railway prod hardening + SOLID refactor completo`
