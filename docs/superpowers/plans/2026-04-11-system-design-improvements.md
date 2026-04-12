# Plan Maestro — System Design Improvements — Komorebi Café

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Cerrar las 8 brechas identificadas en el análisis de system design del 11/04/2026. Dividido en dos fases implementables + documentación de decisiones arquitecturales a largo plazo.

**Architecture:** Monolito PHP 8.4 custom MVC (PSR-7/PSR-15), FrankenPHP + Caddy, MySQL 8.4 + Redis 8, GitHub Actions CI. Las tareas de la Fase 1 y Fase 2 son paralelas entre sí dentro de cada fase.

**Tech Stack:** PHP 8.4, Redis 8, GitHub Actions, SonarQube self-hosted, OWASP ZAP, Caddy.

**Decisiones cerradas (11/04/2026):**

- SonarQube: self-hosted (ya corriendo), `sonar-scanner` CLI, secrets `SONAR_TOKEN` + `SONAR_HOST_URL`
- OWASP ZAP: `fail_action: exit` — bloquea PRs con vulnerabilidades HIGH
- Deploy CD: scaffold multi-opción comentado (sin servidor de producción real aún), GHCR push activo

---

## Mapa de dependencias

```
FASE 1 (Inmediatas — 4 tareas paralelas)   ← SIN dependencias, hacer primero
  ├── T1: Cache-Control headers en Caddyfile         [~30 min]
  ├── T2: SonarQube workflow en CI                   [~1h]
  ├── T3: OWASP ZAP workflow en CI                   [~1h]
  └── T4: CD scaffold deploy.yml                     [~2h]

FASE 2 (Corto plazo — 4 tareas paralelas)  ← Después de Fase 1 (o en paralelo si hay capacidad)
  ├── T5: Circuit Breaker (TelegramService + WeatherService)  [TDD: ~1 día]
  ├── T6: Full Jitter backoff en Queue::retry()               [TDD: ~2h]
  ├── T7: Cache invalidation por eventos en servicios         [~3h]
  └── T8: Correlation ID en payloads de jobs                  [~1h]

LARGO PLAZO (documentado, no implementar)
  ├── L1: Horizontal scaling (trigger: CPU >80%)
  ├── L2: MySQL read replicas (trigger: dashboard queries >200ms)
  ├── L3: Object storage S3/R2/MinIO (prerequisito para L1)
  ├── L4: OpenTelemetry (trigger: 2+ servicios independientes)
  └── L5: Extracción de módulos (KDS, API /api/v1, notification workers)
```

---

## FASE 1 — Implementación inmediata

### T1: Cache-Control headers para assets estáticos en Caddyfile

**Problema:** `frankenphp.Caddyfile` tiene bloque `:80` simple sin headers de caché. Los assets propios (`public/css`, `public/js`, `public/images`) no se cachean en el navegador.

**Solución:** Añadir matcher `@static` + directiva `header` con `Cache-Control: public, max-age=31536000, immutable`.

**Archivos:**

- `frankenphp.Caddyfile`

**Pasos:**

- [ ] Añadir matcher `@static` para extensiones `.css`, `.js`, `.woff2`, `.png`, `.jpg`, `.ico`, `.svg`, `.webp`
- [ ] Añadir `header @static Cache-Control "public, max-age=31536000, immutable"`
- [ ] Verificar con `curl -I http://localhost/css/<archivo>.css` que el header aparece

**Criterio de aceptación:** `curl -I http://localhost/css/<archivo>.css` devuelve `Cache-Control: public, max-age=31536000, immutable`.

---

### T2: SonarQube self-hosted en CI

**Problema:** `sonar-project.properties` existe y está bien configurado (`sonar.projectKey=komorebi-cafe`) pero no hay ningún step en ningún workflow de GitHub Actions que lo invoque.

**Solución:** Crear `.github/workflows/sonarqube.yml` usando la action oficial `sonarsource/sonarqube-scan-action@v3`.

**Archivos:**

- `.github/workflows/sonarqube.yml` (nuevo)

**Secrets requeridos (configurar en GitHub → Settings → Secrets):**

- `SONAR_TOKEN` — token de la instancia self-hosted
- `SONAR_HOST_URL` — URL de la instancia (ej. `http://sonarqube:9000`)

**Workflow:**

```yaml
name: SonarQube Analysis
on:
  push:
    branches: [main, develop]
  pull_request:
    types: [opened, synchronize, reopened]

jobs:
  sonarqube:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
        with:
          fetch-depth: 0   # historial completo para blame/SCM
      - name: Generate coverage report
        run: |
          docker compose -f docker-compose.test.yml up -d
          docker compose -f docker-compose.test.yml exec -T app php vendor/bin/phpunit \
            --coverage-clover=coverage.xml --log-junit=junit.xml
          docker compose -f docker-compose.test.yml down
      - uses: sonarsource/sonarqube-scan-action@v3
        env:
          SONAR_TOKEN: ${{ secrets.SONAR_TOKEN }}
          SONAR_HOST_URL: ${{ secrets.SONAR_HOST_URL }}
```

**Pasos:**

- [ ] Crear `.github/workflows/sonarqube.yml`
- [ ] Confirmar que `sonar-project.properties` tiene `sonar.php.coverage.reportPaths=coverage.xml` y `sonar.testExecutionReportPaths=junit.xml`
- [ ] Configurar secrets `SONAR_TOKEN` y `SONAR_HOST_URL` en GitHub repo settings
- [ ] Verificar que el análisis aparece en el dashboard de SonarQube tras el primer push

**Criterio de aceptación:** Nuevo análisis visible en el dashboard SonarQube self-hosted tras push a `develop`.

---

### T3: OWASP ZAP DAST en CI

**Problema:** No hay análisis dinámico de seguridad (DAST). El análisis estático (PHPStan/Psalm) no detecta XSS, CSRF bypass, injection en runtime ni headers de seguridad faltantes.

**Solución:** Crear `.github/workflows/security-zap.yml` con dos modos:

- PRs: baseline scan + `fail_action: exit` (bloquea si HIGH encontrado)
- Domingos 2am: full scan + SARIF → GitHub Security tab

**Archivos:**

- `.github/workflows/security-zap.yml` (nuevo)
- `.zap/rules.tsv` (nuevo — excepciones de falsos positivos)

**Pasos:**

- [ ] Crear `.github/workflows/security-zap.yml`
- [ ] Crear `.zap/rules.tsv` vacío con comentario de cabecera para futuras exclusiones
- [ ] Verificar que `docker-compose.test.yml` expone la app en un puerto accesible para ZAP
- [ ] Primer run: revisar alertas y añadir exclusiones legítimas a `.zap/rules.tsv`
- [ ] Confirmar que SARIF se carga en GitHub Security tab (requiere `security-events: write` en permisos)

**Criterio de aceptación:** Workflow ejecuta sin errores de configuración; SARIF aparece en GitHub → Security → Code scanning.

---

### T4: CD scaffold — deploy.yml

**Problema:** No existe ningún workflow de Continuous Delivery. Las builds se verifican pero nunca se despliegan automáticamente.

**Solución:** Crear `.github/workflows/deploy.yml` con:

- Bloque activo: push imagen a GHCR (`ghcr.io/{owner}/{repo}`)
- Bloque comentado Variante A: deploy VPS via SSH (`appleboy/ssh-action`)
- Bloque comentado Variante B: deploy fly.io (`flyctl deploy`)
- Notificación Telegram al canal admin de resultado del deploy

**Archivos:**

- `.github/workflows/deploy.yml` (nuevo)

**Secrets requeridos (para activar en el futuro):**

- Variante A: `SSH_HOST`, `SSH_USER`, `SSH_KEY`
- Variante B: `FLY_API_TOKEN`, `FLY_APP_NAME`
- Notificación: `TELEGRAM_BOT_TOKEN`, `TELEGRAM_CHAT_ID`

**Pasos:**

- [ ] Crear `.github/workflows/deploy.yml` con trigger `on: push: branches: [main]`
- [ ] Añadir step activo de login a GHCR y push de imagen Docker
- [ ] Añadir Variante A (VPS SSH) como bloque comentado con instrucciones
- [ ] Añadir Variante B (fly.io) como bloque comentado con instrucciones
- [ ] Añadir step de notificación Telegram (éxito/fallo) como bloque comentado
- [ ] Verificar que la imagen se sube correctamente a GHCR tras merge a `main`

**Criterio de aceptación:** Imagen visible en `ghcr.io/{owner}/komorebi-cafe:latest` tras merge a `main`.

---

## FASE 2 — Corto plazo

### T5: Circuit Breaker para servicios externos

**Problema:** `TelegramService::sendMessage()` usa `file_get_contents` con `timeout=5s`. `WeatherService::fetchApi()` usa `curl_exec` con `timeout=10s`. Si el servicio externo está caído, **cada request gasta 5-10s** en timeout, creando un cascade failure.

**Solución:** Implementar `app/Core/CircuitBreaker.php` — clase final, 3 estados (CLOSED/OPEN/HALF_OPEN), estado persistido en Redis, integrada en ambos servicios.

**Archivos:**

- `app/Core/CircuitBreaker.php` (nuevo)
- `tests/Unit/Core/CircuitBreakerTest.php` (nuevo — TDD primero)
- `app/Services/TelegramService.php` (modificar)
- `app/Services/WeatherService.php` (modificar)

**Parámetros de configuración:**

```php
FAILURE_THRESHOLD = 5   // fallos consecutivos para abrir el circuito
WINDOW_SECONDS = 60     // ventana de tiempo para contar fallos
TIMEOUT_SECONDS = 120   // tiempo OPEN antes de pasar a HALF_OPEN
```

**Redis keys:**

- `circuit:{name}:state` → `CLOSED|OPEN|HALF_OPEN`
- `circuit:{name}:failures` → contador (con TTL = WINDOW_SECONDS)
- `circuit:{name}:opened_at` → timestamp de apertura

**TDD — tests a escribir primero:**

```
CircuitBreakerTest::testStartsClosed()
CircuitBreakerTest::testOpensAfterThreshold()
CircuitBreakerTest::testHalfOpenAfterTimeout()
CircuitBreakerTest::testResetsOnSuccess()
CircuitBreakerTest::testThrowsWhenOpen()
```

**Pasos:**

- [ ] Escribir `tests/Unit/Core/CircuitBreakerTest.php` con los 5 tests (TDD)
- [ ] Implementar `app/Core/CircuitBreaker.php` hasta que pasen los tests
- [ ] Integrar en `TelegramService::sendMessage()` — envolver llamada HTTP
- [ ] Integrar en `WeatherService::fetchApi()` — envolver llamada curl
- [ ] Verificar con `redis-cli GET circuit:telegram:state` tras forzar fallos
- [ ] `make test-unit` verde

**Criterio de aceptación:** `redis-cli GET circuit:telegram:state` devuelve `OPEN` tras 5 fallos consecutivos simulados; `make test-unit` verde.

---

### T6: Full Jitter backoff en Queue::retry()

**Problema:** `Queue::retry()` ya tiene `$delay = 2 ** $attempts` (exponential backoff) pero **sin jitter**. Bajo carga, múltiples workers reintentarán exactamente al mismo tiempo (thundering herd). `maxAttempts=3` es demasiado bajo para intermitencias transitorias.

**Solución:**

1. Cambiar `$delay = 2 ** $attempts` → `$delay = random_int(0, min(300, 2 ** $attempts))`
2. Elevar `maxAttempts` de 3 → 10 en `Queue::retry()` y en `bin/worker.php`

**Archivos:**

- `app/Core/Queue.php` (modificar — 1 línea en `retry()`)
- `bin/worker.php` (modificar — actualizar llamada a `Queue::retry()`)
- `bin/email-worker.php` (modificar — actualizar si usa `maxAttempts` explícito)
- `tests/Unit/Core/QueueRetryTest.php` (nuevo — TDD primero)

**TDD — tests a escribir primero:**

```
QueueRetryTest::testRetryDelayIsWithinJitterBounds()
QueueRetryTest::testRetryDelayNeverExceedsCap()
QueueRetryTest::testJobSentToFailedAfterMaxAttempts()
```

**Pasos:**

- [ ] Escribir `tests/Unit/Core/QueueRetryTest.php` con los 3 tests (TDD)
- [ ] Modificar `app/Core/Queue.php` línea `$delay = 2 ** $attempts` → con jitter
- [ ] Actualizar `maxAttempts` en los workers y en `Queue::retry()` default
- [ ] `make test-unit` verde

**Criterio de aceptación:** Tests verdes; delays son distribución uniforme entre 0 y 2^n (capped 300s); job va a `queue:failed` tras 10 intentos.

---

### T7: Cache invalidation por eventos en servicios de escritura

**Problema:** `app/Core/Cache.php` solo usa TTL como invalidación. Después de un write en admin (actualizar café, menú, horario), el caché puede servir datos obsoletos hasta 1h.

**Solución:** Añadir `Cache::delete(...)` explícito en los métodos `update`/`delete` de los servicios que cachean.

**Servicios a revisar (leer primero para mapear qué cachean):**

- `app/Services/CafeService.php` — keys: `cafe:{slug}`, `cafe:id:{id}`, `cafes:active`
- `app/Services/MenuService.php` (si existe) — keys: `menu:{cafeId}`
- `app/Services/TimeSlotService.php` — keys: `slots:{cafeId}:{date}`
- `app/Services/ReservationService.php` — keys relacionadas con disponibilidad

**Archivos:** Los servicios identificados en el paso de lectura.

**Pasos:**

- [ ] Leer cada servicio para identificar exactamente qué keys cachean y con qué TTL
- [ ] Añadir `Cache::delete("clave")` en los métodos `update()` y `delete()` de cada servicio
- [ ] Si el servicio no tiene tests, escribir el test de invalidación primero (TDD)
- [ ] `make test-unit` verde

**Criterio de aceptación:** Tras `CafeService::update($id, $data)`, una llamada a `Cache::get("cafe:id:{$id}")` devuelve `null` (no el valor anterior).

---

### T8: Propagación de Correlation ID a payloads de jobs

**Problema:** Los logs de workers no tienen el `request_id` del HTTP request que originó el job. Correlacionar logs de HTTP + worker requiere buscar manualmente.

**Solución:** Al hacer `Queue::push()` en el request HTTP, incluir el `request_id` actual en el payload. En `bin/worker.php`, leer ese ID y establecerlo en `WideEvent` para que todos los logs del job lo incluyan.

**Archivos:**

- `app/Core/Queue.php` — revisar si `push()` acepta payload libre (sí, es `array`)
- Todos los `Queue::push(...)` en services/controllers del path HTTP (buscar con grep)
- `bin/worker.php` (modificar — leer `_correlation_id` del payload)
- `bin/email-worker.php` (modificar — mismo patrón)

**Convención de clave:** `_correlation_id` (prefijo `_` indica metadata de infraestructura, no payload de negocio)

**Pasos:**

- [ ] Grep `Queue::push(` en `app/` para encontrar todos los call sites
- [ ] En cada call site dentro del path HTTP, añadir `'_correlation_id' => WideEvent::get('request_id') ?? ''`
- [ ] En `bin/worker.php`, después de `$jobData = Queue::pop(...)`, añadir `WideEvent::set('request_id', $jobData['_correlation_id'] ?? '')`
- [ ] Verificar en logs de worker que `request_id` aparece en los wide events

**Criterio de aceptación:** Log de worker durante test de integración muestra el mismo `request_id` que el log HTTP del request que originó el job.

---

## LARGO PLAZO — Decisiones arquitecturales documentadas

> Estas decisiones NO se implementan ahora. Se documentan para que no se tomen decisiones reactivas sin contexto.

### L1: Escalado horizontal de la app

**Trigger:** CPU app container sostenida >80% durante >10 min, o latencia P95 >500ms.

**Prerequisito:** L3 (object storage) debe estar completo — los uploads no pueden ir a disco local con múltiples instancias.

**Acción cuando el trigger se cumpla:**

- Docker Compose: añadir `replicas: 3` en servicio `app`
- Añadir Caddy como load balancer externo con `upstream` roundrobin
- Sesiones: ya en Redis → no requiere cambios
- Queues: ya en Redis → workers escalan independientemente

---

### L2: MySQL read replicas para reporting

**Trigger:** Queries de dashboards (backoffice, KDS, keeper) con latencia >200ms medida en logs.

**Acción cuando el trigger se cumpla:**

- Añadir `DATABASE_READ_URL` en config
- `Database::getReadConnection()` para queries SELECT en `AbstractRepository::findAll()` y métodos de reporting
- No requiere cambio de esquema; réplica sincronizada vía MySQL replication nativa

---

### L3: Object storage para uploads

**Prerequisito para:** L1 (horizontal scaling).

**Trigger:** Decisión de escalar horizontalmente, o antes si hay riesgo de pérdida de uploads en redeployments.

**Acción cuando el trigger se cumpla:**

- Elegir: AWS S3 / Cloudflare R2 (económico, sin egress) / MinIO self-hosted
- `app/Services/StorageService.php` con interfaz `StorageInterface` → SwapDriver fácil
- Mover `storage/uploads/` → bucket externo
- Actualizar URLs en vistas para apuntar al bucket

---

### L4: OpenTelemetry (trazas distribuidas)

**Trigger:** ≥2 servicios independientes (cuando KDS o API /api/v1 se separen), o cuando la correlación de logs se vuelva insuficiente para debugging.

**Acción cuando el trigger se cumpla:**

- `open-telemetry/opentelemetry-php` SDK
- Exportar traces a Jaeger (self-hosted) o Honeycomb (SaaS)
- El Correlation ID (T8) ya sienta la base — `_correlation_id` = TraceID
- W3C `traceparent` header para propagación HTTP

---

### L5: Extracción de módulos (Strangler Fig)

**Candidatos por orden de separabilidad:**

| Candidato | Por qué separable | Trigger |
|-----------|-------------------|---------|
| API `/api/v1` | Tráfico de terceros + autenticación por token diferente a sesiones | >100k req/día desde terceros |
| Notification workers | Ya físicamente separados en Docker Compose | Queue >10k jobs/día sostenido |
| KDS (Kitchen Display) | Requeriría WebSockets real-time — fit diferente | Requisito real-time explícito |

**NUNCA separar:** El dominio transaccional central (reservations ↔ cafes ↔ zones ↔ users ↔ loyalty ↔ shifts). Todo está acoplado por diseño de negocio — separarlos requeriría sagas distribuidas sin ninguna ventaja.

---

## Design Patterns aplicados

| Patrón | Dónde | Tarea |
|--------|-------|-------|
| Circuit Breaker | `app/Core/CircuitBreaker.php` | T5 |
| Full Jitter Backoff | `app/Core/Queue.php::retry()` | T6 |
| Cache-Aside (invalidación explícita) | Servicios de escritura | T7 |
| Dead Letter Queue | `queue:failed` (ya existe) | — |
| Baggage Propagation | Payload jobs ↔ WideEvent | T8 |
| Quality Gate (SAST) | `.github/workflows/sonarqube.yml` | T2 |
| DAST | `.github/workflows/security-zap.yml` | T3 |
| Delivery Pipeline | `.github/workflows/deploy.yml` | T4 |
| Strangler Fig | Extracción futura de módulos | L5 |

---

## Criterios de aceptación por fase

### Fase 1 completa cuando

- [ ] `curl -I http://localhost/css/*.css` devuelve `Cache-Control: public, max-age=31536000, immutable`
- [ ] Análisis SonarQube visible en dashboard self-hosted tras push a `develop`
- [ ] ZAP workflow ejecuta sin errores de configuración; SARIF en GitHub Security tab
- [ ] Imagen Docker visible en `ghcr.io/{owner}/komorebi-cafe:latest` tras merge a `main`

### Fase 2 completa cuando

- [ ] `redis-cli GET circuit:telegram:state` devuelve `OPEN` tras 5 fallos simulados; `make test-unit` verde
- [ ] Delays de retry son distribución uniforme [0, 2^n]; job llega a `queue:failed` tras 10 intentos
- [ ] `Cache::get("cafe:id:{$id}")` devuelve `null` inmediatamente después de `CafeService::update()`
- [ ] Logs de worker muestran el mismo `request_id` que el request HTTP que originó el job

---

*Plan creado el 11/04/2026. Basado en análisis de system design de la sesión del mismo día.*
