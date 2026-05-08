# Komorebi Café — Sistema de Gestión Integral

Plataforma web para la gestión operativa de una cadena ficticia de catorce cafés de temática japonesa. El nombre deriva del término japonés *komorebi* (木漏れ日), que describe la luz solar filtrándose entre las hojas de los árboles.

Proyecto Fin de Ciclo — DAW (Desarrollo de Aplicaciones Web), I.E.S. Ágora, curso 2025–2026.

---

## Descripción

El sistema cubre el ciclo operativo completo: gestión de reservas con control de idempotencia, menú digital con filtros de alérgenos, programa de fidelización por niveles, Kitchen Display System (KDS) en tiempo real vía SSE, seguimiento de salud animal, operaciones de recepción con check-in/checkout y generación asíncrona de facturas en PDF.

Implementado sobre PHP 8.4 con un framework MVC propio (sin Laravel ni Symfony como capa de aplicación), siguiendo los estándares PSR-7, PSR-14 y PSR-15. La arquitectura sigue los principios 12-Factor App con despliegue mediante Docker Compose y Railway.

**Métricas del sistema:** más de 250 rutas HTTP, 53 servicios de negocio, 29 repositorios, más de 95 endpoints API REST.

---

## Requisitos

- Docker Desktop 4.x o superior
- Docker Compose v2
- Node.js 20+ en el host (para herramientas MCP en desarrollo)
- Make

---

## Inicio rápido

```bash
git clone https://github.com/Irene-R-Carmona/komorebi.git
cd komorebi
cp .env.example .env          # Configurar variables de entorno
make dev                      # Levanta el stack completo (app + mysql + redis + mailpit)
make db-migrate               # Aplica las migraciones SQL
```

Accesos por defecto en entorno local:

| Servicio   | URL                        |
|------------|----------------------------|
| Aplicación | <http://localhost:8080>       |
| Mailpit UI | <http://localhost:8025>       |
| Redis      | localhost:6379              |

Usuario administrador por defecto: `admin@komorebi.cafe` / `komorebi2024`

---

## Variables de entorno

Copia `.env.example` a `.env`. El archivo `.env` nunca debe commitirse al repositorio.

### Aplicación

| Variable                | Descripcion                                                         | Ejemplo                 |
|-------------------------|---------------------------------------------------------------------|-------------------------|
| `APP_ENV`               | Entorno de ejecucion (`local` / `production`)                       | `local`                 |
| `APP_DEBUG`             | Modo debug — desactivar en produccion                               | `true`                  |
| `APP_URL`               | URL base publica de la aplicacion                                   | `http://localhost:8080` |
| `APP_KEY`               | Clave de cifrado simetrico (genera con `bin/generate-secrets.php`)  | `base64:...`            |
| `APP_TIMEZONE`          | Zona horaria del sistema PHP                                        | `Europe/Madrid`         |
| `APP_BUSINESS_TIMEZONE` | Zona horaria del negocio (para mostrar horas)                       | `Asia/Tokyo`            |

### Feature flags

| Variable             | Descripcion                                           | Ejemplo |
|----------------------|-------------------------------------------------------|---------|
| `FEATURE_OPS`        | Activa el modulo de operaciones (turnos, asignaciones)| `0`     |
| `FEATURE_BACKOFFICE` | Activa el modulo de backoffice administrativo         | `0`     |
| `FEATURE_KEEPER`     | Activa el modulo de keepers (salud animal)            | `0`     |

### Base de datos

| Variable           | Descripcion                                              | Ejemplo              |
|--------------------|----------------------------------------------------------|----------------------|
| `DB_HOST`          | Host del servidor MySQL (servicio Docker)                | `db`                 |
| `DB_PORT`          | Puerto de conexion MySQL                                 | `3306`               |
| `DB_DATABASE`      | Nombre de la base de datos                               | `komorebi_db`        |
| `DB_USERNAME`      | Usuario de la base de datos                              | `komorebi`           |
| `DB_PASSWORD`      | Contrasena del usuario de BD                             | `secreto`            |
| `DB_ROOT_PASSWORD` | Contrasena root de MySQL (solo Docker)                   | `Root1234!`          |
| `DB_CHARSET`       | Charset de la conexion                                   | `utf8mb4`            |
| `DB_COLLATION`     | Collation de la conexion                                 | `utf8mb4_unicode_ci` |

### Redis

| Variable         | Descripcion                                    | Ejemplo          |
|------------------|------------------------------------------------|------------------|
| `REDIS_HOST`     | Host del servidor Redis (servicio Docker)      | `cache`          |
| `REDIS_PORT`     | Puerto de Redis                                | `6379`           |
| `REDIS_PASSWORD` | Contrasena de autenticacion Redis              | `redis_password` |
| `REDIS_DB`       | Indice de base de datos Redis                  | `0`              |
| `CACHE_TTL`      | TTL por defecto de la cache en segundos        | `3600`           |

### Email

| Variable          | Descripcion                                | Ejemplo                   |
|-------------------|--------------------------------------------|---------------------------|
| `MAIL_HOST`       | Host del servidor SMTP (Mailpit en dev)    | `mailpit`                 |
| `MAIL_PORT`       | Puerto SMTP                                | `1025`                    |
| `MAIL_FROM`       | Direccion de email remitente               | `no-reply@komorebi.local` |
| `MAIL_FROM_NAME`  | Nombre del remitente                       | `Komorebi`                |

### Seguridad

| Variable            | Descripcion                                                            | Ejemplo            |
|---------------------|------------------------------------------------------------------------|--------------------|
| `SESSION_SECRET`    | Secreto para firma de sesiones (genera con `bin/generate-secrets.php`) | `hex64chars...`    |
| `CSRF_TOKEN_SECRET` | Secreto para tokens CSRF                                               | `hex64chars...`    |
| `ENCRYPTION_KEY`    | Clave de cifrado simetrico AES                                         | `hex64chars...`    |
| `SESSION_NAME`      | Nombre de la cookie de sesion                                          | `KOMOREBI_SESSION` |
| `SESSION_LIFETIME`  | Duracion de la sesion en segundos                                      | `7200`             |
| `SESSION_SECURE`    | Restringir cookie solo a HTTPS (activar en produccion)                 | `false`            |

### APIs externas

| Variable               | Descripcion                                                      | Ejemplo                         |
|------------------------|------------------------------------------------------------------|---------------------------------|
| `OPEN_METEO_BASE_URL`  | URL base de la API de clima Open-Meteo (gratuita, sin clave)     | `https://api.open-meteo.com/v1` |
| `NAGER_DATE_BASE_URL`  | URL base de la API de festivos Nager.Date (gratuita, sin clave)  | `https://date.nager.at/api/v3`  |

### Telegram Bot (opcional)

| Variable               | Descripcion                             | Ejemplo                                         |
|------------------------|-----------------------------------------|-------------------------------------------------|
| `TELEGRAM_BOT_TOKEN`   | Token del bot obtenido desde BotFather  | `123456:ABC-DEF...`                             |
| `TELEGRAM_WEBHOOK_URL` | URL publica del webhook de Telegram     | `https://tudominio.com/api/v1/telegram/webhook` |

---

## Stack tecnologico

| Componente       | Tecnologia                     | Version  |
|------------------|--------------------------------|----------|
| Backend          | PHP con FrankenPHP             | 8.4      |
| Base de datos    | MySQL                          | 8.4      |
| Cache / Queue    | Redis                          | 8-alpine |
| Frontend         | Alpine.js + HTML5              | 3.x      |
| Email            | PHPMailer + Mailpit (dev)      | 7.0      |
| Servidor         | FrankenPHP (Caddy integrado)   | latest   |
| Contenedores     | Docker Compose                 | v2       |
| Tests            | PHPUnit                        | 13.x     |
| Analisis estatico| PHPStan nivel 5 + Psalm        | —        |

---

## Arquitectura

```
public/index.php          Front controller (bootstrap 12-Factor)
bootstrap/container.php   Service Providers (ciclo register -> boot)
app/routes.php            Definicion de todas las rutas (PSR-7/PSR-15)
app/Core/                 Framework custom (Router, Container, View, DB, Cache, Queue)
app/Http/Controllers/     Agrupados por rol: Admin/, Auth/, Manager/, Reception/, Kitchen/, Keeper/, Public/, Api/
app/Services/             Logica de negocio — 53 servicios (inyectados via Container)
app/Repositories/         Capa de acceso a datos — 29 repositorios (extienden AbstractRepository)
app/Events/ + Listeners/  Eventos asincrono PSR-14 (Symfony EventDispatcher)
app/Jobs/ + Workers/      Colas de trabajo asincronas consumidas por bin/email-worker.php
app/Providers/            ServiceProviders registrados en bootstrap/container.php
migrations/               Ficheros SQL planos (aplica con scripts/apply-db.php)
resources/views/          Plantillas agrupadas por rol; layouts/ contiene main, backoffice, kds, mobile, errors
```

Patrones arquitectonicos: MVC + Service Layer + Result Pattern + PSR-15 Middleware Pipeline + PSR-14 Events.

RBAC con 7 roles: `admin`, `manager`, `supervisor`, `reception`, `kitchen`, `keeper`, `user`.

---

## Comandos de desarrollo

Todos los comandos se ejecutan desde el host via `make`. Los targets relevantes estan definidos en `Makefile`.

```bash
make dev               # Levanta el stack Docker (app + mysql + redis + mailpit)
make bash              # Shell dentro del contenedor app
make logs-app          # Tail de logs del contenedor app
make clean             # Limpia storage/cache/* y storage/logs/*

make db-migrate        # Aplica migraciones SQL pendientes
make db-seed           # Ejecuta seeders
make db-reset          # Destruye y recrea volumenes (pide confirmacion)
make db-verify         # Verifica estado actual del esquema

make test              # Ciclo completo: build + migrate + phpunit + down
make test-unit         # Tests unitarios en paralelo (requiere stack levantado)
make test-integration  # Tests de integracion con BD efimera
make test-coverage     # Informe de cobertura HTML + Clover

make phpstan           # PHPStan nivel 5
make psalm             # Psalm analisis estatico
make cs-check          # PSR-12 en modo dry-run
make cs-fix            # Correccion automatica de estilo PSR-12
make ci                # Gate de calidad completo: phpstan + psalm + test + cs-check
make audit             # Auditoria de seguridad de dependencias Composer

make e2e               # Tests end-to-end con Playwright
make e2e-a11y          # Tests de accesibilidad WCAG 2.1 AA
```

---

## Seguridad

- Prepared statements en el 100% de las consultas
- CSRF en todas las rutas mutantes (POST/PUT/PATCH/DELETE)
- Escapado automatico en `View::render()` — XSS prevention
- Hashing de contrasenas con Argon2id
- Rate limiting en login y registro (Redis-backed)
- Security headers: HSTS, CSP, X-Frame-Options, X-Content-Type-Options
- Sesiones: httpOnly, secure, SameSite=Lax
- Secretos en produccion via `/run/secrets/` (Docker secrets)

Para reportar vulnerabilidades, consulta [SECURITY.md](SECURITY.md).

---

## Documentacion

| Documento                                      | Contenido                                                    |
|------------------------------------------------|--------------------------------------------------------------|
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md)   | Capas, patrones, decisiones de diseno y diagrama C4          |
| [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md)       | Despliegue en produccion, Railway, secretos y backups        |
| [docs/openapi.yaml](docs/openapi.yaml)         | Especificacion OpenAPI 3.1 de la API REST                    |
| [migrations/README.md](migrations/README.md)   | Historial de migraciones SQL y dependencias                  |
| [CONTRIBUTING.md](CONTRIBUTING.md)             | Convencion de ramas, flujo de PR y proceso de revision       |
| [DEFINITION_OF_DONE.md](DEFINITION_OF_DONE.md) | Criterios de aceptacion por tipo de cambio                   |

---

## Licencia

Proyecto academico — uso educativo. Ver [LICENSE](LICENSE).
