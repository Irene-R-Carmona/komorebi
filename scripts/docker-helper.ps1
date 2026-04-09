# ==============================================================================
# KOMOREBI v2 - DOCKER HELPER SCRIPT (WINDOWS - PowerShell)
# ==============================================================================
# Propósito: Facilitar operaciones comunes con Docker en Windows
# Uso: .\docker-helper.ps1 [comando] [argumentos]

param(
    [string]$Command = "help",
    [string[]]$Arguments = @()
)

# ============================================================================
# FUNCIONES AUXILIARES
# ============================================================================

function Write-Header {
    param([string]$Message)
    Write-Host ""
    Write-Host "╔════════════════════════════════════════════════════════════════╗" -ForegroundColor Cyan
    Write-Host "║ $Message" -ForegroundColor Cyan
    Write-Host "╚════════════════════════════════════════════════════════════════╝" -ForegroundColor Cyan
    Write-Host ""
}

function Write-Success {
    param([string]$Message)
    Write-Host "✅ $Message" -ForegroundColor Green
}

function Write-Error_ {
    param([string]$Message)
    Write-Host "❌ $Message" -ForegroundColor Red
}

function Write-Warning_ {
    param([string]$Message)
    Write-Host "⚠️  $Message" -ForegroundColor Yellow
}

function Write-Info {
    param([string]$Message)
    Write-Host "ℹ️  $Message" -ForegroundColor Cyan
}

# ============================================================================
# COMANDOS
# ============================================================================

function Show-Help {
    Write-Host @"

KOMOREBI v2 - DOCKER HELPER

COMANDOS DE CICLO DE VIDA:
  .\docker-helper.ps1 start           Inicia los contenedores
  .\docker-helper.ps1 stop            Detiene los contenedores
  .\docker-helper.ps1 restart         Reinicia los contenedores
  .\docker-helper.ps1 rebuild         Rebuilda las imagenes Docker
  .\docker-helper.ps1 reset           Reinicia todo (borra volumenes)

INFORMACION Y MONITOREO:
  .\docker-helper.ps1 status          Muestra estado de contenedores
  .\docker-helper.ps1 logs [service]  Ve logs (ej: logs app, logs db)
  .\docker-helper.ps1 stats           Ver uso de recursos
  .\docker-helper.ps1 ps              Lista contenedores

BASE DE DATOS:
  .\docker-helper.ps1 db-shell        Acceder a MySQL shell
  .\docker-helper.ps1 db-backup       Hacer backup de la BD
  .\docker-helper.ps1 db-restore FILE Restaurar desde backup
  .\docker-helper.ps1 db-migrate      Ejecutar migraciones
  .\docker-helper.ps1 db-seed         Ejecutar seeders
  .\docker-helper.ps1 setup           Ejecuta migrate + seed + permisos

UTILIDADES:
  .\docker-helper.ps1 bash [service]  Bash shell en contenedor
  .\docker-helper.ps1 composer ARGS   Ejecutar composer
  .\docker-helper.ps1 php COMMAND     Ejecutar comando PHP
  .\docker-helper.ps1 redis-cli       Acceder a Redis CLI
  .\docker-helper.ps1 clean-cache     Limpiar cache
  .\docker-helper.ps1 fix-permissions Reparar permisos

DESARROLLO:
    (development commands removed)

EXTRAS:
  .\docker-helper.ps1 mailpit         Abre Mailpit
  .\docker-helper.ps1 phpmyadmin      Abre PhpMyAdmin
  .\docker-helper.ps1 redis-commander Abre Redis Commander

"@
}

function Invoke-Start {
    Write-Header "Iniciando servicios..."
    docker-compose up -d
    Write-Success "Servicios iniciados"
    Start-Sleep -Seconds 2
    Invoke-Status
}

function Invoke-Stop {
    Write-Header "Deteniendo servicios..."
    docker-compose down
    Write-Success "Servicios detenidos"
}

function Invoke-Restart {
    Write-Header "Reiniciando servicios..."
    docker-compose restart
    Write-Success "Servicios reiniciados"
    Start-Sleep -Seconds 2
    Invoke-Status
}

function Invoke-Rebuild {
    Write-Header "Rebuild de imágenes..."
    $noCache = Read-Host "¿Incluir --no-cache? (s/n)"

    if ($noCache -eq "s" -or $noCache -eq "S") {
        docker-compose build --no-cache
    }
    else {
        docker-compose build
    }

    Write-Success "Build completado"
}

function Invoke-Reset {
    Write-Warning_ "Esta acción eliminará TODOS los datos (volúmenes)"
    $confirm = Read-Host "¿Continuar? (s/n)"

    if ($confirm -eq "s" -or $confirm -eq "S") {
        Write-Header "Eliminando todo..."
        docker-compose down -v
        docker-compose build
        docker-compose up -d
        Write-Success "Reset completado"
    }
    else {
        Write-Info "Operación cancelada"
    }
}

function Invoke-Status {
    Write-Header "Estado de servicios"
    docker-compose ps
}

function Invoke-Logs {
    if ($Arguments.Count -eq 0) {
        docker-compose logs -f app
    }
    else {
        docker-compose logs @Arguments
    }
}

function Invoke-Stats {
    Write-Header "Estadísticas de recursos"
    docker stats --no-stream
}

function Invoke-PS {
    docker ps -a
}

function Invoke-DBShell {
    Write-Info "Conectando a MySQL..."
    docker-compose exec db mysql -u root -p -D komorebi_db
}

function Invoke-DBBackup {
    $timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
    $backupFile = "backup_db_$timestamp.sql.gz"

    Write-Header "Creando backup de BD..."

    # Leer contraseña del .env
    $envContent = Get-Content .env
    $dbPassword = ($envContent | Select-String "DB_ROOT_PASSWORD=").ToString().Split("=")[1]

    # Crear backup (necesita ejecutable mysqldump)
    docker-compose exec -T db mysqldump -u root -p"$dbPassword" komorebi_db | gzip > $backupFile

    Write-Success "Backup creado: $backupFile"
    Get-Item $backupFile | Format-Table Name, Length
}

function Invoke-DBRestore {
    if ($Arguments.Count -eq 0) {
        Write-Error_ "Uso: .\docker-helper.ps1 db-restore <archivo.sql.gz>"
        exit 1
    }

    $file = $Arguments[0]

    if (-not (Test-Path $file)) {
        Write-Error_ "Archivo no encontrado: $file"
        exit 1
    }

    Write-Warning_ "Esto sobreescribirá la BD actual"
    $confirm = Read-Host "¿Continuar? (s/n)"

    if ($confirm -eq "s" -or $confirm -eq "S") {
        Write-Header "Restaurando desde $file..."

        $envContent = Get-Content .env
        $dbPassword = ($envContent | Select-String "DB_ROOT_PASSWORD=").ToString().Split("=")[1]

        Get-Content $file | docker-compose exec -T db mysql -u root -p"$dbPassword" komorebi_db
        Write-Success "Restauración completada"
    }
    else {
        Write-Info "Operación cancelada"
    }
}

function Invoke-DBMigrate {
    Write-Header "Ejecutando migraciones..."
    docker-compose exec app php migrations/run_migrations.php
}

function Invoke-DBSeed {
    Write-Header "Ejecutando seeders..."
    docker-compose exec app php -r "require 'vendor/autoload.php'; (new App\Core\DatabaseSeeder())->run();"
    Write-Success "Seeders completados"
}

function Invoke-Bash {
    $service = if ($Arguments.Count -gt 0) { $Arguments[0] } else { "app" }
    Write-Info "Bash shell en $service"
    docker-compose exec $service bash
}

function Invoke-Composer {
    docker-compose exec app composer @Arguments
}

function Invoke-PHP {
    docker-compose exec app php @Arguments
}

function Invoke-RedisCLI {
    Write-Info "Redis CLI"

    $envContent = Get-Content .env
    $redisPassword = ($envContent | Select-String "REDIS_PASSWORD=").ToString().Split("=")[1]

    docker-compose exec cache redis-cli -a "$redisPassword"
}

function Invoke-CleanCache {
    Write-Header "Limpiando cache..."
    docker-compose exec app rm -rf /var/www/html/storage/cache/*
    docker-compose exec app rm -rf /var/www/html/storage/logs/*.log
    Write-Success "Cache limpiado"
}

function Invoke-FixPermissions {
    Write-Header "Reparando permisos..."
    docker-compose exec app chown -R www-data:www-data /var/www/html/storage
    docker-compose exec app chmod -R 775 /var/www/html/storage
    Write-Success "Permisos reparados"
}

# Dev commands removed for security.

function Invoke-Setup {
    Write-Header "Configuración inicial completa..."
    Write-Info "1/3 Ejecutando migraciones..."
    docker-compose exec app php migrations/run_migrations.php
    Write-Info "2/3 Ejecutando seeders..."
    docker-compose exec app php -r "require 'vendor/autoload.php'; (new App\Core\DatabaseSeeder())->run();"
    Write-Info "3/3 Configurando permisos..."
    docker-compose exec app chown -R www-data:www-data /var/www/html/storage
    docker-compose exec app chmod -R 775 /var/www/html/storage
    Write-Success "Setup completado"
}

# Dev command removed for security.

function Invoke-OpenMailpit {
    Write-Info "Abriendo Mailpit..."
    Start-Process "http://localhost:8025"
}

function Invoke-OpenPhpMyAdmin {
    Write-Info "Abriendo PhpMyAdmin..."
    Start-Process "http://localhost:8081"
}

function Invoke-OpenRedisCommander {
    Write-Info "Abriendo Redis Commander..."
    Start-Process "http://localhost:8082"
}

# ============================================================================
# MAIN
# ============================================================================

switch ($Command.ToLower()) {
    "help" { Show-Help }
    "start" { Invoke-Start }
    "stop" { Invoke-Stop }
    "restart" { Invoke-Restart }
    "rebuild" { Invoke-Rebuild }
    "reset" { Invoke-Reset }
    "status" { Invoke-Status }
    "logs" { Invoke-Logs }
    "stats" { Invoke-Stats }
    "ps" { Invoke-PS }
    "db-shell" { Invoke-DBShell }
    "db-backup" { Invoke-DBBackup }
    "db-restore" { Invoke-DBRestore }
    "db-migrate" { Invoke-DBMigrate }
    "db-seed" { Invoke-DBSeed }
    "bash" { Invoke-Bash }
    "composer" { Invoke-Composer }
    "php" { Invoke-PHP }
    "redis-cli" { Invoke-RedisCLI }
    "clean-cache" { Invoke-CleanCache }
    "fix-permissions" { Invoke-FixPermissions }
    # dev commands removed
    "setup" { Invoke-Setup }
    "mailpit" { Invoke-OpenMailpit }
    "phpmyadmin" { Invoke-OpenPhpMyAdmin }
    "redis-commander" { Invoke-OpenRedisCommander }
    default {
        Write-Error_ "Comando desconocido: $Command"
        Write-Host "Usa '.\docker-helper.ps1 help' para ver opciones disponibles"
        exit 1
    }
}
