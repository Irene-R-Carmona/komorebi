# ============================================================================
# Docker Build Script - Optimized for Development
# ============================================================================
# Este script construye la imagen Docker con BuildKit habilitado y optimizaciones
#
# Uso:
#   .\docker-build.ps1                    # Build production
#   .\docker-build.ps1 -Dev               # Build development (con Xdebug)
#   .\docker-build.ps1 -Dev -NoCache      # Rebuild desde cero
# ============================================================================

param(
    [switch]$Dev,
    [switch]$NoCache,
    [switch]$Verbose
)

# Colores para output
function Write-Success { Write-Host $args -ForegroundColor Green }
function Write-Info { Write-Host $args -ForegroundColor Cyan }
function Write-Warning { Write-Host $args -ForegroundColor Yellow }
function Write-Error { Write-Host $args -ForegroundColor Red }

Write-Info "🐳 Komorebi Docker Build Script"
Write-Info "================================"

# Configurar BuildKit
$env:DOCKER_BUILDKIT = "1"
$env:COMPOSE_DOCKER_CLI_BUILD = "1"

# Determinar modo de build
if ($Dev) {
    $buildEnv = "development"
    $installDev = "true"
    Write-Info "📦 Modo: DESARROLLO (con dependencias dev y Xdebug)"
} else {
    $buildEnv = "production"
    $installDev = "false"
    Write-Info "📦 Modo: PRODUCCIÓN (optimizado)"
}

# Construir argumentos
$buildArgs = @(
    "build"
    "--build-arg", "BUILD_ENV=$buildEnv"
    "--build-arg", "INSTALL_DEV=$installDev"
)

if ($NoCache) {
    $buildArgs += "--no-cache"
    Write-Warning "⚠️  No-Cache habilitado: build desde cero (más lento)"
}

if ($Verbose) {
    $buildArgs += "--progress=plain"
    Write-Info "📋 Modo verbose: mostrando detalles completos"
} else {
    $buildArgs += "--progress=auto"
}

$buildArgs += "app"

Write-Info ""
Write-Info "🔨 Construyendo imagen..."
Write-Info "   Build Env: $buildEnv"
Write-Info "   Install Dev: $installDev"
Write-Info "   Cache: $(if ($NoCache) { 'Disabled' } else { 'Enabled' })"
Write-Info ""

# Ejecutar build
$startTime = Get-Date
& docker compose $buildArgs

if ($LASTEXITCODE -eq 0) {
    $duration = (Get-Date) - $startTime
    Write-Success ""
    Write-Success "✅ Build completado exitosamente!"
    Write-Success "⏱️  Tiempo: $($duration.ToString('mm\:ss'))"
    Write-Success ""

    # Mostrar tamaño de imagen
    $imageInfo = docker images komorebi-app:v2 --format "{{.Size}}"
    if ($imageInfo) {
        Write-Info "📊 Tamaño de imagen: $imageInfo"
    }

    Write-Info ""
    Write-Info "🚀 Para levantar los servicios:"
    Write-Info "   docker compose up -d"
    Write-Info ""
    Write-Info "📋 Para ver logs:"
    Write-Info "   docker compose logs -f app"

} else {
    $duration = (Get-Date) - $startTime
    Write-Error ""
    Write-Error "❌ Build falló después de $($duration.ToString('mm\:ss'))"
    Write-Error ""
    Write-Warning "💡 Sugerencias:"
    Write-Warning "   - Verifica que Docker Desktop esté corriendo"
    Write-Warning "   - Revisa logs con: .\docker-build.ps1 -Dev -Verbose"
    Write-Warning "   - Intenta rebuild: .\docker-build.ps1 -Dev -NoCache"
    exit 1
}
