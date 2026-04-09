# 🍵 Komorebi Café - Sistema de Gestión Integral

**Plataforma completa para gestión de cafeterías con animales estilo japonés**

> **🔥 Última actualización:** 02/02/2026 — Rediseño completo de base de datos v2.0 con compliance RGPD

## 🚀 Quick Start

```bash
# Clonar repositorio
git clone https://github.com/yourusername/komorebi.git
cd komorebi

# Iniciar con Docker
docker compose --profile dev up -d

# Aplicar rediseño de BD v2.0 (automático)
docker compose exec app php scripts/apply-db.php

# Verificar aplicación
docker compose exec app php scripts/verify-db-schema.php

# Acceder
# App: http://localhost:8080
# phpMyAdmin: http://localhost:8081
# Mailpit: http://localhost:8025
```

**Usuario admin por defecto:** `admin@komorebi.local` / `Admin123!`

## Variables de entorno

Copia `.env.example` a `.env` y configura los valores según tu entorno:

```bash
cp .env.example .env
```

> ⚠️ El archivo `.env` **NUNCA debe commitirse al repositorio**. Contiene secretos y credenciales sensibles. Asegúrate
> de que `.env` esté en `.gitignore`.

### Aplicación

| Variable                | Descripción                                                        | Ejemplo                 |
|-------------------------|--------------------------------------------------------------------|-------------------------|
| `APP_ENV`               | Entorno de ejecución (`local`/`production`)                        | `local`                 |
| `APP_DEBUG`             | Activar modo debug (desactivar en producción)                      | `true`                  |
| `APP_URL`               | URL base pública de la aplicación                                  | `http://localhost:8080` |
| `APP_KEY`               | Clave de cifrado simétrico (genera con `bin/generate-secrets.php`) | `base64:...`            |
| `APP_TIMEZONE`          | Zona horaria del sistema PHP                                       | `Europe/Madrid`         |
| `APP_BUSINESS_TIMEZONE` | Zona horaria del negocio (para mostrar horas)                      | `Asia/Tokyo`            |

### Docker Compose

| Variable           | Descripción                     | Ejemplo |
|--------------------|---------------------------------|---------|
| `COMPOSE_PROFILES` | Perfil activo de Docker Compose | `dev`   |

### Feature Flags

| Variable             | Descripción                                            | Ejemplo |
|----------------------|--------------------------------------------------------|---------|
| `FEATURE_OPS`        | Activa el módulo de operaciones (turnos, asignaciones) | `0`     |
| `FEATURE_BACKOFFICE` | Activa el módulo de backoffice administrativo          | `0`     |
| `FEATURE_KEEPER`     | Activa el módulo de keepers (salud animal)             | `0`     |

### Base de datos

| Variable           | Descripción                                             | Ejemplo              |
|--------------------|---------------------------------------------------------|----------------------|
| `DB_CONNECTION`    | Tipo de driver de base de datos                         | `mysql`              |
| `DB_HOST`          | Host del servidor MySQL (nombre del servicio en Docker) | `db`                 |
| `DB_PORT`          | Puerto de conexión MySQL                                | `3306`               |
| `DB_DATABASE`      | Nombre de la base de datos                              | `komorebi_db`        |
| `DB_USERNAME`      | Usuario de la base de datos                             | `komorebi`           |
| `DB_PASSWORD`      | Contraseña del usuario de BD                            | `secreto`            |
| `DB_ROOT_PASSWORD` | Contraseña root de MySQL (solo Docker)                  | `Root1234!`          |
| `DB_CHARSET`       | Charset de la conexión                                  | `utf8mb4`            |
| `DB_COLLATION`     | Collation de la conexión                                | `utf8mb4_unicode_ci` |

### Redis / Cola

| Variable         | Descripción                                             | Ejemplo          |
|------------------|---------------------------------------------------------|------------------|
| `REDIS_HOST`     | Host del servidor Redis (nombre del servicio en Docker) | `cache`          |
| `REDIS_PORT`     | Puerto de Redis                                         | `6379`           |
| `REDIS_PASSWORD` | Contraseña de autenticación Redis                       | `redis_password` |
| `REDIS_DB`       | Índice de base de datos Redis                           | `0`              |
| `CACHE_TTL`      | TTL por defecto de la caché en segundos                 | `3600`           |

### Email (Mailpit en dev)

| Variable          | Descripción                                | Ejemplo                   |
|-------------------|--------------------------------------------|---------------------------|
| `MAIL_DRIVER`     | Driver de envío de email                   | `smtp`                    |
| `MAIL_HOST`       | Host del servidor SMTP (Mailpit en dev)    | `mailpit`                 |
| `MAIL_PORT`       | Puerto SMTP                                | `1025`                    |
| `MAIL_USERNAME`   | Usuario SMTP (vacío en dev con Mailpit)    | ``                        |
| `MAIL_PASSWORD`   | Contraseña SMTP (vacía en dev con Mailpit) | ``                        |
| `MAIL_ENCRYPTION` | Cifrado SMTP (`tls`/`ssl`/vacío)           | ``                        |
| `MAIL_FROM`       | Dirección de email remitente               | `no-reply@komorebi.local` |
| `MAIL_FROM_NAME`  | Nombre del remitente                       | `Komorebi`                |

### APIs externas

| Variable               | Descripción                                                     | Ejemplo                         |
|------------------------|-----------------------------------------------------------------|---------------------------------|
| `OPEN_METEO_BASE_URL`  | URL base de la API de clima Open-Meteo (gratuita, sin clave)    | `https://api.open-meteo.com/v1` |
| `OPEN_METEO_CACHE_TTL` | TTL de caché para datos meteorológicos (segundos)               | `3600`                          |
| `NAGER_DATE_BASE_URL`  | URL base de la API de festivos Nager.Date (gratuita, sin clave) | `https://date.nager.at/api/v3`  |
| `NAGER_DATE_CACHE_TTL` | TTL de caché para festivos (segundos)                           | `86400`                         |

### Telegram Bot (opcional)

| Variable               | Descripción                            | Ejemplo                                         |
|------------------------|----------------------------------------|-------------------------------------------------|
| `TELEGRAM_BOT_TOKEN`   | Token del bot obtenido desde BotFather | `123456:ABC-DEF...`                             |
| `TELEGRAM_WEBHOOK_URL` | URL pública del webhook de Telegram    | `https://tudominio.com/api/v1/telegram/webhook` |

### Seguridad

| Variable            | Descripción                                                            | Ejemplo            |
|---------------------|------------------------------------------------------------------------|--------------------|
| `SESSION_SECRET`    | Secreto para firma de sesiones (genera con `bin/generate-secrets.php`) | `hex64chars...`    |
| `CSRF_TOKEN_SECRET` | Secreto para tokens CSRF                                               | `hex64chars...`    |
| `ENCRYPTION_KEY`    | Clave de cifrado simétrico AES                                         | `hex64chars...`    |
| `SESSION_NAME`      | Nombre de la cookie de sesión                                          | `KOMOREBI_SESSION` |
| `SESSION_LIFETIME`  | Duración de la sesión en segundos                                      | `7200`             |
| `SESSION_SECURE`    | Restringir cookie solo a HTTPS (activar en producción)                 | `false`            |
| `SESSION_HTTP_ONLY` | Bloquear acceso JavaScript a la cookie de sesión                       | `true`             |
| `SESSION_SAME_SITE` | Política SameSite de la cookie (`Lax`/`Strict`/`None`)                 | `Lax`              |

### Logging

| Variable        | Descripción                                            | Ejemplo                |
|-----------------|--------------------------------------------------------|------------------------|
| `LOG_CHANNEL`   | Canal de logging                                       | `single`               |
| `LOG_LEVEL`     | Nivel mínimo de log (`debug`/`info`/`warning`/`error`) | `debug`                |
| `LOG_PATH`      | Ruta del archivo de log                                | `storage/logs/app.log` |
| `LOG_MAX_FILES` | Número máximo de archivos rotados                      | `7`                    |

## 📋 Stack Tecnológico

| Componente    | Tecnología                | Versión  |
|---------------|---------------------------|----------|
| **Backend**   | PHP (FrankenPHP)          | 8.4+     |
| **Database**  | MySQL                     | 8.4      |
| **Cache**     | Redis                     | 8-alpine |
| **Frontend**  | Alpine.js + HTML5         | 3.x      |
| **Email**     | PHPMailer + Mailpit (dev) | 7.0      |
| **Server**    | FrankenPHP (Caddy + PHP)  | latest   |
| **Container** | Docker Compose            | -        |

## 🏗️ Arquitectura

- **Patrón:** MVC + Service Layer + Result Pattern
- **Namespace:** PSR-4 (`App\`)
- **Routing:** Custom router con middleware
- **Auth:** RBAC con 6 roles, 59 permisos granulares
- **Security:** CSRF, rate limiting, prepared statements, Argon2id
- **Cache:** Redis con fallback, invalidación automática
- **RGPD:** Soft deletes + eventos MySQL de purga automática (30 días datos, 1 año logs)

## ✨ Funcionalidades Principales

### Gestión de Usuarios

- Login/Logout con sesiones seguras
- Registro de clientes + verificación email
- Recuperación de contraseña
- Sistema RBAC (Admin, Manager, Reception, Kitchen, Staff, Customer)
- Rate limiting anti-fuerza bruta (5 intentos/15 min)
- **NUEVO:** Soft deletes con anonimización automática RGPD

### Gestión de Cafés

- CRUD completo con horarios operativos
- Integración API clima en tiempo real
- Festivos españoles automáticos
- Vista pública con filtros y búsqueda
- **NUEVO:** Geolocalización (lat/long), rating_avg calculado desde reviews

### Sistema de Reservas

- Formulario multi-step para clientes
- Verificación disponibilidad en tiempo real
- Paquetes/experiencias configurables
- Email confirmación automático
- Panel de recepción (check-in, no-show)
- UUIDs públicos para seguridad
- **NUEVO:** Protocolo check-in (higiene, briefing, calzado)
- **NUEVO:** Tracking de pagos (efectivo, tarjeta, transferencia)

### Reviews y Ratings

- Sistema de valoraciones 1-5 estrellas
- Moderación con aprobación/rechazo
- Validación: 1 review por café por usuario
- **NUEVO:** reservation_id obligatorio (trazabilidad)
- Requiere reserva completada

### Gestión de Productos

- CRUD menú con categorías
- Tipos: food, drink, item, pass
- Stock management (próximamente)

## 📖 Documentación

- **[DEPLOYMENT.md](docs/DEPLOYMENT.md)** — Instalación, Docker, actualización, backups y rollback
- **[ARCHITECTURE.md](docs/ARCHITECTURE.md)** — Diseño técnico, capas, patrones y decisiones
- **[migrations/README.md](migrations/README.md)** — Historial de migraciones SQL y dependencias
- **[CONTRIBUTING.md](CONTRIBUTING.md)** — Cómo contribuir, convención de ramas y proceso de PR

## 🧪 Testing

```bash
# Tests unitarios
docker compose exec app php vendor/bin/phpunit

# Tests con coverage
docker compose exec app php vendor/bin/phpunit --coverage-html coverage/

# Análisis estático (PHPStan)
docker compose exec app php vendor/bin/phpstan analyse
```

## 🛠️ Comandos Útiles

```bash
# Acceder al contenedor app
docker compose exec app bash

# Backup base de datos
docker compose exec mysql mysqldump -u root -p komorebi_db > backup.sql

# Ver logs en tiempo real
docker compose logs -f app

# Reiniciar servicios
docker compose restart app

# Estado de contenedores
docker compose ps
```

## 🔐 Seguridad

- ✅ Prepared statements (100% queries)
- ✅ CSRF protection en formularios POST
- ✅ XSS protection (escapado automático)
- ✅ Password hashing con Argon2id
- ✅ Rate limiting en login/registro
- ✅ Security headers (HSTS, CSP, X-Frame-Options)
- ✅ Session security (httponly, secure, samesite)

## 🤝 Desarrollo

```bash
# Instalar dependencias
docker compose exec app composer install

# Ejecutar migraciones
docker compose exec app php migrations/run_migrations.php

# Limpiar cache
docker compose exec app rm -rf storage/cache/*

# Reiniciar Redis
docker compose restart cache
```

## 📦 Estructura del Proyecto

```
komorebi/
├── app/
│   ├── Controllers/       # Controladores MVC
│   ├── Services/          # Lógica de negocio
│   ├── Models/            # Modelos de datos
│   ├── Core/              # Framework core
│   ├── Http/              # HTTP layer (middleware)
│   └── routes.php         # Definición de rutas
├── public/                # Document root
│   └── index.php          # Front controller
├── resources/
│   └── views/             # Plantillas PHP
├── migrations/            # SQL migrations
├── storage/
│   ├── cache/
│   ├── logs/
│   └── uploads/
├── tests/                 # PHPUnit tests
├── docker-compose.yml     # Stack Docker
└── composer.json          # Dependencias PHP
```

## 📄 Licencia

Proyecto Fin de Ciclo - DAW (Desarrollo de Aplicaciones Web)

---

**Última actualización:** Febrero 2026
