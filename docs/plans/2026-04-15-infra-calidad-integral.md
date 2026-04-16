# Infraestructura, Calidad Integral y Trazabilidad — Plan de Implementación

> **Contexto:** Plan surgido del análisis de logs Docker del 15/04/2026.
> Cubre bugfixes funcionales, higiene de migraciones, estándar de logging
> profesional, trazabilidad por capas y limpieza de deuda técnica Docker/PHP.
>
> **Restricción:** Proyecto en dev — no existen "migrations de fix". Si hay
> un error en una migration, se corrige en el archivo original y se borra el fix.

**Fecha creación:** 15 de abril de 2026
**Estado:** 🔵 Plan creado — pendiente inicio
**Dependencias:** ninguna (trabaja sobre archivos de infra y core, sin tocar contratos entre capas)

---

## Estado de partida — Diagnóstico

### Ya completado (commit `fix(docker): harden security + fix Dockerfile.prod`, 2026-04-15)

- [x] `Dockerfile.prod`: añadido `COPY . /app` en Stage 2 (imagen prod no tenía código)
- [x] `Dockerfile.prod`: eliminado `/app/docker` tras build + fijado orden `dos2unix`
- [x] `docker-compose.yml`: `security_opt: no-new-privileges:true` en todos los servicios
- [x] `docker-compose.yml`: `cap_drop: ALL` en servicio `cache` (Redis no necesita capabilities)
- [x] `.dockerignore`: añadido `storage/` y `lighthouse-reports/`

### Bugs confirmados pendientes

| ID | Severidad | Archivo | Bug |
|----|-----------|---------|-----|
| A1 | CRÍTICA | `scripts/apply-db.php` | Migraciones 016-018 no están en el array `$migrations` → tablas supervisor_assignments, product_stock, api_tokens nunca se crean |
| A2 | ALTA | `app/Core/Seeders/WaitlistSeeder.php:63` | `WHERE r.name = 'user'` → debe ser `r.code` → 0 usuarios encontrados → seeder vacío |
| A3 | ALTA | `scripts/apply-db.php` prereq ReservationSeeder | No verifica `time_slots > 0` → corre en Pass 1 antes de TimeSlotSeeder → 0 reservas completadas → ReviewSeeder falla los 3 passes |
| A4 | MEDIA | `migrations/019_fix_supervisor_assignments_bigint.sql` | Migration de fix en dev (inaceptable) — su contenido debe fusionarse en 016 y eliminarse |
| A5 | MEDIA | `docker-compose.yml:85` | `dev.cnf` ignorado en Windows Docker (world-writable) → MySQL arranca con defaults en vez de config de dev optimizada |
| A6 | MEDIA | `app/Workers/*.php` | Workers no arrancan en `make dev` — causa a investigar antes del fix |

### Deuda técnica pendiente

| ID | Área | Descripción |
|----|------|-------------|
| C1-C3 | Logging | Emojis y caracteres Unicode en `apply-db.php`, 7 seeders y `bin/quality-check.php` |
| D1-D5 | Trazabilidad | AbstractRepository sin timing, canales `db`/`queue` declarados pero sin handler, `_correlation_id` incompleto en re-push de workers |
| E1-E4 | Scripts | 4 scripts one-shot sin uso productivo |
| F1-F3 | Docker/PHP | Config PHP (xdebug/brotli/session), versiones de imágenes, tooling redundante |

---

## Módulo A — Bugs críticos funcionales

### A1: apply-db.php — Añadir migraciones 016-018 (NO 019)

**Archivo:** `scripts/apply-db.php` ~línea 161
**Acción:** Añadir al array `$migrations`, después de `015_animal_health_checks.sql`:

```php
'016_supervisor_assignments.sql',
'017_product_stock.sql',
'018_api_tokens.sql',
```

> 019 NO se añade — su contenido se fusiona en 016 en el Módulo B y el archivo se elimina.

- [ ] A1 — Añadir migraciones 016-018 al array en apply-db.php

---

### A2: WaitlistSeeder — Fix SQL `r.name` → `r.code`

**Archivo:** `app/Core/Seeders/WaitlistSeeder.php:63`
**Acción:** Cambiar `WHERE r.name = 'user'` → `WHERE r.code = 'user'`

Todos los demás seeders usan `r.code` como identificador único de rol. La columna `name`
no existe como identificador en la tabla `roles`.

- [ ] A2 — WaitlistSeeder: `r.name` → `r.code` en línea 63

---

### A3: apply-db.php — Prereq ReservationSeeder: añadir time_slots check

**Archivo:** `scripts/apply-db.php`, prereq del seeder `Reservations`

**Problema:** El prereq actual solo verifica `users > 0 && cafes > 0 && products['pass'] > 0`.
No exige que `time_slots > 0`. Consecuencia: ReservationSeeder corre en Pass 1 antes
de TimeSlotSeeder → genera reservas sin `time_slot_id` → esas reservas no llegan a
`status='completed'` → ReviewSeeder no encuentra reservas completadas en ningún pass → siempre `pending`.

**Acción:** En el closure del prereq de `Reservations`, añadir:

```php
$t = (int)$db->query('SELECT COUNT(*) FROM time_slots')->fetchColumn();
return $u > 0 && $c > 0 && $p > 0 && $t > 0;
```

Efecto: ReservationSeeder se fuerza a Pass 2 (TimeSlotSeeder corre en Pass 1), las reservas
se crean con `time_slot_id`, llegan a `completed`, ReviewSeeder pasa en Pass 2 o 3.

- [ ] A3 — Prereq ReservationSeeder: añadir verificación `time_slots > 0`

---

### A4: MySQL dev.cnf — Pasar settings via `db.command` en override

**Problema:** Docker en Windows presenta los bind mounts como world-writable.
MySQL 8.4 ignora por seguridad cualquier `.cnf` world-writable → `dev.cnf` nunca se aplica
→ MySQL arranca con defaults (performance_schema ON, buffer pool pequeño, etc.).

**Acción:**

1. `docker-compose.yml`: eliminar la línea `./docker/mysql/dev.cnf:/etc/mysql/conf.d/dev.cnf:ro`
   del bloque `volumes:` del servicio `db`.
2. `docker-compose.override.yml`: añadir bloque `command:` al servicio `db`:

```yaml
services:
  db:
    command:
      - --performance-schema=OFF
      - --innodb-buffer-pool-size=268435456
      - --innodb-flush-log-at-trx-commit=2
      - --innodb-flush-method=O_DIRECT
      - --max-connections=25
      - --key-buffer-size=16777216
      - --slow-query-log=OFF
```

Los flags inline ignoran la restricción de permisos — MySQL los aplica siempre.
`dev.cnf` en disco puede mantenerse como documentación de referencia.

- [ ] A4 — Docker MySQL: mover dev.cnf a `db.command` en compose.override

---

### A5: Workers — investigar y corregir fallo de arranque en `make dev`

**Síntoma:** Workers (`email-worker.php`, `notification-worker.php`, `worker.php`) no arrancan
al ejecutar `make dev`. Causa desconocida — requiere investigar antes de proponer fix.

**Acción:**

1. Ejecutar `make dev` y revisar logs del contenedor app: `docker compose logs app`
2. Verificar que `supervisor.conf` incluye los 3 workers con `autostart=true`
3. Verificar que los paths en `supervisor.conf` coinciden con el filesystem del contenedor
4. Aplicar el fix según causa identificada

- [ ] A5 — Workers: investigar causa de fallo + aplicar fix

---

## Módulo B — Higiene de migraciones

**Principio:** En desarrollo no existen migrations de "fix". Los errores se corrigen
directamente en el archivo original. Un `ALTER TABLE` para compensar un error de tipo
en la misma tabla de la misma iteración de desarrollo es deuda inaceptable.

### B1: 016_supervisor_assignments.sql — Cambiar INT → BIGINT UNSIGNED

**Archivo:** `migrations/016_supervisor_assignments.sql`
**Acción:** En el `CREATE TABLE supervisor_assignments`, cambiar todas las columnas
`INT UNSIGNED` → `BIGINT UNSIGNED` en: `id`, `supervisor_id`, `reservation_id`, `cafe_id`.

Motivación: Todas las demás tablas FK del proyecto usan `BIGINT UNSIGNED`. La inconsistencia
causó el `019_fix` que vamos a eliminar.

- [ ] B1 — 016_supervisor_assignments.sql: INT → BIGINT UNSIGNED en columnas clave

### B2: Eliminar migration 019 (fix migration)

**Archivo:** `migrations/019_fix_supervisor_assignments_bigint.sql`
**Acción:** Eliminar el archivo. Verificar primero que no está referenciado en `apply-db.php`
(no lo está — nunca se añadió al array `$migrations`).

- [ ] B2 — Eliminar `019_fix_supervisor_assignments_bigint.sql`

---

## Módulo C — Estándar de logging profesional

**Regla única:**

- **PROHIBIDO:** emojis (`✅❌⚠️📅🔧🔍📊🎨💡`), Unicode box-drawing (`╔═╗║╚╝═`), `\n` literal en strings
- **TODO output** vía `Logger::info/warning/error('[ClassName] mensaje', $context)`
- Sin `echo` desnudo en seeders ni scripts de infraestructura
- Niveles: `info` = flujo normal, `warning` = datos ausentes/fallback, `error` = fallo con impacto

### C1: scripts/apply-db.php — limpiar logMsg callsites

Reemplazos en las 8+ llamadas a `logMsg()`:

| Antes | Después | Nivel |
|-------|---------|-------|
| `logMsg('\n✅ ...')` | `logMsg('OK: ...', 'info')` | info |
| `logMsg('\n⚠️ ...')` | `logMsg('WARNING: ...', 'warning')` | warning |
| `logMsg('\nWARNING: ...')` | `logMsg('WARNING: ...', 'warning')` | warning |
| Separadores con `╔═╗` etc. | Separadores `---` ASCII | info |

- [ ] C1 — apply-db.php: limpiar emojis y `\n` literal en callsites logMsg

### C2: 7 seeders — `echo` + emojis → `Logger::*`

Seeders a modificar: `ReservationSeeder`, `ReviewSeeder`, `TimeSlotSeeder`,
`WaitlistSeeder`, `SystemSettingsSeeder`, `CafeSeeder`, `StaffSeeder`.

Patrón:

```php
// ANTES
echo "✅ $count registros creados\n";
echo "⚠️ No hay time_slots disponibles\n";

// DESPUÉS
Logger::info('[ReservationSeeder] completed', ['count' => $count]);
Logger::warning('[ReservationSeeder] no time_slots available');
```

- [ ] C2 — 7 seeders: `echo` + emojis → Logger con prefijo `[ClassName]`

### C3: bin/quality-check.php — emojis → marcadores ASCII

| Emoji | Reemplazar por |
|-------|---------------|
| `✅` | `[OK]` |
| `❌` | `[FAIL]` |
| `⚠️` | `[WARN]` |
| `🔧 🔍 📊 🎨 📏 🔎 🔬 💡` | `[STEP]` o texto plano |
| Separadores `╔═╗║╚╝═` | `====` / `----` ASCII |

- [ ] C3 — quality-check.php: emojis → ASCII markers, box-drawing → ASCII

---

## Módulo D — Trazabilidad por capas

### Contexto de lo ya existente

- **request_id** (64 bits) → auto-propagado a TODOS los logs vía `LogContextProcessor` (no requiere código en services)
- **WideEvent** → canonical log line por request (método, path, status, duración, user, cache, body sanitizado)
- **`_correlation_id`** → 17/20 `Queue::push` sites ya lo incluyen; workers lo consumen en `WideEvent::set('request_id', ...)`
- **Canal `db`** → declarado en Logger.php, NINGÚN StreamHandler activo
- **Canal `queue`** → declarado, NINGÚN StreamHandler activo
- **`WideEvent::setSection`** → usado solo en middleware, no en services
- **EmailService** → patrón correcto de referencia (log + `_correlation_id`)

### D1: AbstractRepository — slow query detection

**Archivo:** `app/Repositories/AbstractRepository.php`

Añadir método protegido `execTimed()` que envuelve cada `execute()`:

```php
protected function execTimed(callable $fn, string $sql, array $params = []): mixed
{
    $start = hrtime(true);
    $result = $fn();
    $ms = (hrtime(true) - $start) / 1_000_000;
    if ($ms > 500) {
        Logger::channel('db')->error('[DB] Slow query', [
            'sql'          => substr($sql, 0, 200),
            'params_count' => count($params),
            'duration_ms'  => round($ms, 2),
        ]);
    } elseif ($ms > 100) {
        Logger::channel('db')->warning('[DB] Slow query', [
            'sql'          => substr($sql, 0, 200),
            'params_count' => count($params),
            'duration_ms'  => round($ms, 2),
        ]);
    }
    return $result;
}
```

Envolver los 6 métodos CRUD heredados (`findById`, `findAll`, `exists`, `create`, `update`, `delete`)
con `execTimed(fn() => $stmt->execute($params), $sql, $params)`.

> **Seguridad:** Nunca loguear `$params` completos (pueden contener PII). Solo `count($params)`.

- [ ] D1 — AbstractRepository: añadir execTimed + envolver 6 métodos CRUD

### D2: Logger.php — activar handlers para canales `db` y `queue`

**Archivo:** `app/Core/Logger.php`

Verificar que los canales `db` y `queue` tienen `StreamHandler` real hacia `php://stdout`.
Si solo están en comentarios/docblock sin handler activo, añadir:

```php
// Canal 'db' — queries lentas
$dbLogger = new Logger('db');
$dbLogger->pushProcessor($processor);
$dbLogger->pushHandler(new StreamHandler('php://stdout', $devLevel));
static::$channels['db'] = $dbLogger;

// Canal 'queue' — jobs y workers
$queueLogger = new Logger('queue');
$queueLogger->pushProcessor($processor);
$queueLogger->pushHandler(new StreamHandler('php://stdout', $devLevel));
static::$channels['queue'] = $queueLogger;
```

Nivel dev: `DEBUG` (con `DEBUG_QUERIES=1`). Nivel prod: `WARNING`.

- [ ] D2 — Logger.php: activar StreamHandler en canales db y queue

### D3: WideEvent::setSection en servicios con mutaciones clave

Añadir **solo** donde el contexto de negocio enriquece el canonical log sin log lines extra.
No añadir a todos los services — solo los de alto valor diagnóstico:

| Service + método | setSection a añadir |
|-----------------|---------------------|
| `ReservationService::create` | `reservation` → `['id', 'cafe_id', 'date', 'guests']` |
| `ReviewService::create` | `review` → `['cafe_id', 'rating']` |
| `AuthService::login` (éxito) | `auth` → `['user_id', 'method' => 'login']` |
| `LoyaltyService` stamp/reward | `loyalty` → `['user_id', 'stamps', 'tier']` |

Efecto: el canonical log `POST /reservas status:201 duration:340ms` pasa a incluir
qué reserva se creó, para quién y en qué café — sin líneas adicionales.

- [ ] D3 — WideEvent::setSection en 4 servicios clave

### D4: Logger::warning antes de Result::fail en condiciones diagnosticables

**Distinción crítica:**

- **Tipo A** (validación normal, NO loguear): email ya en uso, fecha pasada, aforo completo → es flow normal
- **Tipo B** (condición diagnosticable, SÍ loguear): fallo de transacción, FK violation, externo no disponible

Para Tipo B, añadir `Logger::warning('[ServiceName::method] failure', ['code' => $code, 'ctx' => ...])` ANTES del `return Result::fail()`.

Services donde añadir (identificados en análisis):

- `ReservationService`: capacidad no disponible por error interno, fallo de transacción
- `AuthService`: fallo al crear sesión, fallo al invalidar token
- `LoyaltyService`: fallo al asignar stamp
- `WaitlistService`: fallo al promover de lista de espera

EmailService ya tiene el patrón correcto — es la referencia.

- [ ] D4 — Logger::warning tipo B antes de Result::fail en 4 services

### D5: Completar _correlation_id en re-push de workers

**Archivos:**

- `app/Workers/NotificationWorker.php` ~línea 183
- `app/Workers/EmailWorker.php` ~línea 184

Los `Queue::push()` internos (re-push de jobs) no incluyen `_correlation_id` → rompen la cadena
de trazabilidad si el job falla y se reencola.

**Acción:** Añadir al payload del re-push:

```php
'_correlation_id' => ($jobData['payload']['_correlation_id'] ?? ''),
```

- [ ] D5 — Workers: añadir _correlation_id en Queue::push de re-push interno

---

## Módulo E — Limpieza de scripts

### E1: Verificar referencias en Makefile y eliminar 4 scripts one-shot

Verificar primero que ninguno aparece en `Makefile` ni en otros scripts, luego eliminar:

| Archivo | Motivo |
|---------|--------|
| `scripts/fix_bom_views.php` | One-shot ejecutado — trabajo completado |
| `scripts/fix_reservation_tests.php` | One-shot de refactoring completado |
| `scripts/check_pass.php` | 10 líneas de debug ad-hoc sin valor |
| `tools/ctx_check.php` | Debug stub sin uso productivo |

Scripts que **se conservan:**

- `scripts/apply-db.php` — crítico para bootstrap DB
- `scripts/benchmark.php` — utilidad de mantenimiento
- `scripts/crawl_depth2.php` — QA utility
- `scripts/csrf_probe.php` — QA utility
- `scripts/docker-build.ps1`, `docker-helper.ps1` — tooling Docker
- `tools/phpstan-bootstrap.php` — stubs PHPStan críticos

- [ ] E1 — Verificar referencias en Makefile
- [ ] E2 — Eliminar 4 scripts one-shot

---

## Módulo F — Docker e infraestructura PHP

### F1: Config PHP menor (docker/php/)

Revisar y ajustar en `docker/php/`:

- **xdebug**: confirmar que `xdebug.mode=off` en prod, solo `debug,develop` en dev con `docker-compose.override.yml`
- **brotli**: verificar que el módulo está habilitado en FrankenPHP/Caddy (`encode br` en Caddyfile — ya presente)
- **session**: revisar `session.gc_maxlifetime` y `session.cookie_secure` (debe ser `1` en prod)

- [ ] F1 — Revisar y ajustar config PHP: xdebug dev-only, session.cookie_secure prod

### F2: Versiones de imágenes Docker

Revisar `docker-compose.yml` y `Dockerfile.prod` para confirmar que las imágenes de base
(`dunglas/frankenphp`, `mysql`, `redis`) están fijadas a versión exacta (no `latest`).
Si hay tags `latest` o sin patch version, fijarlos.

- [ ] F2 — Fijar versiones exactas de imágenes Docker (sin `latest`)

### F3: Decisión tooling redundante (Psalm / PHP_CodeSniffer)

**Decisión tomada (16 abril 2026):** Psalm eliminado. PHPStan nivel 5 cubre el mismo análisis de tipos con menor fricción de CI. PHP CS Fixer se mantiene vía `make cs-check` (PSR-12).

**Cambios aplicados:**

- `bin/psalm.bat` eliminado
- `.quality/baselines/psalm-baseline.xml` eliminado
- `CONTRIBUTING.md` actualizado: se documenta la decisión, se eliminan referencias a `make psalm`
- `make ci` = `phpstan + test + cs-check` (sin psalm)

- [x] F3 — Decisión documentada: Psalm eliminado, PHP CS Fixer mantenido + CONTRIBUTING.md actualizado

---

## Verificación final

Ejecutar en orden:

```bash
# 1. Migraciones y seeders: 14/14 ok, 0 pending
make db-reset

# 2. Sin emojis en logs de app
docker compose logs app 2>&1 | grep -P "[\x{2705}\x{274C}\x{26A0}\x{1F4C5}\x{274B}]"
# → 0 resultados

# 3. Sin warning de world-writable en MySQL
docker compose logs db 2>&1 | grep "world-writable"
# → 0 resultados (o bien no existe dev.cnf mount = fix correcto)

# 4. Slow query logging activo (canal db)
docker compose logs app 2>&1 | grep '"channel":"db"'
# → aparece si se ejecuta alguna query lenta en dev; al menos no error al iniciar

# 5. Suite en verde
make test-unit
# → 0 errors, 0 failures

# 6. PHPStan
make phpstan
# → [OK] No errors

# 7. Scripts eliminados no referenciados
grep -r "fix_bom_views\|fix_reservation_tests\|check_pass\|ctx_check" Makefile scripts/ bin/
# → 0 resultados
```

---

## Mapa de archivos modificados

| Módulo | Archivo | Acción |
|--------|---------|--------|
| A1 | `scripts/apply-db.php` | Añadir 016-018 al array migrations |
| A2 | `app/Core/Seeders/WaitlistSeeder.php` | Fix `r.name` → `r.code` |
| A3 | `scripts/apply-db.php` | Fix prereq ReservationSeeder |
| A4 | `docker-compose.yml` | Eliminar mount dev.cnf |
| A4 | `docker-compose.override.yml` | Añadir `db.command` con flags MySQL |
| A5 | TBD tras análisis | Fix workers |
| B1 | `migrations/016_supervisor_assignments.sql` | INT → BIGINT UNSIGNED |
| B2 | `migrations/019_fix_supervisor_assignments_bigint.sql` | **Eliminar** |
| C1 | `scripts/apply-db.php` | Limpiar logMsg callsites |
| C2 | 7 seeders en `app/Core/Seeders/` | echo → Logger |
| C3 | `bin/quality-check.php` | Emojis → ASCII markers |
| D1 | `app/Repositories/AbstractRepository.php` | Añadir execTimed |
| D2 | `app/Core/Logger.php` | Activar handlers db/queue |
| D3 | `ReservationService`, `ReviewService`, `AuthService`, `LoyaltyService` | WideEvent::setSection |
| D4 | `ReservationService`, `AuthService`, `LoyaltyService`, `WaitlistService` | Logger::warning tipo B |
| D5 | `app/Workers/NotificationWorker.php`, `EmailWorker.php` | _correlation_id en re-push |
| E1-E2 | `scripts/fix_bom_views.php`, `fix_reservation_tests.php`, `check_pass.php`, `tools/ctx_check.php` | **Eliminar** |
| F1 | `docker/php/*.ini` | Revisar xdebug/session |
| F2 | `docker-compose.yml`, `Dockerfile.prod` | Fijar versiones imagen |
| F3 | `composer.json`, `Makefile`, `CONTRIBUTING.md` | Decisión tooling |
