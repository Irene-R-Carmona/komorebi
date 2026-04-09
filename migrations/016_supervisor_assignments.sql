CREATE TABLE IF NOT EXISTS supervisor_assignments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    supervisor_id INT UNSIGNED NOT NULL,
    reservation_id INT UNSIGNED NOT NULL,
    table_code VARCHAR(20) NOT NULL,
    cafe_id INT UNSIGNED NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (supervisor_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE,
    FOREIGN KEY (cafe_id) REFERENCES cafes(id) ON DELETE CASCADE,
    INDEX idx_supervisor_active (supervisor_id, is_active),
    INDEX idx_cafe_active (cafe_id, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
