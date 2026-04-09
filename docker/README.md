# Docker - Komorebi v2

Configuración de contenedores Docker para el entorno de desarrollo y producción de Komorebi Café.

## 📦 Servicios

```
┌─────────────────────────────────────────────────────────────────────┐
│                        KOMOREBI DOCKER STACK                        │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  ┌─────────────┐     ┌─────────────┐     ┌─────────────┐           │
│  │     APP     │────▶│     DB      │     │    CACHE    │           │
│  │  PHP 8.4    │     │  MySQL 8.4  │     │  Redis 7.4  │           │
│  │  Apache     │     │             │     │             │           │
│  │  :8080      │     │  :3306      │     │  :6379      │           │
│  └─────────────┘     └─────────────┘     └─────────────┘           │
│         │                   ▲                   ▲                   │
│         │                   │                   │                   │
│         ▼                   │                   │                   │
│  ┌─────────────┐     ┌─────────────┐     ┌─────────────┐           │
│  │   MAILPIT   │     │    QUEUE    │     │  TELEGRAM   │           │
│  │  SMTP Mock  │     │   Worker    │     │    Bot      │           │
│  │  :8025/:1025│     │ (placeholder)│     │ (placeholder)│          │
│  └─────────────┘     └─────────────┘     └─────────────┘           │
│                                                                     │
│  ════════════════════ PROFILE: dev ════════════════════            │
│                                                                     │
│  ┌─────────────┐     ┌─────────────┐                               │
│  │ PhpMyAdmin  │     │   Redis     │                               │
│  │   :8081     │     │  Commander  │                               │
│  │             │     │   :8082     │                               │
│  └─────────────┘     └─────────────┘                               │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

## 🚀 Inicio Rápido

### Primera instalación

**Linux/Mac:**

```bash
# 1. Copiar configuración
cp .env.example .env

# 2. Editar variables en .env (ajustar passwords)

# 3. Iniciar en modo desarrollo
docker compose --profile dev up -d

# O usando el helper:
./docker-helper.sh dev

# O usando Make:
make dev
```

**Windows PowerShell:**

```powershell
# 1. Copiar configuración
Copy-Item .env.example .env

# 2. Editar variables en .env (ajustar passwords)

# 3. Iniciar en modo desarrollo
docker compose --profile dev up -d

# O usando el helper:
.\docker-helper.ps1 dev
```

> **Nota**: Asegúrate de usar `docker compose` (con espacio) y no `docker-compose` (con guión). El proyecto usa Docker Compose V2.

### URLs disponibles

| Servicio        | URL                     | Descripción             |
|-----------------|-------------------------|-------------------------|
| App             | <http://localhost:8080> | Aplicación principal    |
| Mailpit         | <http://localhost:8025> | UI de emails capturados |
| PhpMyAdmin      | <http://localhost:8081> | Admin BD (solo dev)     |
| Redis Commander | <http://localhost:8082> | Admin Redis (solo dev)  |

## ⚙️ Comandos

### Usando docker-helper (recomendado)

```bash
# Linux/Mac
./docker-helper.sh dev          # Inicia en modo desarrollo
./docker-helper.sh setup        # Ejecuta migrate + seed
./docker-helper.sh logs         # Ver logs de app
./docker-helper.sh db-shell     # Shell MySQL
./docker-helper.sh bash         # Shell en contenedor

# Windows PowerShell
.\docker-helper.ps1 dev
.\docker-helper.ps1 setup
.\docker-helper.ps1 logs
```

### Usando Make

```bash
make dev        # Inicia en modo desarrollo
make setup      # migrate + seed + permisos
make logs       # Ver logs
make db-shell   # Shell MySQL
make reset      # Reset completo (¡borra datos!)
```

### Usando docker compose directamente

```bash
# Servicios básicos
docker compose up -d

# Con herramientas de desarrollo
docker compose --profile dev up -d

# Detener todo
docker compose --profile dev down

# Reset completo (elimina volúmenes)
docker compose --profile dev down -v
```

## 🔧 Variables de Entorno

### Requeridas

| Variable           | Descripción     | Ejemplo         |
|--------------------|-----------------|-----------------|
| `DB_DATABASE`      | Nombre de la BD | `komorebi_db`   |
| `DB_USERNAME`      | Usuario MySQL   | `komorebi_user` |
| `DB_PASSWORD`      | Password MySQL  | `changeme`      |
| `DB_ROOT_PASSWORD` | Root password   | `root_secret`   |
| `REDIS_PASSWORD`   | Password Redis  | `redis_secret`  |

### Opcionales (entrypoint)

| Variable          | Default | Descripción                                    |
|-------------------|---------|------------------------------------------------|
| `SKIP_MIGRATIONS` | `0`     | Si `1`, salta migraciones al iniciar           |
| `SKIP_SEEDERS`    | `0`     | Si `1`, salta seeders al iniciar               |
| `FORCE_SEED`      | `0`     | Si `1`, fuerza re-seed aunque ya existan datos |

### Nota sobre perfiles de desarrollo

El proyecto ya no expone helpers "dev" por defecto en el código de producción. Evita añadir `COMPOSE_PROFILES=dev` a tu `.env` en entornos que puedan ser accesibles públicamente. Usa perfiles dev localmente solo en entornos aislados y controla su uso con políticas de CI/CD.

## 📁 Estructura de Archivos

```
docker/
├── apache/
│   └── vhost.conf          # Virtual host Apache
├── mysql/
│   └── init.sql            # Script inicial (solo config, sin tablas)
└── php/
    ├── Dockerfile          # Imagen PHP 8.4 + Apache
    ├── custom.ini          # Configuración PHP
    └── docker-entrypoint.sh # Script de inicio
```

## 🔄 Flujo de Inicialización

Cuando el contenedor `app` inicia, el entrypoint ejecuta:

1. **Composer install** - Instala dependencias si no existen
2. **Preparar storage** - Crea directorios y configura permisos
3. **Esperar MySQL** - Verifica conexión a BD
4. **Migraciones** - Ejecuta `migrations/run_migrations.php`
5. **Seeders** - Ejecuta `DatabaseSeeder` (solo primera vez)
6. **Permisos finales** - Configura ownership para www-data

### Control de seeders

Los seeders solo se ejecutan **una vez** (se crea archivo `.seeded` en storage).

Para forzar re-seed:

```bash
# Opción 1: Variable de entorno
FORCE_SEED=1 docker compose up -d

# Opción 2: Eliminar lock file
docker compose exec app rm /var/www/html/storage/.seeded
docker compose restart app

# Opción 3: Ejecutar manualmente
docker compose exec app php -r "require 'vendor/autoload.php'; (new App\Core\DatabaseSeeder())->run();"
```

## 🐛 Troubleshooting

### Error: "no configuration file provided: not found"

Este error generalmente indica problemas con la sintaxis del archivo `docker-compose.yml` o que estás en el directorio incorrecto.

**Solución:**

```powershell
# 1. Verifica que estés en el directorio raíz del proyecto
cd F:\Proyectos\Web\komorebi

# 2. Verifica que docker-compose.yml existe
Test-Path .\docker-compose.yml

# 3. Valida la configuración
docker compose config --quiet

# 4. Si todo está bien, inicia los servicios
docker compose --profile dev up -d
```

**Nota sobre `version:`**: Docker Compose V2 no requiere la declaración `version:` al inicio del archivo. Si ves una advertencia sobre esto, es normal y puedes ignorarla o remover la línea.

### Error: Docker Compose no reconocido (Windows)

Si obtienes "docker: 'compose' is not a docker command":

**Solución:**

```powershell
# Verificar versión de Docker Desktop
docker --version

# Docker Desktop incluye Compose V2 por defecto
# Si usas una versión antigua, actualiza Docker Desktop

# Si tienes docker-compose (V1), usa:
docker-compose --version
# Pero te recomendamos actualizar a V2
```

### Error: "Table already exists"

Las migraciones usan `CREATE TABLE IF NOT EXISTS`. Si hay conflictos:

```bash
# Reset completo de BD
docker compose exec db mysql -u root -p -e "DROP DATABASE komorebi_db; CREATE DATABASE komorebi_db;"
docker compose restart app
```

### Error: MySQL connection refused

Esperar a que MySQL esté healthy:

```bash
docker compose ps  # Verificar estado
docker compose logs db  # Ver logs de MySQL
```

### Permisos en storage

```bash
docker compose exec app chown -R www-data:www-data /var/www/html/storage
docker compose exec app chmod -R 775 /var/www/html/storage
```

### Ver logs de todos los servicios

```bash
docker compose logs -f
```

### Reconstruir imagen PHP

```bash
docker compose build --no-cache app
docker compose up -d
```

## 🔒 Seguridad

- **Nunca** commitear `.env` con passwords reales
- Los servicios `pma` y `redis_commander` solo están en profile `dev`
- En producción, usar secrets management (Docker Swarm, Kubernetes)
- Configurar `SESSION_SECURE=true` en producción (requiere HTTPS)

## 📝 Notas

- MySQL y Redis tienen volúmenes persistentes (`db_data`, `redis_data`)
- El volumen `komorebi_storage` persiste uploads y cache
- Los workers (`queue`, `telegram_bot`) son placeholders - implementar cuando se necesiten
