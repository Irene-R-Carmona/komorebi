# Railway Readiness — Plan de Implementación por Prioridad

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Dejar Komorebi listo para desplegar en Railway sin fallos de arranque, sin pérdida de sesiones/archivos y con configuración operativa reproducible.

**Architecture:** Ajustes mínimos y seguros en configuración/env, bootstrap de base de datos, unificación Redis, y contrato de despliegue Railway con Dockerfile de producción + healthcheck + volumen persistente.

**Tech Stack:** PHP 8.4, FrankenPHP, Docker Compose, Railway (MySQL + Redis + Volume), PHPMailer, Supervisor.

---

## Estado del plan

| Fase | Descripción | Estado |
|------|-------------|--------|
| P0 | Bloqueantes de arranque y persistencia | ✅ Implementación completa |
| P1 | Seguridad previa a tráfico real | - [ ] |
| P2 | Hardening funcional de negocio | - [ ] |
| P3 | Observabilidad y deuda técnica | - [ ] |
| P4 | Optimización de rendimiento | - [ ] |

---

## P0 — Bloqueantes (ejecutar primero)

### R1 — Corregir bootstrap de BD: env() inexistente + soporte URL de Railway

**Archivos:**

- [x] Modificar: `config/database.php`

**Checklist:**

- [x] Reemplazar `env('...')` por `Env::get('...')`.
- [x] Soportar `MYSQL_URL` y `DATABASE_URL` con `parse_url()` como origen primario.
- [x] Mantener `DB_*` como fallback para entorno local.
- [x] Verificar que no queden llamadas a `env()` en ese archivo.

### R2 — Eliminar defaults localhost en APP_URL (emails)

**Archivos:**

- [x] Modificar: `app/Jobs/WaitlistPromotionJob.php`
- [x] Modificar: `app/Jobs/RewardUnlockedJob.php`
- [n/a] Modificar: `app/Services/EmailVerificationService.php` — ya usaba `Env::require()`
- [n/a] Modificar: `app/Services/PasswordResetService.php` — ya usaba `Env::require()`
- [x] Modificar: `app/Services/NewsletterService.php`
- [x] Modificar: `app/Core/Config.php`

**Checklist:**

- [x] Quitar defaults `http://localhost` / `http://localhost:8080` en APP_URL.
- [x] Usar `Env::require('APP_URL')` para fail-fast en Jobs y NewsletterService.
- [x] Confirmar que todos los links de email se construyen desde APP_URL real.

### R3 — Unificar defaults de REDIS_HOST

**Archivos:**

- [x] Modificar: `app/Core/Cache.php`
- [x] Modificar: `app/Core/Config.php`
- [x] Modificar: `app/Services/CacheService.php`

**Checklist:**

- [x] Unificar default de REDIS_HOST a `localhost`.
- [x] Confirmar coherencia entre cache de framework y cache de servicios.

### R4 — Documentar drivers obligatorios para producción

**Archivos:**

- [x] Modificar: `.env.example`

**Checklist:**

- [x] Añadir `SESSION_DRIVER=redis`.
- [x] Añadir `CACHE_DRIVER=redis`.
- [x] Añadir comentario de obligatoriedad en Railway.

### R5 — Crear contrato de despliegue Railway

**Archivos:**

- [x] Crear: `railway.toml`

**Checklist:**

- [x] `build.dockerfilePath = "docker/php/Dockerfile.prod"`.
- [x] Healthcheck `"/health.php"`.
- [x] `restartPolicyType = "ON_FAILURE"`.
- [x] Declarar mount persistente para `/app/storage`.

### R6 — Completar matriz de variables de entorno

**Archivos:**

- [x] Modificar: `.env.example`

**Checklist:**

- [x] Verificar presencia/documentación de: `APP_ENV`, `APP_URL`, `APP_KEY`.
- [x] Añadir `DATABASE_URL` (Railway primary connection string).
- [x] Verificar presencia/documentación de: `REDIS_*`.
- [x] Verificar presencia/documentación de: `MAIL_*`.
- [x] Verificar presencia/documentación de: `FEATURE_*`.

### R7 — Homogeneizar acceso a entorno en healthcheck

**Archivos:**

- [x] Modificar: `public/health.php`

**Checklist:**

- [x] Reemplazar `getenv()` por `Env::get()`.
- [x] Eliminar bloque duplicado de `disk_free_space()`.
- [x] Mantener salida JSON y códigos HTTP actuales.

### R8 — Resolver arranque de workers

**Resolución:** No es un bug. Workers arrancan con `make workers-up` o `make dev-full` (profiles Docker Compose).
`docker/supervisor.conf` es artefacto huérfano — no afecta al flujo de despliegue.

- [x] Confirmado: by-design, no requiere cambios.

### R9 — Asegurar Xdebug fuera de producción

**Archivos:**

- [x] Modificar: `docker/php/Dockerfile.prod`

**Checklist:**

- [x] `ENV XDEBUG_MODE=off` añadido como declaración defensiva.
- [x] Extensión Xdebug no instalada en Dockerfile.prod (confirmado).

### R10 — Fijar versiones Docker para builds reproducibles

**Resolución:** Ya estaba correcto. Imágenes `mysql:8.4`, `redis:8-alpine`, `dunglas/frankenphp:1.12.2-php8.4-alpine` — sin tags `latest`.

- [x] Confirmado: no requiere cambios.

---

## P1 — Seguridad previa a tráfico real

**Plan fuente:** `docs/plans/2026-04-17-business-rules-hardening.md` (Sprint 0, 1 y 2)

- [ ] Completar S0-03 y S0-05.
- [ ] Completar S1-01 a S1-06.
- [ ] Completar S2-01 a S2-07.

---

## P2 — Hardening funcional (post-lanzamiento inicial)

**Plan fuente:** `docs/plans/2026-04-17-business-rules-hardening.md` (Sprint 3, 4 y 5)

- [ ] Completar reglas de dominio Sprint 3.
- [ ] Completar mejoras arquitectónicas Sprint 4.
- [ ] Completar limpieza P3 Sprint 5.
- [ ] Refactorizar `RewardUnlockedJob` para delegar envío en `SendEmailJob`.

---

## P3 — Observabilidad y deuda técnica

**Plan fuente:** `docs/plans/2026-04-15-infra-calidad-integral.md` (módulos C, D, E)

- [ ] Completar C1-C3 (higiene de logging scripts).
- [ ] Completar D1-D5 (trazabilidad por capas).
- [ ] Completar E1-E2 (limpieza scripts one-shot).
- [ ] Encapsular Telegram con feature flag dedicado.

---

## P4 — Optimización de rendimiento

**Plan fuente:** `docs/plans/2026-04-15-frankenphp-stack-optimization.md`

- [ ] Ejecutar Fase 5 (Worker Mode) cuando P0-P2 estén cerradas.
- [ ] Ejecutar resto de fases de optimización por impacto/coste.

---

## Validación mínima antes de cerrar P0

- [ ] `make dev` levanta app + db + redis + workers sin errores.
- [ ] `make db-migrate` completa sin fallos.
- [ ] Healthcheck `/health.php` responde OK.
- [ ] Login mantiene sesión entre reinicios de app (Redis).
- [ ] Uploads y facturas persisten tras redeploy (volume `/app/storage`).
- [ ] Email links apuntan a dominio Railway real (no localhost).

---

## Variables obligatorias en Railway

```bash
APP_ENV=production
APP_URL=https://<dominio>.railway.app
APP_KEY=<secreto>

MYSQL_URL=mysql://user:pass@host:port/db
# o fallback DB_HOST/DB_PORT/DB_DATABASE/DB_USERNAME/DB_PASSWORD

REDIS_HOST=...
REDIS_PORT=6379
REDIS_PASSWORD=...
SESSION_DRIVER=redis
CACHE_DRIVER=redis

MAIL_HOST=...
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM=...
MAIL_FROM_NAME=Komorebi Cafe

FEATURE_BACKOFFICE=1
FEATURE_OPS=1
FEATURE_KEEPER=1
```
