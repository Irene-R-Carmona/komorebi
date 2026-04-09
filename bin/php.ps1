#!/usr/bin/env pwsh
# -----------------------------------------------------------------------------
# Script: php.ps1
# Proyecto: Komorebi Café
#
# Descripción:
#   Wrapper para PowerShell que ejecuta PHP dentro del contenedor Docker "app".
#   Permite que Windows (PowerShell) utilice el PHP del contenedor como si fuera
#   un ejecutable local.
#
# Funcionamiento:
#   - Recoge todos los argumentos y los pasa al comando PHP del contenedor.
#
# Motivo de uso:
#   - Integración con editores/IDEs en Windows.
#   - Evita instalar PHP en el host.
# -----------------------------------------------------------------------------

param(
    [Parameter(ValueFromRemainingArguments = $true)]
    [string[]]$RemainingArgs
)

if ($null -eq $RemainingArgs) { $RemainingArgs = @() }

# Guard: verificar que el contenedor está en ejecución
$status = docker compose ps app 2>$null | Select-String -Pattern 'running' -Quiet
if (-not $status) {
    Write-Error "[Komorebi] El contenedor 'app' no está en ejecución. Ejecuta: make dev"
    exit 1
}

docker compose exec -T app php @RemainingArgs
