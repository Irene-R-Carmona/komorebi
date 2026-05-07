-- 014_staff_shifts.sql
-- Migración: Gestión de turnos de staff

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS staff_shifts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL COMMENT 'Staff member asignado al turno',
    cafe_id BIGINT UNSIGNED NOT NULL COMMENT 'Café donde se realiza el turno',
    shift_date DATE NOT NULL COMMENT 'Fecha del turno',
    shift_start TIME NOT NULL COMMENT 'Hora de inicio del turno',
    shift_end TIME NOT NULL COMMENT 'Hora de fin del turno',
    notes TEXT COMMENT 'Notas adicionales del turno',
    created_by BIGINT UNSIGNED COMMENT 'Manager que creó el turno',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL COMMENT 'Soft delete',
    CONSTRAINT fk_staff_shifts_users FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_staff_shifts_cafes FOREIGN KEY (cafe_id) REFERENCES cafes(id) ON DELETE CASCADE,
    CONSTRAINT fk_staff_shifts_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_staff_shifts_user_date (user_id, shift_date),
    INDEX idx_staff_shifts_cafe_date (cafe_id, shift_date),
    INDEX idx_staff_shifts_date_range (shift_date, shift_start, shift_end)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Turnos de staff para gestión de horarios por managers';

SET FOREIGN_KEY_CHECKS = 1;
