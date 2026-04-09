-- 013_loyalty_system.sql
-- Sistema de fidelización "Point Card" con sellos y recompensas

-- Tarjetas de fidelización (una por usuario)
CREATE TABLE IF NOT EXISTS loyalty_cards (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    stamps INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Sellos acumulados (1 por visita completada)',
    current_tier ENUM('bronze', 'silver', 'gold', 'platinum') NOT NULL DEFAULT 'bronze' COMMENT 'Nivel actual según visits_count',
    visits_count INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Total de visitas completadas (histórico)',
    total_rewards_redeemed INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Total de recompensas canjeadas',
    last_stamp_at TIMESTAMP NULL COMMENT 'Fecha del último sello añadido',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY idx_user_id (user_id),
    INDEX idx_tier (current_tier),
    INDEX idx_stamps (stamps),

    CONSTRAINT fk_loyalty_card_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Tarjetas de fidelización: 1 sello = 1 visita completada. Recompensas: 5 sellos = bebida gratis, 10 = entrada gratis';

-- Recompensas canjeadas (historial)
CREATE TABLE IF NOT EXISTS loyalty_rewards (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    loyalty_card_id BIGINT UNSIGNED NOT NULL,
    reward_type ENUM('drink_free', 'entry_free', 'discount_10', 'discount_20', 'merch_discount') NOT NULL COMMENT 'Tipo de recompensa canjeada',
    stamps_cost INT UNSIGNED NOT NULL COMMENT 'Sellos consumidos al canjear',
    redeemed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de canje',
    used_at TIMESTAMP NULL COMMENT 'Fecha en que se usó la recompensa (si aplica)',
    expires_at TIMESTAMP NULL COMMENT 'Fecha de expiración (30 días desde canje)',
    status ENUM('pending', 'used', 'expired') NOT NULL DEFAULT 'pending',
    redemption_code VARCHAR(20) NOT NULL COMMENT 'Código único para validar en TPV',
    notes TEXT NULL COMMENT 'Notas adicionales',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_user_id (user_id),
    INDEX idx_loyalty_card_id (loyalty_card_id),
    INDEX idx_status (status),
    INDEX idx_redemption_code (redemption_code),
    INDEX idx_expires_at (expires_at),

    CONSTRAINT fk_loyalty_reward_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_loyalty_reward_card FOREIGN KEY (loyalty_card_id)
        REFERENCES loyalty_cards(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Historial de recompensas canjeadas. Códigos válidos por 30 días';

-- Tracking de animales vistos por usuario (para recomendaciones)
CREATE TABLE IF NOT EXISTS user_animal_visits (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    animal_id BIGINT UNSIGNED NOT NULL,
    reservation_id BIGINT UNSIGNED NOT NULL COMMENT 'Reserva en la que vio al animal',
    visited_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    interaction_rating TINYINT UNSIGNED NULL COMMENT 'Calificación opcional 1-5 de la interacción',

    INDEX idx_user_animal (user_id, animal_id),
    INDEX idx_reservation (reservation_id),
    INDEX idx_visited_at (visited_at),

    CONSTRAINT fk_user_animal_visit_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_user_animal_visit_animal FOREIGN KEY (animal_id)
        REFERENCES animals(id) ON DELETE CASCADE,
    CONSTRAINT fk_user_animal_visit_reservation FOREIGN KEY (reservation_id)
        REFERENCES reservations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Tracking de qué animales ha visto cada usuario para personalización';

-- Configuración de recompensas disponibles (catálogo)
CREATE TABLE IF NOT EXISTS loyalty_reward_catalog (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reward_type ENUM('drink_free', 'entry_free', 'discount_10', 'discount_20', 'merch_discount') NOT NULL,
    name_es VARCHAR(100) NOT NULL COMMENT 'Nombre en español',
    name_en VARCHAR(100) NOT NULL COMMENT 'Nombre en inglés',
    description_es TEXT NOT NULL,
    description_en TEXT NOT NULL,
    stamps_required INT UNSIGNED NOT NULL COMMENT 'Sellos necesarios para canjear',
    tier_required ENUM('bronze', 'silver', 'gold', 'platinum') NOT NULL DEFAULT 'bronze' COMMENT 'Tier mínimo requerido',
    validity_days INT UNSIGNED NOT NULL DEFAULT 30 COMMENT 'Días de validez desde canje',
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    display_order INT NOT NULL DEFAULT 0,
    icon VARCHAR(50) NULL COMMENT 'Clase de icono (ej: fa-coffee)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY idx_reward_type (reward_type),
    INDEX idx_tier_stamps (tier_required, stamps_required),
    INDEX idx_display_order (display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Catálogo de recompensas disponibles (configuración)';

-- Insertar recompensas predefinidas
INSERT INTO loyalty_reward_catalog
    (reward_type, name_es, name_en, description_es, description_en, stamps_required, tier_required, validity_days, display_order, icon)
VALUES
    ('drink_free', 'Bebida Gratis', 'Free Drink',
     'Canjea por cualquier bebida del menú (hasta 500 ¥)',
     'Redeem for any drink from our menu (up to ¥500)',
     5, 'bronze', 30, 1, 'fa-coffee'),

    ('entry_free', 'Entrada Gratis', 'Free Entry',
     'Entrada gratuita (1 persona, válida para cualquier pase)',
     'Free entry (1 person, valid for any pass)',
     10, 'silver', 30, 2, 'fa-ticket'),

    ('discount_10', 'Descuento 10%', '10% Discount',
     '10% de descuento en tu próxima reserva',
     '10% discount on your next reservation',
     3, 'bronze', 30, 3, 'fa-percent'),

    ('discount_20', 'Descuento 20%', '20% Discount',
     '20% de descuento en tu próxima reserva',
     '20% discount on your next reservation',
     7, 'gold', 30, 4, 'fa-percent'),

    ('merch_discount', 'Descuento Tienda', 'Merch Discount',
     '15% de descuento en productos de la tienda',
     '15% discount on merchandise',
     4, 'bronze', 60, 5, 'fa-shopping-bag')
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;
