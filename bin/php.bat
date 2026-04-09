@echo off
REM -----------------------------------------------------------------------------
REM Script: php.bat
REM Proyecto: Komorebi Café
REM
REM Descripción:
REM   Wrapper para Windows que permite ejecutar PHP dentro del contenedor Docker
REM   "app" como si estuviera instalado en el sistema local.
REM
REM Funcionamiento:
REM   - Si no se pasan argumentos, abre PHP dentro del contenedor.
REM   - Si se pasan argumentos, los reenvía al comando PHP del contenedor.
REM
REM Motivo de uso:
REM   - Facilita el desarrollo en Windows sin instalar PHP.
REM   - Compatible con editores/IDEs que requieren un ejecutable PHP local.
REM -----------------------------------------------------------------------------

IF "%*"=="" (
    docker compose exec -T app php
) ELSE (
    docker compose exec -T app php %*
)
