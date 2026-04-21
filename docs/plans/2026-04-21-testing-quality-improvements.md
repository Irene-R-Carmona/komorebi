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

## FASE 1 — Eliminar los 70 warnings (Estimado: 15 min)

### 1.1 CorsMiddlewareTest

- [ ] Abrir `tests/Unit/Middleware/CorsMiddlewareTest.php`
- [ ] Reemplazar `use PHPUnit\Framework\Attributes\CoversClass;` + `#[CoversClass(CorsMiddleware::class)]` por `use PHPUnit\Framework\Attributes\CoversNothing;` + `#[CoversNothing]`
- [ ] Ejecutar: `docker compose exec app sh -c "php vendor/bin/phpunit --no-coverage tests/Unit/Middleware/CorsMiddlewareTest.php 2>&1 | tail -5"`
- [ ] Verificar: 0 warnings, mismo número de tests

### 1.2 EmailWorkerTest

- [ ] Abrir `tests/Unit/Workers/EmailWorkerTest.php`
- [ ] Mismo cambio: `CoversClass` → `CoversNothing`
- [ ] Ejecutar test individual y verificar 0 warnings

### 1.3 NotificationWorkerTest

- [ ] Abrir `tests/Unit/Workers/NotificationWorkerTest.php`
- [ ] Mismo cambio: `CoversClass` → `CoversNothing`

### 1.4 Verificación Fase 1

- [ ] `docker compose exec app sh -c "php vendor/bin/phpunit --no-coverage --testsuite 'Unit Tests' 2>&1 | tail -5"`
- [ ] Esperado: `OK (744 tests, ...)` sin `issues` — los 70 warnings deben desaparecer

---

## FASE 2 — Diagnosticar y resolver los 192 notices (Estimado: 30 min)

### 2.1 Diagnóstico exacto

- [ ] Ejecutar: `docker compose exec app sh -c "php vendor/bin/phpunit --no-coverage --testsuite 'Unit Tests' 2>&1 | grep 'Notice' | sort | uniq -c | sort -rn | head -20"`
- [ ] Identificar qué clases/tests generan más notices
- [ ] Si son "no code coverage annotation": añadir `#[CoversNothing]` en los tests sin atributo de cobertura que apuntan a clases fuera del scope de coverage (controllers, middleware excluido, etc.)

### 2.2 Opción A — Silenciar masivamente

Si los notices son todos del mismo tipo ("test does not cover any code"), añadir en `phpunit.xml`:

```xml
<phpunit ... beStrictAboutCoverageMetadata="false">
```

> Nota: ya está en `false`. Si los notices persisten, revisar si PHPUnit 13 cambió el comportamiento.

### 2.3 Opción B — Fix uno a uno

Para cada archivo de test que genere notices:

- [ ] Añadir `#[CoversNothing]` si el test no debería cubrir nada del source
- [ ] O añadir el `#[CoversClass(...)]` correcto si cubre una clase dentro del scope

### 2.4 Verificación Fase 2

- [ ] `docker compose exec app sh -c "php vendor/bin/phpunit --no-coverage --testsuite 'Unit Tests' 2>&1 | tail -3"`
- [ ] Esperado: `OK (744 tests, ...)` sin `issues` ni `notices`

---

## FASE 3 — PHPStan no pierde output (Estimado: 15 min)

### 3.1 Mejorar target `phpstan` en Makefile

```makefile
phpstan: ## Análisis estático PHPStan (guarda en storage/logs/phpstan.txt)
    @mkdir -p storage/logs
    docker compose exec app sh -c "cd /app && php vendor/bin/phpstan analyse --memory-limit=1G --no-progress > /tmp/phpstan.txt 2>&1; EXIT=\$$?; cat /tmp/phpstan.txt; exit \$$EXIT"
    @echo "Output guardado en storage/logs/phpstan.txt"

phpstan-quick: ## PHPStan solo sobre app/ (sin scripts/ ni tests/) — más rápido
    docker compose exec app sh -c "cd /app && php vendor/bin/phpstan analyse app/ --memory-limit=512M --no-progress 2>&1"
```

- [ ] Editar `Makefile` con estos dos targets
- [ ] Añadir `phpstan phpstan-quick` a la línea `.PHONY`
- [ ] Probar: `make phpstan-quick` — debe completar sin perder output

### 3.2 CI — ya guarda output

El workflow `.github/workflows/ci.yml` ya usa `make phpstan` dentro del job — el log de GitHub Actions lo retiene. No requiere cambio de CI.

### 3.3 Verificación Fase 3

- [ ] `make phpstan-quick` completa y muestra resultados
- [ ] `make phpstan` guarda output en `/tmp/phpstan.txt` dentro del contenedor

---

## FASE 4 — Slow tests visibility (Estimado: 10 min)

### 4.1 Añadir threshold en phpunit.xml

Dentro de `<phpunit ...>`, añadir después de `</coverage>`:

```xml
<!-- Slow test detection: loggear tests que tarden más de 500ms -->
<slowTests>
    <threshold seconds="0.5"/>
</slowTests>
```

- [ ] Editar `phpunit.xml`
- [ ] Ejecutar `docker compose exec app sh -c "php vendor/bin/phpunit --no-coverage tests/Unit/Services/WaitlistServiceTest.php 2>&1 | tail -10"` — WaitlistService tarda ~300ms, no debería aparecer
- [ ] Verificar que no rompió nada

---

## Notas de decisión

### ¿Añadir pcov al dev container?

Requiere modificar `Dockerfile` y reconstruir imagen. La recomendación es **no hacerlo** salvo que la lentitud de coverage local sea un bloqueante diario. El workflow correcto es:

1. TDD local: `docker compose exec app php vendor/bin/phpunit --no-coverage tests/Unit/Services/MiServicioTest.php` → ~0.3s
2. Coverage completo: `make test-coverage` (usa pcov via docker-compose.test.yml) → ~5-8 min

### ¿Por qué `make test-unit` usa paratest?

`paratest --processes=4` distribuye los tests en 4 workers paralelos. Requiere que los tests sean independientes (sin estado compartido entre fixtures). Los tests actuales lo son. Tiempo esperado con paratest: ~4s para 744 tests unitarios.
