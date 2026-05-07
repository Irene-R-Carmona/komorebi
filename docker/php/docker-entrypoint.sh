#!/bin/bash
set -eu

APP_DIR="/app"
STORAGE_DIR="$APP_DIR/storage"
LOG_FILE="$STORAGE_DIR/logs/init-migrations.log"
TMP_LOG="/tmp/init-migrations.log"

# ── Timestamp helper ────────────────────────────────────────────
log() {
    printf '[INIT][%s] %s\n' "$(date +%H:%M:%S)" "$1"
}

log "==================================================="
log "  Komorebi Cafe -- Bootstrap del contenedor"
log "  Servicio: ${COMPOSE_SERVICE:-app} | Entorno: ${APP_ENV:-?}"
log "==================================================="

log "Creando directorios de almacenamiento..."
mkdir -p "$STORAGE_DIR/uploads/avatars" "$STORAGE_DIR/uploads/animals" "$STORAGE_DIR/logs" "$STORAGE_DIR/cache" "$STORAGE_DIR/cache/di"
chown -R www-data:www-data "$STORAGE_DIR" 2>/dev/null || true
chmod -R 755 "$STORAGE_DIR" 2>/dev/null || true
chmod -R 775 "$STORAGE_DIR/uploads" "$STORAGE_DIR/logs" "$STORAGE_DIR/cache" 2>/dev/null || true
log "Directorios listos."

# En volúmenes bind-mount de Windows/WSL2 chown puede fallar.
# Verificamos si podemos escribir en el log de storage; si no, usamos /tmp.
if touch "$LOG_FILE" 2>/dev/null; then
    ACTIVE_LOG="$LOG_FILE"
    log "Log de init: $LOG_FILE"
else
    ACTIVE_LOG="$TMP_LOG"
    log "WARN: Sin acceso de escritura a $LOG_FILE — usando $ACTIVE_LOG"
fi
: > "$ACTIVE_LOG"

# ── PASO 1/4: Dependencias PHP ──────────────────────────────────
if [ "${SKIP_COMPOSER:-0}" != "1" ]; then
    if [ ! -f "$APP_DIR/vendor/autoload.php" ]; then
        if command -v composer >/dev/null 2>&1; then
            log "PASO 1/4: vendor/ ausente — ejecutando composer install..."
            log "          ADVERTENCIA: Primera ejecución puede tardar 2-4 min."
            log "          Toda la salida de Composer aparece a continuacion:"
            log "--------------------------------------------------------------"
            composer install --no-interaction --prefer-dist --optimize-autoloader 2>&1 | tee -a "$ACTIVE_LOG"
            composer_rc=${PIPESTATUS[0]}
            log "--------------------------------------------------------------"
            if [ "$composer_rc" -ne 0 ]; then
                log "ERROR: composer install falló (código $composer_rc)."
                log "ERROR: Ver log completo en: $ACTIVE_LOG"
                exit "$composer_rc"
            fi
            log "PASO 1/4: OK — Dependencias instaladas."
        else
            log "ERROR: Composer no disponible y vendor/ no existe. Imagen rota."
            exit 1
        fi
    else
        log "PASO 1/4: OK — vendor/autoload.php existente, omitiendo composer install."
    fi
else
    log "PASO 1/4: SKIP — SKIP_COMPOSER=1 activo."
fi

# ── PASO 2/4: Esperar MySQL ─────────────────────────────────────
log "PASO 2/4: Esperando MySQL en ${DB_HOST:-db}:${DB_PORT:-3306} (bd: ${DB_DATABASE:-?})..."
log "          Timeout máximo: 120s | Intervalo: 2s"
attempt=0
max_wait=60
until php -r "
try {
    new PDO(
        'mysql:host=' . getenv('DB_HOST') . ';port=' . (getenv('DB_PORT') ?: 3306) . ';dbname=' . getenv('DB_DATABASE'),
        getenv('DB_USERNAME'),
        getenv('DB_PASSWORD')
    );
    exit(0);
} catch (Exception \$e) { exit(1); }
" 2>/dev/null; do
    attempt=$((attempt + 1))
    if [ "$attempt" -ge "$max_wait" ]; then
        log "ERROR: MySQL no respondió en $((max_wait * 2))s ($max_wait intentos). Abortando."
        log "ERROR: Verifica DB_HOST=${DB_HOST:-db}, DB_DATABASE=${DB_DATABASE:-?}, DB_USERNAME=${DB_USERNAME:-?}"
        exit 1
    fi
    # Mostrar progreso cada 5 intentos (≈10s) para que no parezca que se ha colgado
    if [ $((attempt % 5)) -eq 0 ]; then
        log "          Intento $attempt/$max_wait — MySQL aún no disponible, esperando..."
    fi
    sleep 2
done
log "PASO 2/4: OK — MySQL disponible (tras $attempt intento(s))."

# ── PASO 3/4: Migraciones y seeders ────────────────────────────
if [ "${SKIP_MIGRATIONS:-0}" = "0" ]; then
    log "PASO 3/4: Ejecutando migraciones y seeders..."
    MIGRATE_SCRIPT="$APP_DIR/scripts/apply-db.php"

    if [ ! -f "$MIGRATE_SCRIPT" ]; then
        log "WARN: Script no encontrado: $MIGRATE_SCRIPT — saltando migraciones."
    else
        attempts=0
        max_attempts=${MIGRATE_ATTEMPTS:-3}

        while [ "$attempts" -lt "$max_attempts" ]; do
            attempts=$((attempts + 1))
            log "PASO 3/4: Ejecutando apply-db.php (intento $attempts/$max_attempts)..."
            log "--------------------------------------------------------------"

            # Ejecutar con salida en tiempo real (tee) y capturar exit code con PIPESTATUS
            # --force: salta el prompt interactivo (no disponible en contenedor sin TTY).
            # Las migraciones siguen siendo idempotentes: no aplica nada ya aplicado.
            php "$MIGRATE_SCRIPT" --force 2>&1 | tee /tmp/migrate-attempt.log
            rc=${PIPESTATUS[0]:-1}

            log "--------------------------------------------------------------"
            cat /tmp/migrate-attempt.log >> "$ACTIVE_LOG" 2>/dev/null || true

            if [ "$ACTIVE_LOG" = "$TMP_LOG" ]; then
                cp "$TMP_LOG" "$LOG_FILE" 2>/dev/null || true
            fi

            if [ "$rc" -eq 0 ]; then
                log "PASO 3/4: OK — Migraciones y seeders completados."
                break
            else
                log "ERROR: apply-db.php finalizó con código $rc."
                if [ "$attempts" -ge "$max_attempts" ]; then
                    log "ERROR: Máximo de intentos ($max_attempts) alcanzado."
                    log "ERROR: Usa SKIP_MIGRATIONS=1 para omitir si es intencional."
                    exit "$rc"
                fi
                sleep_seconds=$((attempts * 2))
                log "PASO 3/4: Reintentando en ${sleep_seconds}s (próximo: intento $((attempts + 1))/$max_attempts)..."
                sleep "$sleep_seconds"
            fi
        done
    fi
else
    log "PASO 3/4: SKIP — SKIP_MIGRATIONS=1 activo."
fi

# ── PASO 4/4: Listo ────────────────────────────────────────────
log "PASO 4/4: Entorno listo. Arrancando proceso principal: $*"
log "==================================================="
exec "$@"
