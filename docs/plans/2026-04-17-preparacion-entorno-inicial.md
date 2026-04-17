# Preparación del Entorno Inicial (Post-Clone) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:
> executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Dejar el stack completo de Komorebi Café operativo tras clonar el repositorio en una máquina nueva, incluyendo
app, BD, Redis, workers de cola y verificación de calidad.

**Architecture:** Stack Docker Compose con perfiles `dev` y `workers`. Secrets generados con `bin/generate-secrets.php`.
Migraciones aplicadas con `scripts/apply-db.php`. Workers supervisados por Docker Compose (`restart: on-failure:3`).

**Tech Stack:** Docker Compose v2, FrankenPHP/PHP 8.4, MySQL 8, Redis, Supervisor, Mailpit, phpMyAdmin.

---

## Estado del plan

| Fase | Descripción                      | Estado |
|------|----------------------------------|--------|
| 1    | Prerequisitos del host           | - [ ]  |
| 2    | Archivo de entorno               | - [ ]  |
| 3    | Arranque de infraestructura base | - [ ]  |
| 4    | Generación de secretos           | - [ ]  |
| 5    | Stack completo con workers       | - [ ]  |
| 6    | Migraciones y seeders            | - [ ]  |
| 7    | Verificación de workers          | - [ ]  |
| 8    | Verificación de calidad          | - [ ]  |
| 9    | Comprobación de accesos UI       | - [ ]  |

---

## Fase 1 — Prerequisitos del host

**Archivos:** ninguno (verificación de entorno).

- [ ] **Step 1.1: Verificar Docker Engine ≥ 24**

```bash
docker --version
docker compose version
```

Expected: `Docker version 24.x.x` y `Docker Compose version v2.x.x`. Si falla → instalar Docker Desktop.

- [ ] **Step 1.2: Verificar Node.js ≥ 20 en el host**

```bash
node --version
```

Expected: `v20.x.x` o superior. Node es requerido para los servidores MCP (`.vscode/mcp.json`). Si falta → instalar
desde https://nodejs.org.

- [ ] **Step 1.3: Verificar que no hay contenedores previos activos en los mismos puertos**

```bash
docker ps --filter "publish=8080" --filter "publish=3306" --filter "publish=6379"
```

Expected: sin filas → puertos libres. Si hay conflicto → `docker compose down` del proyecto anterior.

---

## Fase 2 — Archivo de entorno

**Archivos:**

- Crear: `.env` (copia de `.env.example`)

- [ ] **Step 2.1: Copiar `.env.example` a `.env`**

En Windows PowerShell:

```powershell
Copy-Item .env.example .env
```

En Linux/macOS:

```bash
cp .env.example .env
```

- [ ] **Step 2.2: Completar los valores obligatorios en `.env`**

Abrir `.env` y rellenar (los valores marcados con `=` vacío son obligatorios):

```dotenv
# Base de datos — elige contraseñas seguras:
DB_DATABASE=komorebi_db
DB_USERNAME=komorebi_user
DB_PASSWORD=komorebi_pass          # mínimo 12 caracteres
DB_ROOT_PASSWORD=root_pass_secure  # mínimo 12 caracteres

# Redis — mantener el valor por defecto o cambiar consistentemente:
REDIS_PASSWORD=redis_password

# Feature flags — activar los módulos que se quieran probar:
FEATURE_OPS=1
FEATURE_BACKOFFICE=1
FEATURE_KEEPER=1

# COMPOSE_PROFILES ya viene configurado en .env.example:
COMPOSE_PROFILES=dev,workers
```

> **Nota:** `APP_KEY`, `SESSION_SECRET`, `CSRF_TOKEN_SECRET`, `ENCRYPTION_KEY` se generan en la Fase 4 — no rellenar
> aún.

- [ ] **Step 2.3: Confirmar que `.env` NO está en git**

```bash
git status .env
```

Expected: aparece como `untracked` o no aparece. Si está trackeado → el `.gitignore` está mal configurado (revisar).

---

## Fase 3 — Arranque de infraestructura base

**Objetivo:** levantar solo `db` y `cache` para poder ejecutar `generate-secrets.php`.

- [ ] **Step 3.1: Construir la imagen de la app (primera vez)**

```bash
docker compose build app
```

Expected: `Successfully built <hash>`. Puede tardar 2-5 minutos la primera vez (descarga de layers).

- [ ] **Step 3.2: Levantar solo db y cache**

```bash
docker compose up -d db cache
```

Expected: dos contenedores en estado `Up`.

- [ ] **Step 3.3: Esperar a que la BD esté lista**

```bash
docker compose ps
```

Expected: `komorebi-db` muestra `(healthy)`. Si sigue `starting` → esperar 10-15 segundos y repetir.

---

## Fase 4 — Generación de secretos

**Archivos:** `.env` (actualizar con valores generados).

- [ ] **Step 4.1: Ejecutar el generador de secretos**

```bash
docker compose run --rm app php bin/generate-secrets.php
```

Expected: salida con los valores de `APP_KEY`, `SESSION_SECRET`, `CSRF_TOKEN_SECRET` y `ENCRYPTION_KEY`. Ejemplo:

```
APP_KEY=base64:xxx...
SESSION_SECRET=xxx...
CSRF_TOKEN_SECRET=xxx...
ENCRYPTION_KEY=xxx...
```

- [ ] **Step 4.2: Copiar los valores generados al `.env`**

Abrir `.env` y sustituir los valores vacíos correspondientes con los generados. Sin comillas, sin espacios extra.

- [ ] **Step 4.3: Verificar que `.env` tiene todos los secrets**

```bash
grep -E "^(APP_KEY|SESSION_SECRET|CSRF_TOKEN_SECRET|ENCRYPTION_KEY)=" .env
```

Expected: 4 líneas con valores no vacíos.

---

## Fase 5 — Arranque del stack completo (app + workers)

**Archivos:** ninguno (operación Docker).

- [ ] **Step 5.1: Levantar el stack completo con workers**

```bash
docker compose --profile dev --profile workers up -d
```

O equivalente con make:

```bash
make dev-full
```

Expected: los siguientes contenedores arrancados:

- `komorebi-app` (FrankenPHP, puerto 8080)
- `komorebi-db` (MySQL, puerto 3306)
- `komorebi-cache` (Redis, puerto 6379)
- `komorebi-mailpit` (Mailpit UI: 8025, SMTP: 1025)
- `komorebi-pma` (phpMyAdmin, puerto 8081) — perfil dev
- `komorebi-redis-commander` (RedisInsight, puerto 8082) — perfil dev
- `komorebi-queue` (worker general) — perfil workers
- `komorebi-email-worker` (worker de email) — perfil workers
- `komorebi-notification-worker` (worker de notificaciones) — perfil workers

- [ ] **Step 5.2: Verificar que todos los contenedores están activos**

```bash
docker compose ps
```

Expected: todos en estado `Up` o `running`. Los de perfil `dev`/`workers` deben aparecer si
`COMPOSE_PROFILES=dev,workers` está en `.env`.

- [ ] **Step 5.3: Esperar a que `app` tenga healthcheck verde**

```bash
docker compose ps app
```

Expected: `komorebi-app` muestra `(healthy)`. Puede tardar hasta 30 segundos.

---

## Fase 6 — Migraciones y seeders

**Archivos:** BD (18 migraciones SQL en `migrations/`).

- [ ] **Step 6.1: Aplicar las 18 migraciones y ejecutar seeders**

```bash
docker compose exec app php scripts/apply-db.php
```

Expected: salida con éxito por cada archivo de migración (001 al 018). Los seeders crean el usuario
`admin@komorebi.local` con contraseña `Admin123!`.

- [ ] **Step 6.2: Verificar el estado del esquema**

```bash
docker compose exec app php scripts/verify-db-schema.php
```

Expected: todas las tablas presentes y sin errores de integridad.

- [ ] **Step 6.3: Verificar estadísticas de datos de seed**

```bash
make stats
```

Expected: filas en Usuarios, Cafés, Roles, con conteos > 0.

---

## Fase 7 — Verificación de workers

**Objetivo:** confirmar que los 3 workers conectan a Redis y MySQL sin errores.

- [ ] **Step 7.1: Revisar logs del worker de cola general**

```bash
docker compose logs --tail=30 queue
```

Expected: mensajes de inicio como `[QueueWorker] Starting...` o `Waiting for jobs`. Sin líneas `ERROR` o
`Connection refused`.

- [ ] **Step 7.2: Revisar logs del worker de email**

```bash
docker compose logs --tail=30 email_worker
```

Expected: mensajes de inicio del worker. Sin errores de conexión Redis/MySQL.

- [ ] **Step 7.3: Revisar logs del worker de notificaciones**

```bash
docker compose logs --tail=30 notification_worker
```

Expected: ídem al worker de email.

- [ ] **Step 7.4: Test end-to-end del worker de email (smoke test)**

Acceder a `http://localhost:8080` → registrar una cuenta nueva → confirmar que el email de bienvenida aparece en
`http://localhost:8025` (Mailpit) en menos de 10 segundos.

Expected: email capturado en Mailpit con asunto de bienvenida.

---

## Fase 8 — Verificación de calidad

**Archivos:** ninguno (análisis estático del código existente).

- [ ] **Step 8.1: PHPStan análisis estático nivel 5**

```bash
make phpstan
```

Expected: `[OK] No errors` o solo errores conocidos cubiertos por `phpstan-baseline.neon`.

- [ ] **Step 8.2: Tests unitarios en paralelo**

```bash
make test-unit
```

Expected: todos los tests en verde. Si fallan → revisar `make logs-app` para detectar problemas de configuración del
`.env`.

- [ ] **Step 8.3: Auditoría de seguridad de dependencias**

```bash
make audit
```

Expected: `No security vulnerability advisories found` o lista de advisories conocidos sin CVEs críticos.

---

## Fase 9 — Comprobación de accesos UI

- [ ] **Step 9.1: Verificar health check de la app**

```bash
curl http://localhost:8080/health
```

Expected: `{"status":"ok"}` con HTTP 200.

- [ ] **Step 9.2: Acceder al dashboard de administración**

Abrir `http://localhost:8080/admin/dashboard` en el navegador.
Credenciales: `admin@komorebi.local` / `Admin123!`.
Expected: dashboard carga sin errores 500.

- [ ] **Step 9.3: Verificar accesos a herramientas de desarrollo**

| Herramienta  | URL                     | Credencial               |
|--------------|-------------------------|--------------------------|
| App          | `http://localhost:8080` | —                        |
| phpMyAdmin   | `http://localhost:8081` | usuario/clave del `.env` |
| Mailpit      | `http://localhost:8025` | sin credencial           |
| RedisInsight | `http://localhost:8082` | sin credencial           |

Expected: las cuatro URLs responden con código 200.

---

## Notas de troubleshooting

| Síntoma                             | Causa probable                      | Solución                             |
|-------------------------------------|-------------------------------------|--------------------------------------|
| `komorebi-app` no pasa a `healthy`  | `.env` tiene secretos vacíos        | Repetir Fase 4                       |
| Workers en estado `Exit`            | Error de conexión a Redis           | Verificar `REDIS_PASSWORD` en `.env` |
| `apply-db.php` falla en migración X | Migración ya aplicada o BD no lista | `make db-reset` si es entorno nuevo  |
| `make test-unit` falla              | App no está corriendo               | Ejecutar `make dev` primero          |
| Mailpit no recibe emails            | Worker de email caído               | `docker compose logs email_worker`   |

