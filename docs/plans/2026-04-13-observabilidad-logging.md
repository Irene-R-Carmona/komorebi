# Plan: Observabilidad — Logging, Trazabilidad y Monitoreo (FASE 2)

**Fecha:** 13 de abril de 2026
**Estado:** ✅ COMPLETADO — solo requiere verificación
**Dependencias:** Ninguna (independiente de FASE 0 y FASE 1)
**Rama:** no aplica — ya integrado en main

---

## Resumen de implementación

Todos los ítems de FASE 2 (corto plazo + largo plazo) han sido implementados
antes de la creación de este plan.

---

## Verificación de cada ítem

### TASK 1 — Slow Query Detection (LoggingPDO)

**Archivo:** `app/Core/Database.php` + `app/Core/LoggingPDO.php`

**Verificar:**

```bash
docker compose exec app grep -r "LoggingPDO\|DB_SLOW_QUERY_MS\|Slow query" app/Core/ --include="*.php" -l
```

**Esperado:** Al menos 2 archivos listados.

**Test:**

```bash
docker compose exec app php vendor/bin/phpunit tests/Unit/ --testdox --filter Logging
```

---

### TASK 2 — Request Body sanitizado en errores 4xx

**Archivo:** `app/Core/Middleware/RequestLogMiddleware.php`

**Verificar:**

```bash
docker compose exec app grep -n "password\|token\|cvv\|card_" app/Core/Middleware/RequestLogMiddleware.php
```

**Esperado:** Bloque de sanitización que elimina esas claves del body logueado.

---

### TASK 3 — Make targets de logs

**Archivo:** `Makefile`

**Verificar:**

```bash
grep -n "logs-errors\|logs-slow\|logs-trace\|logs-http" Makefile
```

**Esperado:** 4 targets definidos (líneas aproximadas 41-54 del Makefile).

**Probar en dev:**

```bash
make logs-errors    # docker compose logs app | jq 'select(.level=="error")'
make logs-slow      # filtra queries lentas
make logs-http      # request/response summary
make logs-trace REQUEST_ID=abc123
```

---

### TASK 4 — Health endpoint completo

**Archivo:** `public/health.php`

**Verificar estructura:**

```bash
docker compose exec app curl -s http://localhost/health | jq .
```

**Esperado:**

```json
{
  "status": "ok",
  "timestamp": "...",
  "version": "...",
  "checks": {
    "database": "ok",
    "redis": "ok",
    "disk": "ok",
    "workers": "..."
  }
}
```

---

### TASK 5 — Cache hit/miss en WideEvent

**Archivo:** `app/Core/Cache.php` + `app/Core/WideEvent.php`

**Verificar:**

```bash
docker compose exec app grep -n "hits\|misses\|cache_hits\|cache_misses" app/Core/Cache.php app/Core/WideEvent.php 2>/dev/null
```

**Esperado:** Contadores incrementados en `get()` y propagados al WideEvent al final del request.

---

### TASK 6 — Worker heartbeat

**Archivos:** `app/Workers/EmailWorker.php`, `app/Workers/NotificationWorker.php`

**Verificar:**

```bash
docker compose exec app grep -n "Heartbeat\|HEARTBEAT_INTERVAL\|emitHeartbeat" app/Workers/EmailWorker.php app/Workers/NotificationWorker.php
```

**Esperado:**

```
app/Workers/EmailWorker.php:XX:    private const int HEARTBEAT_INTERVAL = 60;
app/Workers/EmailWorker.php:XX:    private function emitHeartbeatIfDue(): void
app/Workers/NotificationWorker.php:XX:    private const int HEARTBEAT_INTERVAL = 60;
app/Workers/NotificationWorker.php:XX:    private function emitHeartbeatIfDue(): void
```

---

### TASK 7 — Actualizar índice maestro post-verificación

Una vez confirmados todos los ítems anteriores, marcar FASE 2 como completada en
`docs/plans/indice-maestro.md` (tabla de planes detallados).

---

## Notas

- **OpenTelemetry / Sentry** y **Métricas de percentiles** son largo plazo / infraestructura,
  no se implementan en esta fase de código.
- Si algún TASK falla la verificación, documentar en `docs/plans/YYYY-MM-DD-fix-observabilidad.md`.
