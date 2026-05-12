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
    duration_minutes INT UNSIGNED COMMENT 'Duración del servicio en minutos',
    min_pax TINYINT UNSIGNED DEFAULT 1 COMMENT 'Mínimo personas',
    max_pax TINYINT UNSIGNED COMMENT 'Máximo personas',
    -- Visibilidad y metadatos
    image_url VARCHAR(255) DEFAULT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    stock_quantity INT UNSIGNED NULL DEFAULT NULL COMMENT 'Stock disponible. NULL = sin límite. 0 = agotado.',
    is_seasonal BOOLEAN DEFAULT FALSE COMMENT 'Producto de temporada/limited edition',
    sort_order INT UNSIGNED DEFAULT 0 COMMENT 'Orden personalizado en menú',
    deleted_at TIMESTAMP NULL COMMENT 'Soft delete RGPD',
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    -- Índices optimizados
    INDEX idx_products_category (category_id),
    INDEX idx_products_type (product_type),
    UNIQUE KEY uk_products_slug (slug),
    INDEX idx_products_kds (
        station,
        is_active,
        created_at
    ) COMMENT 'KDS: filtro por estación activos',
    INDEX idx_products_seasonal (is_seasonal),
    INDEX idx_products_active (is_active, deleted_at),
    CONSTRAINT fk_products_menu_categories FOREIGN KEY (category_id) REFERENCES menu_categories (id) ON DELETE CASCADE
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
    guest_count INT UNSIGNED NOT NULL DEFAULT 1, -- DEFAULT estructural; límite máximo dinámico en settings: max_guests_per_reservation
    -- Estados: pending → confirmed → active → completed/cancelled/no_show/refunded
    status ENUM (
        'pending',
        'confirmed',
        'active',
        'completed',
        'cancelled',
        'no_show',
        'refunded'
    ) NOT NULL DEFAULT 'pending',
    invoice_pdf_url VARCHAR(500) NULL COMMENT 'URL pública del PDF de factura (Cloudinary)',
    -- Control de visita y protocolos
    notes TEXT,
    cancellation_reason TEXT NULL COMMENT 'Motivo de cancelación introducido por el manager',
    manager_notes TEXT NULL COMMENT 'Notas internas del manager sobre la reserva',
    check_in_at TIMESTAMP NULL,
    check_out_at TIMESTAMP NULL,
    -- Campos check-in recepción
    protocol_hygiene BOOLEAN DEFAULT FALSE COMMENT 'Desinfección manos',
    protocol_briefing BOOLEAN DEFAULT FALSE COMMENT 'Explicación normas',
    protocol_shoes BOOLEAN DEFAULT FALSE COMMENT 'Retirar calzado si zona requiere',
    -- Pago (sin pasarela online, gestión manual)
    final_amount INT UNSIGNED NULL COMMENT 'Precio final céntimos = pase + sum(items)',
    payment_status ENUM('pending', 'paid', 'cancelled') DEFAULT 'pending',
    payment_method ENUM('card','bizum','cash','transfer','free') NULL DEFAULT NULL COMMENT 'Método de pago. NULL hasta que se procesa el cobro.',
    payment_notes TEXT NULL,
    refund_amount INT UNSIGNED NULL COMMENT 'Importe devuelto en céntimos de €. NULL si no aplica devolución.',
    refunded_at DATETIME NULL COMMENT 'Momento en que se procesó la devolución.',
    loyalty_awarded BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Sello de fidelización emitido para esta reserva (idempotencia)',
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
    INDEX idx_cafe_date_status (cafe_id, reservation_date, status),
    INDEX idx_reservations_user_status (user_id, status),
    INDEX idx_res_payment_status (payment_status, updated_at DESC) COMMENT 'Consultas por estado de pago',
    INDEX idx_res_loyalty_awarded (loyalty_awarded) COMMENT 'Reservas sin sello emitido',
    CONSTRAINT fk_reservations_users FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_reservations_cafes FOREIGN KEY (cafe_id) REFERENCES cafes (id) ON DELETE CASCADE,
    CONSTRAINT fk_reservations_products FOREIGN KEY (pass_product_id) REFERENCES products (id) ON DELETE RESTRICT,
    CONSTRAINT fk_reservations_trackers FOREIGN KEY (tracker_id) REFERENCES trackers (id) ON DELETE SET NULL,
    CONSTRAINT fk_reservations_cafe_zones FOREIGN KEY (current_zone_id) REFERENCES cafe_zones (id) ON DELETE SET NULL
    -- FK time_slot_id se añade en 011_time_slots_waitlist.sql
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Reservas de clientes y registro de visitas';

CREATE TABLE IF NOT EXISTS reservation_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reservation_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    quantity INT UNSIGNED NOT NULL DEFAULT 1,
    unit_price INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Precio unitario en céntimos de €',
    -- Flujo KDS: pre_order (wizard) → pending → kitchen → ready → served
    -- pre_order: añadido en el wizard, activado a pending en check-in por recepción
    status ENUM (
        'pre_order',
        'pending',
        'kitchen',
        'ready',
        'served'
    ) NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    kitchen_started_at TIMESTAMP NULL DEFAULT NULL COMMENT 'Cuándo se inició la preparación (status → kitchen)',
    INDEX idx_items_res (reservation_id),
    INDEX idx_items_prod (product_id),
    INDEX idx_items_kds_active (status, created_at) COMMENT 'KDS: filtro activos pending+kitchen',
    INDEX idx_reservation_items_timeline (reservation_id, created_at DESC),
    INDEX idx_ri_kitchen_started (kitchen_started_at),
    CONSTRAINT fk_reservation_items_reservations FOREIGN KEY (reservation_id) REFERENCES reservations (id) ON DELETE CASCADE,
    CONSTRAINT fk_reservation_items_products FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE RESTRICT
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Líneas de pedido dentro de una reserva (KDS)';
CREATE TABLE IF NOT EXISTS favorites (
    user_id BIGINT UNSIGNED NOT NULL,
    cafe_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, cafe_id),
    CONSTRAINT fk_favorites_users FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_favorites_cafes FOREIGN KEY (cafe_id) REFERENCES cafes (id) ON DELETE CASCADE
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
    CONSTRAINT fk_product_allergens_products FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE,
    CONSTRAINT fk_product_allergens_allergens FOREIGN KEY (allergen_id) REFERENCES allergens (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Relación N:N entre productos y alérgenos (sistema principal)';
-- ============================================
-- 4. DATOS INICIALES
-- ============================================
-- Catálogo oficial UE 1169/2011: 14 alérgenos de declaración obligatoria.
-- Los códigos aquí son la fuente de verdad del sistema.
-- AllergenSeeder.php usa INSERT IGNORE sobre esta misma tabla → re-ejecución es no-op.
INSERT IGNORE INTO allergens (code, name, japanese_name, icon_class, icon_color, severity, description)
VALUES
    ('gluten',        'Gluten',            'グルテン',    'bi-grain',               '#D4A017', 'high',   'Cereales que contienen gluten: trigo, centeno, cebada, avena y sus variedades.'),
    ('crustaceos',    'Crustáceos',        '甲殻類',      'bi-water',               '#E87040', 'high',   'Crustáceos y productos a base de crustáceos.'),
    ('huevos',        'Huevos',            '卵',          'bi-egg',                 '#F5E642', 'medium', 'Huevos y productos a base de huevo.'),
    ('pescado',       'Pescado',           '魚',          'bi-fish',                '#4A90D9', 'high',   'Pescado y productos a base de pescado.'),
    ('cacahuetes',    'Cacahuetes',        '落花生',      'bi-circle-fill',         '#C8860A', 'high',   'Cacahuetes y productos a base de cacahuetes.'),
    ('soja',          'Soja',              '大豆',        'bi-circle',              '#6AAF3D', 'medium', 'Soja y productos a base de soja.'),
    ('lacteos',       'Lácteos',           '乳製品',      'bi-cup-fill',            '#FFFFFF', 'medium', 'Leche y sus derivados (incluida la lactosa).'),
    ('frutos_secos',  'Frutos de cáscara', 'ナッツ',      'bi-tree',                '#8B5E3C', 'high',   'Almendras, avellanas, nueces, anacardos, pacanas, nueces de Brasil, pistachos y nueces de macadamia.'),
    ('apio',          'Apio',              'セロリ',      'bi-flower1',             '#7DBF5C', 'low',    'Apio y productos derivados.'),
    ('mostaza',       'Mostaza',           'マスタード',  'bi-droplet-fill',        '#E8C84A', 'low',    'Mostaza y productos derivados.'),
    ('sesamo',        'Sésamo',            'ごま',        'bi-dot',                 '#D4B483', 'medium', 'Granos de sésamo y productos a base de granos de sésamo.'),
    ('sulfitos',      'Sulfitos',          '亜硫酸塩',    'bi-exclamation-diamond', '#FF6B6B', 'medium', 'Dióxido de azufre y sulfitos en concentraciones superiores a 10 mg/kg o 10 mg/l.'),
    ('altramuces',    'Altramuces',        'ルピナス',    'bi-flower2',             '#9B59B6', 'low',    'Altramuces y productos a base de altramuces.'),
    ('moluscos',      'Moluscos',          '軟体動物',    'bi-water',               '#3498DB', 'high',   'Moluscos y productos a base de moluscos.');
-- ============================================
-- 5. RELACIONES CON OTRAS MIGRACIONES
-- ============================================
-- Agregar FK: reviews.reservation_id → reservations(id)
ALTER TABLE reviews
    ADD CONSTRAINT fk_reviews_reservation
        FOREIGN KEY (reservation_id)
        REFERENCES reservations (id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE;

-- Agregar FK: trackers.last_assigned_reservation_id → reservations(id)
SET @constraint_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE TABLE_NAME = 'trackers'
      AND CONSTRAINT_NAME = 'fk_trackers_reservations'
      AND TABLE_SCHEMA = DATABASE()
);

SET @sql = IF(@constraint_exists = 0,
    'ALTER TABLE trackers ADD CONSTRAINT fk_trackers_reservations FOREIGN KEY (last_assigned_reservation_id) REFERENCES reservations (id) ON DELETE SET NULL',
    'SELECT "Foreign key fk_trackers_reservations ya existe" AS info'
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
