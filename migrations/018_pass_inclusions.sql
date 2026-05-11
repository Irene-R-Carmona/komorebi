-- ============================================================================
-- MIGRACIÓN 034: INCLUSIONES DE PASES (pass_inclusions)
-- ============================================================================
-- Objetivo: Definir qué categorías de productos están incluidas en cada pase,
--           con cantidad por pax y precio máximo elegible.
-- Dependencias: 004_reservations.sql (products, menu_categories)
-- MySQL: 8.0+ / 8.4+ Compatible
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS pass_inclusions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pass_product_id BIGINT UNSIGNED NOT NULL COMMENT 'FK → products.id (product_type = pass)',
    category_id BIGINT UNSIGNED NOT NULL COMMENT 'FK → menu_categories.id',
    quantity_per_pax TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Unidades incluidas por persona',
    max_unit_price INT UNSIGNED NULL COMMENT 'Precio máximo en céntimos (NULL = sin límite)',
    INDEX idx_pass_inclusions_pass (pass_product_id),
    INDEX idx_pass_inclusions_category (category_id),
    UNIQUE KEY uk_pass_category (pass_product_id, category_id),
    CONSTRAINT fk_pass_inclusions_product FOREIGN KEY (pass_product_id) REFERENCES products (id) ON DELETE CASCADE,
    CONSTRAINT fk_pass_inclusions_category FOREIGN KEY (category_id) REFERENCES menu_categories (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci
  COMMENT = 'Categorías incluidas en cada tipo de pase, con cantidad por pax y precio máximo';

SET FOREIGN_KEY_CHECKS = 1;
