@echo off
REM -----------------------------------------------------------------------------
REM Script: php-cs-fixer.bat
REM Proyecto: Komorebi Café
REM
REM Descripción:
REM   Wrapper para ejecutar php-cs-fixer dentro del contenedor Docker "app".
REM   Permite que extensiones de VS Code (junstyle.php-cs-fixer) invoquen
REM   php-cs-fixer sin instalarlo en el host Windows.
REM
REM Motivo de uso:
REM   - El proyecto corre 100% en Docker; vendor/ solo existe en el contenedor.
REM   - Compatible con extensiones que requieren un ejecutable php-cs-fixer local.
REM -----------------------------------------------------------------------------

REM Guard: verificar que el contenedor está en ejecución
FOR /F "tokens=*" %%i IN ('docker compose ps app 2^>nul') DO (
    echo %%i | findstr /i "running" >nul 2>&1
    IF NOT ERRORLEVEL 1 GOTO :run
)
echo [Komorebi] El contenedor 'app' no esta en ejecucion. Ejecuta: make dev 1>&2
exit /b 1

:run
docker compose exec -T app vendor/bin/php-cs-fixer %*
