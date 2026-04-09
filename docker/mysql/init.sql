-- ============================================================================
-- KOMOREBI CAFÉ - DOCKER MYSQL INIT
-- ============================================================================
-- Este archivo solo configura la BD. Las tablas se crean mediante migraciones.
-- NO añadir DDL de tablas aquí - usar migrations/*.sql en su lugar.
-- ============================================================================

-- Configuración de caracteres
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- Mensaje informativo
SELECT 'MySQL inicializado. Las tablas se crearán mediante migraciones PHP.' AS message;
