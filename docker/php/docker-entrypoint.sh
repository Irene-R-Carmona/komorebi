#!/bin/sh
set -eu

APP_DIR="/app"
STORAGE_DIR="$APP_DIR/storage"
LOG_FILE="$STORAGE_DIR/logs/init-migrations.log"
TMP_LOG="/tmp/init-migrations.log"

printf '[INIT] Preparando entorno...\n'

mkdir -p "$STORAGE_DIR/uploads" "$STORAGE_DIR/logs" "$STORAGE_DIR/cache"
chmod -R 775 "$STORAGE_DIR" 2>/dev/null || true
chown -R www-data:www-data "$STORAGE_DIR" 2>/dev/null || true

# En volúmenes bind-mount de Windows/WSL2 chown puede fallar.
# Verificamos si podemos escribir en el log de storage; si no, usamos /tmp.
if touch "$LOG_FILE" 2>/dev/null; then
    ACTIVE_LOG="$LOG_FILE"
else
    ACTIVE_LOG="$TMP_LOG"
    printf '[WARN] Sin acceso de escritura a %s — logs de init en %s (siempre visible en docker logs)\n' "$LOG_FILE" "$ACTIVE_LOG"
fi
: > "$ACTIVE_LOG"  # truncar/crear en blanco al inicio

if [ "${SKIP_COMPOSER:-0}" != "1" ]; then
    if [ ! -f "$APP_DIR/vendor/autoload.php" ]; then
        if command -v composer >/dev/null 2>&1; then
            printf '[INIT] vendor/autoload.php no encontrado. Ejecutando composer install...\n'
            composer install --no-interaction --prefer-dist --optimize-autoloader >> "$ACTIVE_LOG" 2>&1 || {
                printf '[ERROR] composer install falló. Ver %s\n' "$ACTIVE_LOG" >&2
                exit 1
            }
        else
            printf '[ERROR] Composer no disponible en la imagen y vendor/ no existe. La imagen está rota.\n' >&2
            exit 1
        fi
    fi
fi

printf '[INIT] Esperando MySQL...\n'
until php -r "
try {
    new PDO(
        'mysql:host=' . getenv('DB_HOST') . ';dbname=' . getenv('DB_DATABASE'),
        getenv('DB_USERNAME'),
        getenv('DB_PASSWORD')
    );
    exit(0);
} catch (Exception \$e) { exit(1); }
"; do
    sleep 2
done

printf '[INIT] Base de datos disponible.\n'

# Ejecutar migraciones y seeders a menos que se indique lo contrario
if [ "${SKIP_MIGRATIONS:-0}" = "0" ]; then
    printf '[INIT] Ejecutando migraciones y seeders (modo automático)...\n'

    MIGRATE_SCRIPT="$APP_DIR/scripts/apply-db.php"

    if [ ! -f "$MIGRATE_SCRIPT" ]; then
        printf '[WARN] Script de migraciones no encontrado: %s\n' "$MIGRATE_SCRIPT"
        printf '[WARN] Saltando la ejecución de migraciones. Para forzar, coloque el script en %s o establezca SKIP_MIGRATIONS=0\n' "$MIGRATE_SCRIPT"
    else
        # Número de intentos configurable vía env MIGRATE_ATTEMPTS (por defecto 3)
        attempts=0
        max_attempts=${MIGRATE_ATTEMPTS:-3}

        while [ "$attempts" -lt "$max_attempts" ]; do
            attempts=$((attempts + 1))
            printf '[INIT] Ejecutando %s (intento %d/%d)...\n' "$MIGRATE_SCRIPT" "$attempts" "$max_attempts"

            # Ejecutar y registrar salida en /tmp (siempre escribible), luego persistir
            if php "$MIGRATE_SCRIPT" --force > /tmp/migrate-attempt.log 2>&1; then
                cat /tmp/migrate-attempt.log >> "$ACTIVE_LOG" 2>/dev/null || true
                cat /tmp/migrate-attempt.log  # stdout → docker logs
                # Si usamos /tmp, intentar copiar al log permanente para trazabilidad
                if [ "$ACTIVE_LOG" = "$TMP_LOG" ]; then
                    cp "$TMP_LOG" "$LOG_FILE" 2>/dev/null || true
                fi
                printf '[INIT] Migraciones y seeders ejecutados correctamente.\n'
                break
            else
                rc=$?
                cat /tmp/migrate-attempt.log >> "$ACTIVE_LOG" 2>/dev/null || true
                cat /tmp/migrate-attempt.log >&2  # stderr → docker logs
                printf '[ERROR] Falló la ejecución del script (código %d). Ver: %s\n' "$rc" "$ACTIVE_LOG" >&2

                if [ "$attempts" -ge "$max_attempts" ]; then
                    printf '[ERROR] Alcanzado máximo de intentos (%d). Puede omitir con SKIP_MIGRATIONS=1 si es intencional.\n' "$max_attempts" >&2
                    exit "$rc"
                fi

                # Espera exponencial simple entre reintentos
                sleep_seconds=$((attempts * 2))
                printf '[INIT] Reintentando en %d segundos...\n' "$sleep_seconds"
                sleep "$sleep_seconds"
            fi
        done
    fi
else
    printf '[INIT] SKIP_MIGRATIONS activo — saltando migraciones.\n'
fi

printf '[INIT] Entorno listo. Iniciando servidor...\n'
exec "$@"
