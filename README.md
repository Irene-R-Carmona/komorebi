# Komorebi Café — Sistema de Gestión Integral

> **[EN]** Production-deployed full-stack café management platform built
> with a **custom MVC framework** (no Laravel/Symfony) following PSR-7,
> PSR-14 and PSR-15 standards — 250+ routes · 53 business services ·
> Real-time KDS via SSE · WCAG 2.1 AA · Live on Railway

> **[ES]** Plataforma web full-stack para la gestión operativa de una
> cadena ficticia de catorce cafés de temática japonesa. Implementada
> sobre un framework MVC propio (sin Laravel ni Symfony), siguiendo
> los estándares PSR-7, PSR-14 y PSR-15, con despliegue en producción
> real sobre Railway.

> *El nombre deriva del término japonés 木漏れ日 (komorebi): la luz
> solar filtrándose entre las hojas de los árboles.*

[![PHP](https://img.shields.io/badge/PHP-8.4-777BB4?style=flat&logo=php&logoColor=white)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-8.4-4479A1?style=flat&logo=mysql&logoColor=white)](https://www.mysql.com/)
[![Redis](https://img.shields.io/badge/Redis-8--alpine-DC382D?style=flat&logo=redis&logoColor=white)](https://redis.io/)
[![Alpine.js](https://img.shields.io/badge/Alpine.js-3.x-8BC0D0?style=flat&logo=alpinedotjs&logoColor=white)](https://alpinejs.dev/)
[![Docker](https://img.shields.io/badge/Docker-Compose_v2-2496ED?style=flat&logo=docker&logoColor=white)](https://docs.docker.com/compose/)
[![Railway](https://img.shields.io/badge/Railway-Deployed-0B0D0E?style=flat&logo=railway&logoColor=white)](https://railway.app/)

[![PHPUnit](https://img.shields.io/badge/PHPUnit-13.x-366488?style=flat&logo=phpunit&logoColor=white)]()
[![PHPStan](https://img.shields.io/badge/PHPStan-Level_5-blue?style=flat)]()
[![Psalm](https://img.shields.io/badge/Psalm-passing-brightgreen?style=flat)]()
[![Playwright](https://img.shields.io/badge/Playwright-E2E-45ba4b?style=flat&logo=playwright&logoColor=white)]()
[![WCAG](https://img.shields.io/badge/WCAG_2.1-AA-1c6fba?style=flat)]()
[![License](https://img.shields.io/badge/License-Academic-lightgrey?style=flat)]()

---

## ✨ Highlights

| | |
|---|---|
| 🚀 **Deploy** | Live en Railway — accesible sin instalación local |
| 🏗️ **Framework** | MVC custom sin Laravel ni Symfony — PSR-7 · PSR-14 · PSR-15 · 12-Factor App |
| 📡 **Tiempo real** | Kitchen Display System (KDS) vía Server-Sent Events (SSE) |
| 🔐 **Seguridad** | CSRF · Argon2id · Rate limiting Redis · Security headers · Prepared statements 100% |
| ♿ **Accesibilidad** | Tests WCAG 2.1 AA con Playwright · Escapado automático XSS en View layer |
| 📊 **Escala** | 250+ rutas HTTP · 53 servicios de negocio · 29 repositorios · 95+ endpoints REST |
| 🧪 **Calidad** | PHPUnit 13 · PHPStan nivel 5 · Psalm · Playwright E2E · PSR-12 |
| 🎭 **RBAC** | 7 roles: admin · manager · supervisor · reception · kitchen · keeper · user |

---

## ⚡ Quick Start

### Con Docker (local)

```bash
git clone https://github.com/Irene-R-Carmona/komorebi.git
cd komorebi
cp .env.example .env          # Configurar variables de entorno
make dev                      # Levanta el stack completo
make db-migrate               # Aplica las migraciones SQL
make db-seed                  # Datos de demostración
```

| Servicio | URL |
|---|---|
| Aplicación | http://localhost:8080 |
| Mailpit UI | http://localhost:8025 |

Usuario administrador: `admin@komorebi.cafe` / `komorebi2024`

### Demo en producción (Railway)

> 🔗 **[Ver demo en vivo](https://komorebi.up.railway.app/)**
>
> Acceso sin instalación. Usuario demo disponible en la pantalla de login.

---

## Tabla de contenidos

- [Descripción](#descripción)
- [Arquitectura](#arquitectura)
- [Stack tecnológico](#stack-tecnológico)
- [Módulos funcionales](#módulos-funcionales)
- [Seguridad](#seguridad)
- [Requisitos](#requisitos)
- [Variables de entorno](#variables-de-entorno)
- [Comandos de desarrollo](#comandos-de-desarrollo)
- [Tests y calidad](#tests-y-calidad)
- [Documentación](#documentación)
- [Roadmap](#roadmap)
- [Licencia](#licencia)

---

## Descripción

**Komorebi Café** es una plataforma web full-stack que cubre el ciclo operativo completo de una cadena ficticia de catorce cafés de temática japonesa:

- **Reservas** con control de idempotencia
- **Menú digital** con filtros de alérgenos
- **Programa de fidelización** por niveles
- **Kitchen Display System (KDS)** en tiempo real vía SSE
- **Seguimiento de salud animal** (módulo Keeper)
- **Operaciones de recepción** con check-in/checkout
- **Generación asíncrona de facturas** en PDF

Implementado sobre **PHP 8.4 con FrankenPHP**, con un framework MVC propio construido sin Laravel ni Symfony como capa de aplicación. El framework sigue los estándares **PSR-7** (HTTP messages), **PSR-14** (Event Dispatcher) y **PSR-15** (HTTP Middlewares), y cumple los principios de la **12-Factor App**.

**Proyecto Fin de Ciclo** — DAW (Desarrollo de Aplicaciones Web), I.E.S. Ágora, 2025–2026.

---

## Arquitectura

```
public/index.php            Front controller (bootstrap 12-Factor)
bootstrap/container.php     Service Providers (ciclo register → boot)
app/routes.php              Definición de todas las rutas (PSR-7/PSR-15)

app/Core/                   Framework custom
├── Router/                 Resolución de rutas PSR-7
├── Container/              IoC Container con autowiring
├── View/                   Motor de plantillas con escapado XSS automático
├── DB/                     Query Builder sobre PDO con prepared statements
├── Cache/                  Abstracción Redis
└── Queue/                  Sistema de colas asíncronas

app/Http/Controllers/       Agrupados por rol
├── Admin/                  Backoffice administrativo
├── Auth/                   Autenticación y sesiones
├── Manager/                Gestión de turno y operaciones
├── Reception/              Check-in/checkout y reservas
├── Kitchen/                KDS y gestión de pedidos
├── Keeper/                 Salud animal
├── Public/                 Menú digital y fidelización
└── Api/                    95+ endpoints REST

app/Services/               Lógica de negocio (53 servicios)
app/Repositories/           Acceso a datos (29 repositorios)
app/Events/ + Listeners/    PSR-14 async events (Symfony EventDispatcher)
app/Jobs/ + Workers/        Colas de trabajo (bin/email-worker.php)
app/Providers/              ServiceProviders registrados en bootstrap
migrations/                 Ficheros SQL planos (apply-db.php)
resources/views/            Plantillas por rol: layouts, kds, mobile, errors
```

**Patrones arquitectónicos:** MVC + Service Layer + Result Pattern +
PSR-15 Middleware Pipeline + PSR-14 Events.

**RBAC con 7 roles:** `admin` · `manager` · `supervisor` · `reception` ·
`kitchen` · `keeper` · `user`

---

## Stack tecnológico

| Componente | Tecnología | Versión |
|---|---|---|
| Backend | PHP con FrankenPHP | 8.4 |
| Base de datos | MySQL | 8.4 |
| Cache / Queue | Redis | 8-alpine |
| Frontend | Alpine.js + HTML5 + CSS3 | 3.x |
| Email | PHPMailer + Mailpit (dev) | 7.0 |
| Servidor | FrankenPHP (Caddy integrado) | latest |
| Contenedores | Docker Compose | v2 |
| Tests unitarios | PHPUnit | 13.x |
| Análisis estático | PHPStan nivel 5 + Psalm | — |
| Tests E2E | Playwright | — |
| Accesibilidad | Playwright WCAG 2.1 AA | — |
| Build / Dev | Make | — |
| Deploy | Railway | — |

---

## Módulos funcionales

| Módulo | Descripción | Roles con acceso |
|---|---|---|
| **Auth** | Autenticación, sesiones, registro | Todos |
| **Public** | Menú digital con filtros de alérgenos, fidelización | user |
| **Reception** | Check-in/checkout, reservas, idempotencia | reception, manager, admin |
| **Kitchen / KDS** | Kitchen Display System en tiempo real vía SSE | kitchen, manager, admin |
| **Manager** | Operaciones de turno y asignaciones | manager, supervisor, admin |
| **Keeper** | Seguimiento de salud animal | keeper, admin |
| **Backoffice** | Gestión administrativa integral | admin |
| **API REST** | 95+ endpoints para integración externa | según endpoint |

### Feature flags

Los módulos opcionales se activan mediante variables de entorno:

| Variable | Módulo |
|---|---|
| `FEATURE_OPS` | Operaciones (turnos y asignaciones) |
| `FEATURE_BACKOFFICE` | Backoffice administrativo |
| `FEATURE_KEEPER` | Salud animal (keepers) |

---

## Seguridad

- **Prepared statements** en el 100% de las consultas — sin SQL injection
- **CSRF** en todas las rutas mutantes (POST/PUT/PATCH/DELETE)
- **Escapado automático XSS** en `View::render()` — sin output sin sanitizar
- **Hashing Argon2id** para contraseñas
- **Rate limiting** en login y registro (Redis-backed)
- **Security headers:** HSTS · CSP · X-Frame-Options · X-Content-Type-Options
- **Sesiones:** httpOnly · secure · SameSite=Lax
- **Secretos en producción** via `/run/secrets/` (Docker secrets)
- **Auditoría de dependencias** con `make audit` (Composer security check)

Para reportar vulnerabilidades, consulta [SECURITY.md](SECURITY.md).

---

## Requisitos

- Docker Desktop 4.x o superior
- Docker Compose v2
- Node.js 20+ en el host (herramientas MCP en desarrollo)
- Make

No es necesario instalar PHP, MySQL ni Redis localmente.
Docker gestiona todo el stack.

---

## Variables de entorno

Copia `.env.example` a `.env`. El archivo `.env` nunca debe commitirse.

### Aplicación

| Variable | Descripción | Ejemplo |
|---|---|---|
| `APP_ENV` | Entorno (`local` / `production`) | `local` |
| `APP_DEBUG` | Modo debug — desactivar en producción | `true` |
| `APP_URL` | URL base pública | `http://localhost:8080` |
| `APP_KEY` | Clave de cifrado simétrico | `base64:...` |
| `APP_TIMEZONE` | Zona horaria PHP | `Europe/Madrid` |
| `APP_BUSINESS_TIMEZONE` | Zona horaria del negocio | `Asia/Tokyo` |

### Feature flags

| Variable | Descripción | Ejemplo |
|---|---|---|
| `FEATURE_OPS` | Módulo de operaciones | `0` |
| `FEATURE_BACKOFFICE` | Módulo backoffice | `0` |
| `FEATURE_KEEPER` | Módulo keepers | `0` |

### Base de datos

| Variable | Descripción | Ejemplo |
|---|---|---|
| `DB_HOST` | Host MySQL (servicio Docker) | `db` |
| `DB_PORT` | Puerto MySQL | `3306` |
| `DB_DATABASE` | Nombre de la BD | `komorebi_db` |
| `DB_USERNAME` | Usuario BD | `komorebi` |
| `DB_PASSWORD` | Contraseña BD | `secreto` |
| `DB_ROOT_PASSWORD` | Contraseña root MySQL (Docker) | `Root1234!` |
| `DB_CHARSET` | Charset | `utf8mb4` |
| `DB_COLLATION` | Collation | `utf8mb4_unicode_ci` |

### Redis

| Variable | Descripción | Ejemplo |
|---|---|---|
| `REDIS_HOST` | Host Redis (servicio Docker) | `cache` |
| `REDIS_PORT` | Puerto Redis | `6379` |
| `REDIS_PASSWORD` | Contraseña Redis | `redis_password` |
| `REDIS_DB` | Índice BD Redis | `0` |
| `CACHE_TTL` | TTL por defecto (segundos) | `3600` |

### Email

| Variable | Descripción | Ejemplo |
|---|---|---|
| `MAIL_HOST` | Host SMTP (Mailpit en dev) | `mailpit` |
| `MAIL_PORT` | Puerto SMTP | `1025` |
| `MAIL_FROM` | Dirección remitente | `no-reply@komorebi.local` |
| `MAIL_FROM_NAME` | Nombre remitente | `Komorebi` |

### Seguridad

| Variable | Descripción | Ejemplo |
|---|---|---|
| `SESSION_SECRET` | Secreto firma sesiones | `hex64chars...` |
| `CSRF_TOKEN_SECRET` | Secreto tokens CSRF | `hex64chars...` |
| `ENCRYPTION_KEY` | Clave AES simétrica | `hex64chars...` |
| `SESSION_NAME` | Nombre cookie sesión | `KOMOREBI_SESSION` |
| `SESSION_LIFETIME` | Duración sesión (segundos) | `7200` |
| `SESSION_SECURE` | Solo HTTPS (activar en producción) | `false` |

### APIs externas

| Variable | Descripción | Ejemplo |
|---|---|---|
| `OPEN_METEO_BASE_URL` | API clima Open-Meteo (sin clave) | `https://api.open-meteo.com/v1` |
| `NAGER_DATE_BASE_URL` | API festivos Nager.Date (sin clave) | `https://date.nager.at/api/v3` |

### Telegram Bot (opcional)

| Variable | Descripción | Ejemplo |
|---|---|---|
| `TELEGRAM_BOT_TOKEN` | Token BotFather | `123456:ABC-DEF...` |
| `TELEGRAM_WEBHOOK_URL` | URL pública webhook | `https://tudominio.com/api/v1/telegram/webhook` |

---

## Comandos de desarrollo

Todos los comandos se ejecutan desde el host via `make`.

```bash
# Stack Docker
make dev               # Levanta app + mysql + redis + mailpit
make bash              # Shell dentro del contenedor app
make logs-app          # Tail de logs del contenedor app
make clean             # Limpia storage/cache/* y storage/logs/*

# Base de datos
make db-migrate        # Aplica migraciones SQL pendientes
make db-seed           # Ejecuta seeders
make db-reset          # Destruye y recrea volúmenes (pide confirmación)
make db-verify         # Verifica estado actual del esquema

# Tests y calidad
make test              # Ciclo completo: build + migrate + phpunit + down
make test-unit         # Tests unitarios en paralelo
make test-integration  # Tests de integración con BD efímera
make test-coverage     # Informe de cobertura HTML + Clover
make phpstan           # PHPStan nivel 5
make psalm             # Psalm análisis estático
make cs-check          # PSR-12 dry-run
make cs-fix            # Corrección automática PSR-12
make ci                # Gate completo: phpstan + psalm + test + cs-check
make audit             # Auditoría de seguridad Composer

# E2E y accesibilidad
make e2e               # Tests end-to-end con Playwright
make e2e-a11y          # Tests de accesibilidad WCAG 2.1 AA
```

---

## Tests y calidad

```bash
# Ejecutar suite completa (no requiere stack levantado para unitarios)
make test

# Solo unitarios en paralelo
make test-unit

# Cobertura HTML
make test-coverage
# Informe: coverage/html/index.html
```

### Pipeline de calidad (`make ci`)

```
PHPStan L5 → Psalm → PHPUnit → CS Check PSR-12
```

Todos los checks deben pasar para que el gate de calidad sea verde.

### Análisis estático

| Herramienta | Nivel | Alcance |
|---|---|---|
| **PHPStan** | Nivel 5 | Detección de errores de tipo, null safety, lógica |
| **Psalm** | — | Análisis de flujo, inferencia de tipos, seguridad |
| **PSR-12** | — | Estilo de código (make cs-fix para autocorrección) |

### Tests E2E y accesibilidad

```bash
make e2e        # Playwright — flujos funcionales completos
make e2e-a11y   # Playwright — WCAG 2.1 AA por cada vista
```

---

## Documentación

| Documento | Contenido |
|---|---|
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | Capas, patrones, decisiones de diseño y diagrama C4 |
| [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) | Despliegue en producción, Railway, secretos y backups |
| [docs/openapi.yaml](docs/openapi.yaml) | Especificación OpenAPI 3.1 de la API REST |
| [migrations/README.md](migrations/README.md) | Historial de migraciones SQL y dependencias |
| [CONTRIBUTING.md](CONTRIBUTING.md) | Convención de ramas, flujo de PR y proceso de revisión |
| [DEFINITION_OF_DONE.md](DEFINITION_OF_DONE.md) | Criterios de aceptación por tipo de cambio |
| [SECURITY.md](SECURITY.md) | Política de reporte de vulnerabilidades |

---

## Roadmap

Las siguientes mejoras están identificadas para iteraciones futuras:

| Mejora | Descripción | Prioridad |
|---|---|---|
| **WebSockets** | Migrar KDS de SSE a WebSockets para comunicación bidireccional | Media |
| **Tests de integración BD** | Ampliar cobertura de tests de integración con BD efímera | Media |
| **Dashboard de métricas** | Panel de analíticas en tiempo real para admins | Baja |
| **API versioning** | Añadir versionado v2 a la API REST | Baja |
| **i18n** | Internacionalización para soporte multiidioma | Planificado |
| **PWA** | Progressive Web App para acceso offline en KDS | Planificado |

---

## Licencia

Proyecto académico — uso educativo. Ver [LICENSE](LICENSE).

© 2026 Irene Reyes Carmona — Proyecto Fin de Ciclo DAW, I.E.S. Ágora, Cáceres.
