# Guía de Despliegue — Komorebi Café

## Requisitos previos

- Docker 24+ y Docker Compose v2 (incluido en Docker Desktop)
- Git
- No se requiere PHP ni extensiones instaladas en el host

## Instalación inicial

```bash
git clone <repo-url>
cd komorebi
cp .env.example .env
# Editar .env con los valores de producción
php bin/generate-secrets.php   # genera APP_KEY, SESSION_SECRET, CSRF_SECRET
make build
make up
```

Las migraciones de base de datos se ejecutan automáticamente en el primer arranque a través de `docker-entrypoint.sh`.
No es necesario ejecutar `make db-migrate` manualmente en una instalación limpia.

## Variables de entorno críticas para producción

| Variable             | Requerida | Descripción                                                |
|----------------------|-----------|------------------------------------------------------------|
| `APP_ENV`            | Sí        | Establecer en `production`                                 |
| `APP_KEY`            | Sí        | Clave de cifrado (generada con `bin/generate-secrets.php`) |
| `DB_ROOT_PASSWORD`   | Sí        | Contraseña root de MySQL                                   |
| `DB_PASSWORD`        | Sí        | Contraseña del usuario de aplicación de MySQL              |
| `REDIS_PASSWORD`     | Sí        | Contraseña de Redis                                        |
| `MAIL_HOST`          | No        | Servidor SMTP                                              |
| `MAIL_PORT`          | No        | Puerto SMTP (por defecto: 587)                             |
| `MAIL_USERNAME`      | No        | Usuario SMTP                                               |
| `MAIL_PASSWORD`      | No        | Contraseña SMTP                                            |
| `TELEGRAM_BOT_TOKEN` | No        | Token del bot de Telegram (opcional)                       |

> **Seguridad:** Los secretos pueden pasarse como Docker Secrets montados en `/run/secrets/KEY_NAME` para mayor
> seguridad en producción. `App\Core\SecretLoader::require('key_name')` busca primero la variable de entorno `KEY_NAME` y
> luego el archivo `/run/secrets/key_name`.

## Verificar el estado

```bash
make ps                             # ver estado de todos los contenedores
docker compose ps                   # con columna Health
curl http://localhost:8080/health   # debe devolver HTTP 200
```

> Los servicios `db` (MySQL) y `cache` (Redis) tienen healthchecks configurados. El servicio `app` no arranca hasta que
> ambos estén sanos (`healthy`).

## Actualización (rolling deploy)

```bash
git pull
make build
docker compose up -d   # reemplaza los contenedores (breve interrupción)
make db-migrate        # aplica las nuevas migraciones si las hay
```

## Backups

```bash
make db-backup   # crea backups/backup_YYYYMMDD_HHMMSS.sql
```

Restaurar un backup:

```bash
docker compose exec -i db mysql -u root -p komorebi < backups/FILENAME.sql
```

## Rollback

1. `docker compose down`
2. `git checkout <etiqueta-anterior>`
3. `make build && make up`
4. Restaurar el backup de la base de datos si el esquema cambió (ver sección Backups)

## Logs y monitorización

```bash
make logs-app                              # logs del servidor PHP/app
docker compose logs email_worker           # logs de un worker específico
docker compose logs -f notification_worker # seguimiento en tiempo real
```

Los logs van a stdout siguiendo el principio XI de la metodología 12-Factor y son capturados por Docker.

## Servicios Docker Compose

| Servicio              | Descripción                                              |
|-----------------------|----------------------------------------------------------|
| `app`                 | FrankenPHP + Caddy, puerto 8080                          |
| `db`                  | MySQL 8.4                                                |
| `cache`               | Redis 8 (Alpine)                                         |
| `queue`               | Worker de cola por defecto (`bin/worker.php`)            |
| `email_worker`        | Worker de cola de emails (`bin/email-worker.php`)        |
| `notification_worker` | Worker de notificaciones (`bin/notification-worker.php`) |
| `telegram_bot`        | Bot de Telegram                                          |
| `mailpit`             | Captura de emails en desarrollo (puerto 8025)            |

## Limpiar recursos

```bash
make clean    # limpia cache y logs dentro del contenedor
make down     # detiene los contenedores (los datos se preservan)
make down-v   # ⚠️ elimina también los volúmenes (DESTRUCTIVO, pide confirmación)
```
