@echo off
REM -----------------------------------------------------------------------------
REM Script: phpunit.bat
REM Proyecto: Komorebi Café
REM
REM Descripción:
REM   Wrapper para ejecutar phpunit dentro del contenedor Docker "app".
REM   Permite que extensiones de VS Code (PHPUnit Test Explorer) invoquen
REM   phpunit sin instalarlo en el host Windows.
REM
REM Motivo de uso:
REM   - El proyecto corre 100% en Docker; vendor/ y la DB solo existen en
REM     el contenedor / red Docker.
REM   - Compatible con extensiones que requieren un ejecutable phpunit local.
REM -----------------------------------------------------------------------------

REM Guard: verificar que el contenedor está en ejecución
FOR /F "tokens=*" %%i IN ('docker compose ps app 2^>nul') DO (
    echo %%i | findstr /i "running" >nul 2>&1
    IF NOT ERRORLEVEL 1 GOTO :run
)
echo [Komorebi] El contenedor 'app' no esta en ejecucion. Ejecuta: make dev 1>&2
exit /b 1

:run
docker compose exec -T app vendor/bin/phpunit %*
