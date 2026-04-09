-- ============================================================================
-- MIGRACIÓN 004: RESERVAS, PRODUCTOS Y SISTEMA DE ALÉRGENOS
-- ============================================================================
-- Módulo: Reservas completo + Catálogo de productos + Gestión de alérgenos
-- Dependencias: 001_infrastructure.sql, 002_users_rbac.sql (users, cafes, zones)
-- MySQL: 8.0+ / 8.4+ Compatible
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
-- ============================================
-- 1. ESTRUCTURA BASE: CATEGORÍAS Y PRODUCTOS
-- ============================================
CREATE TABLE IF NOT EXISTS menu_categories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    slug VARCHAR(60) NOT NULL,
    display_order TINYINT UNSIGNED DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_menu_categories_slug (slug),
    INDEX idx_menu_categories_order (display_order)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Categorías del menú (Bebidas, Comidas, Pases, etc.)';
CREATE TABLE IF NOT EXISTS products (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id BIGINT UNSIGNED NOT NULL,
    -- Clasificación y nomenclatura
    product_type ENUM ('item', 'pass') NOT NULL DEFAULT 'item',
    name VARCHAR(100) NOT NULL,
    japanese_name VARCHAR(150) DEFAULT NULL COMMENT 'Nombre en japonés (reemplaza japanese_name)',
    slug VARCHAR(150) DEFAULT NULL COMMENT 'URL amigable',
    description TEXT,
    -- Precio y preparación
    price INT UNSIGNED NOT NULL COMMENT 'Precio en céntimos',
    station ENUM (
        'bar',
        'kitchen_hot',
        'kitchen_cold',
        'bakery',
        'assembly'
    ) NOT NULL DEFAULT 'assembly' COMMENT 'Estación de preparación (KDS)',
    prep_time TINYINT UNSIGNED DEFAULT 5 COMMENT 'Tiempo estimado en minutos',
    recipe_steps TEXT COMMENT 'Pasos de preparación para cocina',
    ingredients_list JSON COMMENT 'Array de ingredientes',
    critical_check VARCHAR(255) COMMENT 'Punto crítico de control (HACCP)',
    -- Información nutricional y filtros
    calories INT UNSIGNED DEFAULT NULL COMMENT 'Kcal aproximadas',
    attributes JSON COMMENT 'Tags: vegano, picante, sin_gluten, etc.',
    target_cafe_types JSON COMMENT 'Tipos de café donde está disponible',
    target_animal_types JSON COMMENT 'Especies de animales objetivo',
    -- Configuración de pases (cuando product_type = pass)
    duration_minutes INT UNSIGNED COMMENT 'Duración estándar del servicio',
    min_pax TINYINT UNSIGNED DEFAULT 1 COMMENT 'Mínimo personas',
    max_pax TINYINT UNSIGNED COMMENT 'Máximo personas',
    pass_duration_minutes INT UNSIGNED COMMENT 'Duración específica del pase (override)',
    -- Visibilidad y metadatos
    image_url VARCHAR(255) DEFAULT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    is_seasonal BOOLEAN DEFAULT FALSE COMMENT 'Producto de temporada/limited edition',
    sort_order INT UNSIGNED DEFAULT 0 COMMENT 'Orden personalizado en menú',
    deleted_at TIMESTAMP NULL COMMENT 'Soft delete RGPD',
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    -- Índices optimizados
    INDEX idx_products_category (category_id),
    INDEX idx_products_type (product_type),
    INDEX idx_products_slug (slug),
    INDEX idx_products_kds (
        station,
        is_active,
        created_at
    ) COMMENT 'KDS: filtro por estación activos',
    INDEX idx_products_seasonal (is_seasonal),
    CONSTRAINT fk_prod_cat FOREIGN KEY (category_id) REFERENCES menu_categories (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Catálogo: ítems de menú y pases de entrada';
-- ============================================
-- 2. RESERVAS Y ÓRDENES
-- ============================================
CREATE TABLE IF NOT EXISTS reservations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    cafe_id BIGINT UNSIGNED NOT NULL,
    -- Información del pase comprado
    pass_product_id BIGINT UNSIGNED NOT NULL,
    pass_name VARCHAR(100) NOT NULL COMMENT 'Snapshot del nombre en momento de compra',
    pass_unit_price INT UNSIGNED NOT NULL COMMENT 'Snapshot precio en céntimos',
    pass_duration_minutes INT UNSIGNED NOT NULL,
    time_slot_id BIGINT UNSIGNED DEFAULT NULL COMMENT 'Slot de tiempo asociado (opcional)',
    -- Seguimiento físico
    tracker_id BIGINT UNSIGNED DEFAULT NULL COMMENT 'Brazalete/Tracker asignado',
    current_zone_id BIGINT UNSIGNED DEFAULT NULL COMMENT 'Ubicación actual dentro del café',
    -- Fecha y hora
    reservation_date DATE NOT NULL,
    reservation_time TIME NOT NULL,
    guest_count INT UNSIGNED NOT NULL DEFAULT 1,
    -- Estados: pending → confirmed → active → completed/cancelled/no_show
    status ENUM (
        'pending',
        'confirmed',
        'active',
        'completed',
        'cancelled',
        'no_show'
    ) DEFAULT 'pending',
    -- Control de visita y protocolos
    notes TEXT,
    check_in_at TIMESTAMP NULL,
    check_out_at TIMESTAMP NULL,
    -- Campos check-in recepción
    protocol_hygiene BOOLEAN DEFAULT FALSE COMMENT 'Desinfección manos',
    protocol_briefing BOOLEAN DEFAULT FALSE COMMENT 'Explicación normas',
    protocol_shoes BOOLEAN DEFAULT FALSE COMMENT 'Retirar calzado si zona requiere',
    -- Pago (sin pasarela online, gestión manual)
    final_amount INT UNSIGNED NULL COMMENT 'Precio final céntimos = pase + sum(items)',
    payment_status ENUM('pending', 'paid', 'cancelled') DEFAULT 'pending',
    payment_method VARCHAR(50) NULL COMMENT 'cash, card, transfer',
    payment_notes TEXT NULL,
    deleted_at TIMESTAMP NULL COMMENT 'Soft delete RGPD',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    -- Índices para consultas frecuentes
    INDEX idx_res_user (user_id),
    INDEX idx_res_cafe (cafe_id),
    INDEX idx_res_pass (pass_product_id),
    INDEX idx_res_status (status),
    INDEX idx_res_date_time (
        reservation_date,
        reservation_time
    ),
    -- Índices compuestos optimizados
    INDEX idx_res_user_history (
        user_id,
        reservation_date DESC,
        status
    ) COMMENT 'Historial usuario',
    INDEX idx_res_reception_today (
        cafe_id,
        reservation_date,
        status
    ) COMMENT 'Dashboard recepción',
    INDEX idx_res_completed (status, check_out_at DESC) COMMENT 'Reportes completadas',
    INDEX idx_res_time_slot (time_slot_id, status) COMMENT 'Gestión por slot',
    CONSTRAINT fk_res_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_res_cafe FOREIGN KEY (cafe_id) REFERENCES cafes (id) ON DELETE CASCADE,
    CONSTRAINT fk_res_pass FOREIGN KEY (pass_product_id) REFERENCES products (id) ON DELETE RESTRICT,
    CONSTRAINT fk_res_tracker FOREIGN KEY (tracker_id) REFERENCES trackers (id) ON DELETE SET NULL,
    CONSTRAINT fk_res_zone FOREIGN KEY (current_zone_id) REFERENCES cafe_zones (id) ON DELETE SET NULL
    -- FK time_slot_id se añade en 011_time_slots_waitlist.sql
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Reservas de clientes y registro de visitas';

-- Agregar FK de reviews.reservation_id ahora que reservations existe (solo si no existe)
SET @fk_exists := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_review_reservation');
SET @sql := IF(@fk_exists = 0,
    'ALTER TABLE reviews ADD CONSTRAINT fk_review_reservation FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE SET NULL',
    'SELECT "FK fk_review_reservation ya existe"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
CREATE TABLE IF NOT EXISTS reservation_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reservation_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    quantity INT UNSIGNED NOT NULL DEFAULT 1,
    unit_price DECIMAL(10, 2) NOT NULL COMMENT 'Precio histórico',
    -- Flujo KDS: pending → kitchen → ready → served
    status ENUM (
        'pending',
        'kitchen',
        'ready',
        'served'
    ) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_items_res (reservation_id),
    INDEX idx_items_prod (product_id),
    INDEX idx_items_kds_active (status, created_at) COMMENT 'KDS: filtro activos pending+kitchen',
    CONSTRAINT fk_items_res FOREIGN KEY (reservation_id) REFERENCES reservations (id) ON DELETE CASCADE,
    CONSTRAINT fk_items_prod FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE RESTRICT
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Líneas de pedido dentro de una reserva (KDS)';
CREATE TABLE IF NOT EXISTS favorites (
    user_id BIGINT UNSIGNED NOT NULL,
    cafe_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, cafe_id),
    CONSTRAINT fk_fav_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_fav_cafe FOREIGN KEY (cafe_id) REFERENCES cafes (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Cafés favoritos por usuario';
-- ============================================
-- 3. SISTEMA NORMALIZADO DE ALÉRGENOS
-- ============================================
CREATE TABLE IF NOT EXISTS allergens (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(10) NOT NULL UNIQUE COMMENT 'Código corto: GLU, LAC, etc.',
    name VARCHAR(50) NOT NULL UNIQUE,
    japanese_name VARCHAR(50) DEFAULT NULL COMMENT 'Nombre en japonés',
    icon_class VARCHAR(50) DEFAULT NULL COMMENT 'Clase CSS para icono (opcional)',
    icon_color VARCHAR(20) DEFAULT NULL COMMENT 'Color HEX sugerido',
    severity ENUM (
        'low',
        'medium',
        'high'
    ) DEFAULT 'medium',
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_allergens_code (code),
    INDEX idx_allergens_severity (severity)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Catálogo oficial de alérgenos alimentarios';
CREATE TABLE IF NOT EXISTS product_allergens (
    product_id BIGINT UNSIGNED NOT NULL,
    allergen_id INT UNSIGNED NOT NULL,
    notes VARCHAR(255) DEFAULT NULL COMMENT 'Nota específica (ej: trazas, cruzado)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (product_id, allergen_id),
    CONSTRAINT fk_pa_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE,
    CONSTRAINT fk_pa_allergen FOREIGN KEY (allergen_id) REFERENCES allergens (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Relación N:N entre productos y alérgenos (sistema principal)';
-- ============================================
-- 4. DATOS INICIALES
-- ============================================
-- Insertar alérgenos con códigos cortos
INSERT INTO allergens (code, name, japanese_name, icon_color, severity)
VALUES ('GLU', 'Gluten', 'グルテン', '#D4A574', 'high'),
    ('LAC', 'Lácteos', '乳製品', '#87CEEB', 'medium'),
    ('HUE', 'Huevo', '卵', '#FFE5B4', 'medium'),
    ('FRU', 'Frutos Secos', 'ナッツ類', '#8B4513', 'high'),
    ('SOJ', 'Soja', '大豆', '#90EE90', 'medium'),
    ('PES', 'Pescado', '魚', '#4682B4', 'high'),
    ('MAR', 'Marisco', '貝類', '#FF6347', 'high'),
    ('SES', 'Sésamo', 'ごま', '#DEB887', 'medium'),
    ('MOS', 'Mostaza', 'マスタード', '#FFD700', 'low'),
    ('API', 'Apio', 'セロリ', '#32CD32', 'low'),
    ('MOL', 'Moluscos', '軟体動物', '#FFB6C1', 'high'),
    ('SUL', 'Sulfitos', '亜硫酸塩', '#722F37', 'low') ON DUPLICATE KEY
UPDATE name =
VALUES(name),
    japanese_name =
VALUES(japanese_name);
-- ============================================
-- 5. RELACIONES CON OTRAS MIGRACIONES
-- ============================================
-- Agregar foreign key a reviews.reservation_id si no existe
SET @constraint_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE TABLE_NAME = 'reviews'
      AND CONSTRAINT_NAME = 'fk_review_reservation'
      AND TABLE_SCHEMA = DATABASE()
);

SET @sql = IF(@constraint_exists = 0,
    'ALTER TABLE reviews ADD CONSTRAINT fk_review_reservation FOREIGN KEY (reservation_id) REFERENCES reservations (id) ON DELETE SET NULL',
    'SELECT "Foreign key fk_review_reservation ya existe" AS info'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Evento MySQL 8.4: Purga automática reservations eliminadas (RGPD 30 días)
DROP EVENT IF EXISTS evt_cleanup_old_reservations;
CREATE EVENT evt_cleanup_old_reservations ON SCHEDULE EVERY 1 MONTH DO
DELETE FROM reservations
WHERE deleted_at IS NOT NULL
    AND deleted_at < NOW() - INTERVAL 30 DAY;
-- Evento: Purga automática products eliminados
DROP EVENT IF EXISTS evt_cleanup_old_products;
CREATE EVENT evt_cleanup_old_products ON SCHEDULE EVERY 1 MONTH DO
DELETE FROM products
WHERE deleted_at IS NOT NULL
    AND deleted_at < NOW() - INTERVAL 30 DAY;
SET FOREIGN_KEY_CHECKS = 1;
