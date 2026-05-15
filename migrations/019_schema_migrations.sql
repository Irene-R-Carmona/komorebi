-- =============================================================================
-- Migración 019: Tabla de trazabilidad de migraciones aplicadas
-- =============================================================================
-- Registra qué archivos SQL han sido aplicados y cuándo,
-- permitiendo que apply-db.php salte migraciones ya ejecutadas
-- en lugar de re-ejecutar todos los archivos en cada deploy.
--
-- Diseño:
--   - PRIMARY KEY en filename garantiza idempotencia a nivel de tabla.
--   - execution_ms permite detectar migraciones lentas en logs.
--   - La propia inserción en esta tabla se hace DENTRO del mismo deploy,
--     por lo que si apply-db.php se interrumpe, la migración queda sin
--     registrar y se volverá a intentar en el siguiente deploy (safe).
-- =============================================================================

CREATE TABLE IF NOT EXISTS schema_migrations (
    filename     VARCHAR(255)  NOT NULL,
    applied_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    execution_ms INT UNSIGNED  NULL,
    PRIMARY KEY (filename)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
