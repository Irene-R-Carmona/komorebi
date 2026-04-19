# Plan: Deploy en Railway — Komorebi Café (Defensa TFG)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan
> task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Fecha:** 17 de abril de 2026
**Estado:** 🔵 Plan creado — pendiente ejecución
**Prioridad:** ALTA — último plan antes de la defensa
**Opción elegida:** B — Web + Worker unificado + MySQL + Redis (~$15-20/mo)

---

## Contexto

Desplegar Komorebi Café en Railway para la defensa del TFG. La app usa FrankenPHP (PHP 8.4),
MySQL 8, Redis y 3 workers asíncronos. Railway se eligió por soporte nativo de MySQL, UI visual
demostrativa y push-to-deploy desde GitHub.

## Arquitectura en Railway

```
Railway Project "komorebi-cafe"
├── Service: web              → Dockerfile.prod (FrankenPHP)        ← ~$5/mo
├── Service: worker           → Dockerfile.worker + Supervisor      ← ~$5/mo
├── Plugin: MySQL             → Railway MySQL 8.0                   ← ~$5/mo
└── Plugin: Redis             → Railway Redis                       ← ~$5/mo
```

## Prerequisitos

- [ ] **P-01** — S0-01 completado: los 21 callers rotos de `Result` corregidos (sin esto la app no funciona)
- [ ] **P-02** — `make cs-check` → 0 violaciones
- [ ] **P-03** — `make test-unit` → 0 fallos
- [ ] **P-04** — Repositorio pusheado a GitHub (rama `main` o `deploy`)

---

## Fase 1 — Preparación del código para Railway

**Archivos:** `docker/php/Dockerfile.prod`, `docker/php/Dockerfile.worker`, `docker/supervisor.conf`

- [ ] **Step 1.1: Verificar Dockerfile.prod**
    - Confirmar que `EXPOSE 80` está presente (Railway lo detecta para ruteo)
    - Confirmar que `docker-entrypoint.sh` ejecuta migraciones si `RUN_MIGRATIONS=1`
    - Confirmar que FrankenPHP escucha en `:80` (ya configurado en `frankenphp.Caddyfile`)

- [ ] **Step 1.2: Verificar Dockerfile.worker**
    - Confirmar que existe y puede ejecutar workers vía Supervisor
    - Si no usa Supervisor, adaptar para ejecutar los 3 workers en un solo proceso:
      ```
      CMD ["supervisord", "-c", "/app/docker/supervisor.conf"]
      ```

- [ ] **Step 1.3: Verificar `docker/supervisor.conf`**
    - Debe contener programas para: `worker.php default`, `email-worker.php`, `notification-worker.php`
    - Confirmar que `stdout_logfile=/dev/stdout` (12-Factor: logs a stdout para Railway)

- [ ] **Step 1.4: Crear `railway.toml` (opcional pero recomendado)**
  ```toml
  [build]
  dockerfilePath = "docker/php/Dockerfile.prod"

  [deploy]
  healthcheckPath = "/health"
  healthcheckTimeout = 30
  restartPolicyType = "on_failure"
  restartPolicyMaxRetries = 3
  ```

---

## Fase 2 — Crear proyecto en Railway

**Archivos:** ninguno (configuración en Railway UI)

- [ ] **Step 2.1: Crear cuenta Railway**
    - Ir a https://railway.app → Sign in with GitHub
    - Verificar trial credits ($5 disponibles)

- [ ] **Step 2.2: Crear proyecto**
    - Dashboard → New Project → "Empty Project"
    - Nombre: `komorebi-cafe`

- [ ] **Step 2.3: Añadir plugin MySQL**
    - Add → Database → MySQL
    - Railway crea automáticamente las variables: `MYSQLHOST`, `MYSQLPORT`, `MYSQLDATABASE`, `MYSQLUSER`,
      `MYSQLPASSWORD`, `MYSQL_URL`

- [ ] **Step 2.4: Añadir plugin Redis**
    - Add → Database → Redis
    - Railway crea: `REDISHOST`, `REDISPORT`, `REDISPASSWORD`, `REDIS_URL`

---

## Fase 3 — Servicio web (FrankenPHP)

**Archivos:** configuración en Railway UI

- [ ] **Step 3.1: Añadir servicio web desde GitHub**
    - Add → GitHub Repo → seleccionar repositorio `komorebi`
    - Railway detecta `Dockerfile.prod` automáticamente
    - Si no lo detecta: Settings → Build → Dockerfile Path: `docker/php/Dockerfile.prod`

- [ ] **Step 3.2: Configurar variables de entorno del servicio web**
  Variables de referencia (Railway syntax `${{service.VAR}}`):

  ```
  # App
  APP_ENV=production
  APP_DEBUG=false
  APP_URL=https://<dominio-railway>.up.railway.app
  APP_KEY=<generar con bin/generate-secrets.php>
  APP_TIMEZONE=Europe/Madrid
  APP_BUSINESS_TIMEZONE=Asia/Tokyo

  # Database (reference variables de MySQL plugin)
  DB_CONNECTION=mysql
  DB_HOST=${{MySQL.MYSQLHOST}}
  DB_PORT=${{MySQL.MYSQLPORT}}
  DB_DATABASE=${{MySQL.MYSQLDATABASE}}
  DB_USERNAME=${{MySQL.MYSQLUSER}}
  DB_PASSWORD=${{MySQL.MYSQLPASSWORD}}

  # Redis (reference variables de Redis plugin)
  REDIS_HOST=${{Redis.REDISHOST}}
  REDIS_PORT=${{Redis.REDISPORT}}
  REDIS_PASSWORD=${{Redis.REDISPASSWORD}}

  # Email (Mailtrap free tier recomendado para demo)
  MAIL_HOST=sandbox.smtp.mailtrap.io
  MAIL_PORT=587
  MAIL_USERNAME=<mailtrap-username>
  MAIL_PASSWORD=<mailtrap-password>

  # Migraciones automáticas al arrancar
  SKIP_MIGRATIONS=0
  SKIP_SEEDERS=0

  # Feature flags (activar según demo)
  FEATURE_OPS=0
  FEATURE_BACKOFFICE=1
  FEATURE_KEEPER=0

  # CORS
  CORS_ALLOWED_ORIGINS=https://<dominio-railway>.up.railway.app
  ```

- [ ] **Step 3.3: Configurar dominio**
    - Settings → Networking → Generate Domain
    - Anotar URL generada (formato: `komorebi-cafe-production.up.railway.app`)
    - Actualizar `APP_URL` y `CORS_ALLOWED_ORIGINS` con el dominio real

- [ ] **Step 3.4: Configurar healthcheck**
    - Settings → Deploy → Health Check Path: `/health`

---

## Fase 4 — Servicio worker (Supervisor)

**Archivos:** configuración en Railway UI

- [ ] **Step 4.1: Añadir segundo servicio desde mismo repo**
    - Add → GitHub Repo → mismo repositorio
    - Settings → Build → Dockerfile Path: `docker/php/Dockerfile.worker`

- [ ] **Step 4.2: Configurar variables de entorno del worker**
  Mismas variables de DB y Redis que el servicio web (copiar o usar shared variables).
  No necesita `APP_URL`, `CORS_*` ni `MAIL_*` propios (usa los del servicio web si aplica).

  ```
  APP_ENV=production
  DB_HOST=${{MySQL.MYSQLHOST}}
  DB_PORT=${{MySQL.MYSQLPORT}}
  DB_DATABASE=${{MySQL.MYSQLDATABASE}}
  DB_USERNAME=${{MySQL.MYSQLUSER}}
  DB_PASSWORD=${{MySQL.MYSQLPASSWORD}}
  REDIS_HOST=${{Redis.REDISHOST}}
  REDIS_PORT=${{Redis.REDISPORT}}
  REDIS_PASSWORD=${{Redis.REDISPASSWORD}}
  MAIL_HOST=sandbox.smtp.mailtrap.io
  MAIL_PORT=587
  MAIL_USERNAME=<mailtrap-username>
  MAIL_PASSWORD=<mailtrap-password>
  ```

- [ ] **Step 4.3: Desactivar dominio público del worker**
    - Settings → Networking → NO generar dominio (el worker no recibe HTTP)

---

## Fase 5 — Migraciones y seeders

- [ ] **Step 5.1: Verificar que las migraciones se ejecutaron al arrancar**
    - Revisar logs del servicio web en Railway: buscar `[Migration]` o `apply-db.php`
    - Si `SKIP_MIGRATIONS=0` y el entrypoint lo ejecuta, debería ser automático

- [ ] **Step 5.2: Si las migraciones no se ejecutaron automáticamente**
    - Instalar Railway CLI: `npm install -g @railway/cli`
    - `railway login`
    - `railway link` → seleccionar proyecto
    - `railway run php scripts/apply-db.php`

- [ ] **Step 5.3: Verificar seeders**
    - En logs: buscar output de seeders (UserSeeder, CafeSeeder, etc.)
    - Verificar acceso con `admin@komorebi.local` / `Admin123!`

---

## Fase 6 — Verificación del deploy

- [ ] **Step 6.1: Healthcheck**
  ```
  curl https://<dominio>.up.railway.app/health
  # Esperado: HTTP 200
  ```

- [ ] **Step 6.2: Navegación básica**
    - [ ] Homepage carga correctamente
    - [ ] Catálogo de cafés muestra datos
    - [ ] Menú de productos visible
    - [ ] Login con `admin@komorebi.local` / `Admin123!` funciona
    - [ ] Dashboard admin accesible tras login

- [ ] **Step 6.3: Flujo de reserva (si Sprint 0 S0-01 corregido)**
    - [ ] Crear reserva como usuario
    - [ ] Verificar que el job se encola en Redis
    - [ ] Verificar en logs del worker que el email se procesa

- [ ] **Step 6.4: Workers operativos**
    - Revisar logs del servicio worker en Railway
    - Buscar `[EmailWorker] Listening`, `[Worker] Listening`, `[NotificationWorker] Listening`

- [ ] **Step 6.5: Rendimiento básico**
    - Lighthouse audit en URL de Railway (accesibilidad ≥ 90, performance aceptable)
    - Verificar tiempos de respuesta < 500ms en páginas principales

---

## Fase 7 — Preparación para la demo

- [ ] **Step 7.1: Datos de demo**
    - Verificar que los seeders crearon datos suficientes: cafés, productos, usuarios, reservas
    - Si faltan datos: `railway run php scripts/apply-db.php` con seeders habilitados

- [ ] **Step 7.2: Credenciales de demo**
  Documentar para tener a mano durante la defensa:

  | Rol | Email | Password |
    |-----|-------|----------|
  | Admin | `admin@komorebi.local` | `Admin123!` |
  | Manager | (verificar seeder) | (verificar seeder) |
  | User | (verificar seeder) | (verificar seeder) |

- [ ] **Step 7.3: Screenshots de Railway dashboard**
    - Capturar la vista del proyecto con los 4 servicios conectados
    - Útil para la presentación: muestra arquitectura real desplegada

- [ ] **Step 7.4: Plan de contingencia**
    - Si Railway falla durante la defensa: tener Docker Compose listo en local
    - `docker compose up -d` como backup inmediato en localhost:8080

---

## Criterios de Aceptación

1. App accesible en `https://<dominio>.up.railway.app` con HTTPS automático
2. Login funcional con credenciales de seeder
3. Workers procesando jobs (visible en logs de Railway)
4. Healthcheck `/health` devuelve HTTP 200
5. Lighthouse accesibilidad ≥ 90 en URL de producción
6. Tiempo de respuesta < 500ms en homepage

---

## Estimación de tiempo

| Fase                            | Duración estimada |
|---------------------------------|-------------------|
| Fase 1 — Preparación código     | 30 min            |
| Fase 2 — Crear proyecto Railway | 15 min            |
| Fase 3 — Servicio web           | 30 min            |
| Fase 4 — Servicio worker        | 20 min            |
| Fase 5 — Migraciones y seeders  | 15 min            |
| Fase 6 — Verificación           | 30 min            |
| Fase 7 — Preparación demo       | 20 min            |
| **Total**                       | **~2.5 horas**    |

> **Nota:** La estimación asume que P-01 (S0-01 callers rotos) ya está corregido.
> Si no, añadir 1-2h para esa tarea previa.

---

## Coste estimado

| Servicio            | Coste/mes   |
|---------------------|-------------|
| Web (FrankenPHP)    | ~$5         |
| Worker (Supervisor) | ~$5         |
| MySQL 8.0           | ~$5         |
| Redis               | ~$5         |
| **Total**           | **~$20/mo** |

> Trial de Railway incluye $5 de crédito. Para una demo temporal de 1-2 semanas,
> el coste real puede estar entre $5-10 si se destruye tras la defensa.

---

## Ciclo de vida del plan

Al completar cada fase: marcar tareas `[x]` + actualizar estado en `indice-maestro.md`.
Al completar todas las fases: eliminar este archivo y marcar `✅ Completado y eliminado` en el índice.

