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

REM Guard: verificar que el contenedor está en ejecución
FOR /F "tokens=*" %%i IN ('docker compose ps app 2^>nul') DO (
    echo %%i | findstr /i "running" >nul 2>&1
    IF NOT ERRORLEVEL 1 GOTO :run
)
echo [Komorebi] El contenedor 'app' no esta en ejecucion. Ejecuta: make dev 1>&2
exit /b 1

:run
IF "%*"=="" (
    docker compose exec -T app php
) ELSE (
    docker compose exec -T app php %*
)
