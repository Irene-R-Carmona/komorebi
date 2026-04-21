# Testing Quality Improvements — Komorebi Café

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Eliminar los 70 warnings + 192 notices del output de PHPUnit, hacer que PHPStan no pierda output, y habilitar coverage rápido localmente sin xdebug.

**Architecture:** Ajustes puntuales en phpunit.xml, Makefile y 3 archivos de test; sin cambios en código de producción.

**Tech Stack:** PHPUnit 13, PHPStan 2, PHP CS Fixer, Docker, pcov, xdebug.

---

## Diagnóstico documentado (21-04-2026)

### Problema 1 — 70 PHPUnit Warnings

`"Class X is not a valid target for code coverage"` — los tests de Workers y CorsMiddleware tienen `#[CoversClass]` apuntando a clases excluidas del `<source>` en `phpunit.xml`. Causa: `failOnWarning="true"` hace que la suite reporte `issues` en lugar de `OK`.

Archivos afectados:

- `tests/Unit/Middleware/CorsMiddlewareTest.php` — 66 warnings (`App\Http\Middleware\CorsMiddleware`)
- `tests/Unit/Workers/EmailWorkerTest.php` — 2 warnings (`App\Workers\EmailWorker`)
- `tests/Unit/Workers/NotificationWorkerTest.php` — 2 warnings (`App\Workers\NotificationWorker`)

**Fix**: reemplazar `#[CoversClass(...)]` por `#[CoversNothing]` en los 3 archivos.

### Problema 2 — 192 PHPUnit Notices

Causa probable: tests sin ningún atributo de cobertura (`#[CoversClass]`, `#[CoversNothing]`) en clases que el `<source>` sí incluye. PHPUnit 13 emite un Notice por cada método de test en esa situación.

**Acción**: ejecutar `phpunit --verbose` y capturar los mensajes exactos, luego añadir `#[CoversNothing]` en los archivos sin atributo o añadir `requireCoverageMetadata="true"` en phpunit.xml para convertirlos en error visible.

### Problema 3 — PHPStan pierde output

`make phpstan` hace: `docker compose exec app php vendor/bin/phpstan analyse --memory-limit=1G`
Sin `--no-progress`, sin guardar a fichero. Si el terminal tiene timeout (herramientas de agente, CI local), el output se pierde. PHPStan tarda ~1-2 min localmente.

**Fix**: guardar siempre a `storage/logs/phpstan.txt` + mostrar tail al final + añadir target `phpstan-quick` (solo `app/` sin `scripts/` ni `tests/`).

### Problema 4 — Coverage local lento (xdebug vs pcov)

El dev container tiene xdebug. `XDEBUG_MODE=coverage phpunit` genera HTML solo de Services en ~1:49 min. El `docker-compose.test.yml` usa imagen con pcov (3-5x más rápido). El Makefile no expone un target rápido que use pcov localmente sin reconstruir todo.

**Fix**: añadir `make test-unit-fast` que ejecute con `--no-coverage` para TDD rápido + documentar el workflow de cobertura correcto (usar `make test-coverage` para el reporte real).

### Problema 5 — Sin detección de tests lentos

`phpunit.xml` no configura `slowTests`. No hay visibilidad de qué tests individuales superan X ms. Un test con `sleep()` accidental o una query real en un test unitario es invisible.

**Fix**: añadir configuración `<slowTests>` en `phpunit.xml`.

---

## Mapa de archivos

| Archivo | Cambio |
|---------|--------|
| `tests/Unit/Middleware/CorsMiddlewareTest.php` | `#[CoversClass]` → `#[CoversNothing]` |
| `tests/Unit/Workers/EmailWorkerTest.php` | `#[CoversClass]` → `#[CoversNothing]` |
| `tests/Unit/Workers/NotificationWorkerTest.php` | `#[CoversClass]` → `#[CoversNothing]` |
| `phpunit.xml` | Añadir `<slowTests>` threshold |
| `Makefile` | Mejorar `phpstan`, añadir `phpstan-quick`, `test-unit-fast` |

---

## FASE 1 — Eliminar los 70 warnings (Estimado: 15 min) — ✅ COMPLETADA

### 1.1 CorsMiddlewareTest

- [x] Abrir `tests/Unit/Middleware/CorsMiddlewareTest.php`
- [x] Reemplazar `use PHPUnit\Framework\Attributes\CoversClass;` + `#[CoversClass(CorsMiddleware::class)]` por `use PHPUnit\Framework\Attributes\CoversNothing;` + `#[CoversNothing]`
- [x] Verificado: 0 warnings, mismo número de tests

### 1.2 EmailWorkerTest

- [x] Abrir `tests/Unit/Workers/EmailWorkerTest.php`
- [x] Mismo cambio: `CoversClass` → `CoversNothing`
- [x] Verificado: 0 warnings

### 1.3 NotificationWorkerTest

- [x] Abrir `tests/Unit/Workers/NotificationWorkerTest.php`
- [x] Mismo cambio: `CoversClass` → `CoversNothing`

### 1.4 Fix adicional — phpunit.xml source misconfiguration

> **Fix complementario (sesión 2026-04-21):** Además del CoversNothing, se identificaron 5 causas adicionales de warnings en la sección `<source>` de `phpunit.xml`:
> - `app/Http/Middleware` no estaba en `<include>` (13 archivos de middleware)
> - `app/Events` y `app/Listeners` estaban en include Y exclude simultáneamente (net effect: excluidos)
> - `app/Jobs/SendTelegramNotificationJob.php` excluido explícitamente con `#[CoversClass]` activo
> - `app/Core/Seeders` excluido con `#[CoversClass(RbacSeeder::class)]` activo
>
> Corrección: `app/Http/Middleware` añadido a `<include>`; eliminados los 4 excludes incorrectos.

- [x] Verificación suite completa: `{"result":"passed","tests":890,"passed":890}` — `failOnWarning="true"` confirma 0 warnings

---

## FASE 2 — Diagnosticar y resolver los 192 notices (Estimado: 30 min) — ✅ COMPLETADA

> **Causa real identificada (sesión 2026-04-21):** Los notices no eran por tests sin `#[CoversClass]` sino por `createMock()` usado sin `expects()` — PHPUnit 13 emite "No expectations configured" notice por cada uso. Fix: `createMock()` → `createStub()` en los 5 archivos afectados.

Archivos corregidos (createMock → createStub):
- `tests/Unit/Http/Middleware/HttpRateLimitMiddlewareTest.php` — 4 llamadas
- `tests/Unit/Jobs/SendTelegramNotificationJobTest.php` — 1 llamada
- `tests/Unit/Middleware/ApiAuthMiddlewareTest.php` — 2 llamadas
- `tests/Unit/Repositories/ProductRepositoryTest.php` — stray `$this->stmtMock` → local `$stmtMock`
- `tests/Unit/Repositories/ProductRepositoryStockTest.php` — 4 tests con override createMock para expectations

### 2.1 Diagnóstico exacto — ✅ COMPLETADO

- [x] Causa identificada: `createMock()` sin `expects()` genera notices en PHPUnit 13
- [x] Archivos afectados identificados y corregidos

### 2.2 / 2.3 — ✅ COMPLETADO vía fix correcto (createMock→createStub)

### 2.4 Verificación Fase 2 — ✅ COMPLETADA

- [x] Suite completa: 890 tests, 890 passed, 0 notices. `failOnWarning="true"` — resultado `passed` garantiza 0 warnings y 0 notices

---

## FASE 3 — PHPStan no pierde output (Estimado: 15 min) — ✅ COMPLETADA

### 3.1 Mejorar target `phpstan` en Makefile

- [x] Editar `Makefile` con targets `phpstan` (guarda en /tmp/phpstan.txt) y `phpstan-quick`
- [x] Añadir `phpstan phpstan-quick` a la línea `.PHONY`
- [x] Fix `phpstan-quick`: añadido `--allow-unmatched-ignores` (los 7 `ignoreErrors` para `tests/**` no coinciden cuando solo se analiza `app/` → PHPStan reporta falsos positivos con `reportUnmatchedIgnoredErrors: true`)
- [x] Fix adicional: PHPStan completo detectó 5 errores reales en tests — propiedades declaradas como `&MockObject` pero asignadas con `createStub()` (que retorna `&Stub`). Corregidos en 3 archivos:
  - `tests/Unit/Http/Controllers/Supervisor/SupervisorControllerTest.php` — `@var` annotations líneas 38-40
  - `tests/Unit/Services/NewsletterServiceTest.php` — `@var` annotation línea 41
  - `tests/Unit/Services/ReviewServiceTest.php` — intersection types + import `Stub`

### 3.2 CI — ya guarda output

El workflow `.github/workflows/ci.yml` ya usa `make phpstan` dentro del job — el log de GitHub Actions lo retiene. No requiere cambio de CI.

### 3.3 Verificación Fase 3

- [x] `make phpstan-quick` completa y muestra resultados (con `--allow-unmatched-ignores`)
- [x] `make phpstan` (full: app + scripts + tests): `[OK] No errors`

---

## FASE 4 — Slow tests visibility — ❌ NO APLICABLE en PHPUnit 13

> **`<slowTests>` NO existe en PHPUnit 13.** El esquema XML de PHPUnit 13 no incluye este elemento.
> El plan original estaba basado en información incorrecta. El checkbox `[x]` era falso positivo.
>
> **Alternativas correctas en PHPUnit 13:**
> 1. **`enforceTimeLimit="true"`** en `<phpunit>` + atributos `#[Small]` / `#[Medium]` / `#[Large]` en los tests
>    — los tests que superen el límite **fallan** (no es un informe, es un timeout disruptivo)
> 2. Extensión `johnkary/phpunit-speedtrap` — detecta tests lentos sin hacerlos fallar
>
> **Decisión:** No se implementa en este sprint. El workflow actual (`--no-coverage` para TDD rápido +
> `make test-coverage` para cobertura real) cubre la necesidad de visibilidad de rendimiento.

- [x] FASE 4 cerrada como N/A — feature no existe en PHPUnit 13

---

## Notas de decisión

### ¿Añadir pcov al dev container?

Requiere modificar `Dockerfile` y reconstruir imagen. La recomendación es **no hacerlo** salvo que la lentitud de coverage local sea un bloqueante diario. El workflow correcto es:

1. TDD local: `docker compose exec app php vendor/bin/phpunit --no-coverage tests/Unit/Services/MiServicioTest.php` → ~0.3s
2. Coverage completo: `make test-coverage` (usa pcov via docker-compose.test.yml) → ~5-8 min

### ¿Por qué `make test-unit` usa paratest?

`paratest --processes=4` distribuye los tests en 4 workers paralelos. Requiere que los tests sean independientes (sin estado compartido entre fixtures). Los tests actuales lo son. Tiempo esperado con paratest: ~4s para 744 tests unitarios.
