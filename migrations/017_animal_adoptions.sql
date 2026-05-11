-- ============================================================================
-- MIGRACIÓN 033 — Módulo de adopciones de animales
-- ============================================================================
-- Plan 6: infraestructura DB completa para el ciclo de adopciones.
-- NOTA: Las columnas `birth_date` y `age_years` fueron añadidas en
--       migration 031. Los campos `species_type` (no `species`) y la ausencia
--       de `breed` en `animals` se reflejan correctamente en las vistas.
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================================
-- 1. Campos de adopción en animals
-- ============================================================================

ALTER TABLE animals
    ADD COLUMN is_adoptable TINYINT(1) NOT NULL DEFAULT 0
        COMMENT 'El animal está disponible para adopción'
        AFTER deleted_at,
    ADD COLUMN adopted_at DATETIME NULL
        COMMENT 'Fecha en que fue adoptado'
        AFTER is_adoptable,
    ADD COLUMN adopted_by BIGINT UNSIGNED NULL
        COMMENT 'ID del usuario que lo adoptó'
        AFTER adopted_at,
    ADD CONSTRAINT fk_animals_adopted_by
        FOREIGN KEY (adopted_by)
        REFERENCES users (id)
        ON DELETE SET NULL
        ON UPDATE CASCADE;

-- ============================================================================
-- 2. Tabla de solicitudes de adopción
-- ============================================================================

CREATE TABLE IF NOT EXISTS animal_adoption_requests (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    animal_id    BIGINT UNSIGNED NOT NULL,
    user_id      BIGINT UNSIGNED NOT NULL,
    status       ENUM('pending', 'approved', 'rejected', 'withdrawn') NOT NULL DEFAULT 'pending',
    message      TEXT NULL
        COMMENT 'Carta de presentación del solicitante',
    keeper_notes TEXT NULL
        COMMENT 'Notas internas del keeper',
    reviewed_by  BIGINT UNSIGNED NULL
        COMMENT 'ID del keeper que revisó la solicitud',
    reviewed_at  DATETIME NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Un usuario no puede tener dos solicitudes con el mismo estado para el mismo animal
    CONSTRAINT uk_active_adoption_request
        UNIQUE (animal_id, user_id, status),

    CONSTRAINT fk_aar_animal
        FOREIGN KEY (animal_id)
        REFERENCES animals (id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_aar_user
        FOREIGN KEY (user_id)
        REFERENCES users (id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_aar_reviewer
        FOREIGN KEY (reviewed_by)
        REFERENCES users (id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,

    INDEX idx_aar_animal_status (animal_id, status),
    INDEX idx_aar_user (user_id),
    INDEX idx_aar_status (status)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci
  COMMENT = 'Solicitudes de adopción de animales del café';

-- ============================================================================
-- 3. Vista: animales disponibles para adopción
--    Adaptada: usa `species_type` (nombre real de la columna) y no incluye
--    `breed` (columna que no existe en la tabla `animals`).
-- ============================================================================

CREATE OR REPLACE VIEW v_adoptable_animals AS
SELECT
    a.id,
    a.name,
    a.species_type,
    a.description,
    a.image_url  AS photo_url,
    a.cafe_id,
    c.name       AS cafe_name,
    c.city,
    a.birth_date,
    COALESCE(a.age_years, TIMESTAMPDIFF(YEAR, a.birth_date, CURDATE())) AS age_years,
    (
        SELECT COUNT(*)
        FROM animal_adoption_requests aar
        WHERE aar.animal_id = a.id
          AND aar.status = 'pending'
    )            AS pending_requests
FROM animals a
    INNER JOIN cafes c ON c.id = a.cafe_id
WHERE a.is_adoptable = 1
  AND a.adopted_at IS NULL
  AND a.deleted_at IS NULL;

-- ============================================================================
-- 4. Vista: solicitudes pendientes de revisión por el keeper
-- ============================================================================

CREATE OR REPLACE VIEW v_pending_adoptions AS
SELECT
    aar.id,
    aar.status,
    aar.created_at,
    a.id          AS animal_id,
    a.name        AS animal_name,
    a.species_type,
    a.cafe_id,
    u.id          AS applicant_id,
    u.name        AS applicant_name,
    u.email       AS applicant_email,
    aar.message
FROM animal_adoption_requests aar
    INNER JOIN animals a ON a.id = aar.animal_id
    INNER JOIN users u ON u.id = aar.user_id
WHERE aar.status = 'pending'
ORDER BY aar.created_at ASC;

-- ============================================================================
-- 5. Vista: historial de adopciones procesadas
-- ============================================================================

CREATE OR REPLACE VIEW v_adoption_history AS
SELECT
    aar.id,
    aar.status,
    aar.reviewed_at,
    a.id             AS animal_id,
    a.name           AS animal_name,
    a.species_type,
    u.name           AS applicant_name,
    reviewer.name    AS reviewed_by_name,
    aar.keeper_notes
FROM animal_adoption_requests aar
    INNER JOIN animals a ON a.id = aar.animal_id
    INNER JOIN users u ON u.id = aar.user_id
    LEFT JOIN users reviewer ON reviewer.id = aar.reviewed_by
WHERE aar.status IN ('approved', 'rejected', 'withdrawn')
ORDER BY aar.reviewed_at DESC;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- FIN MIGRACIÓN 033
-- ============================================================================
