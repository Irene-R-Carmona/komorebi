-- Migration 028: Seed loyalty_reward_catalog
-- Purpose: Ensure the 5 standard rewards exist in the catalog.
--          Uses INSERT IGNORE so it is safe to re-run on databases that
--          already have the rows from migration 013.
-- Date: 2026-05-06

SET FOREIGN_KEY_CHECKS = 0;

INSERT IGNORE INTO loyalty_reward_catalog
    (reward_type, name_es, name_en, description_es, description_en, stamps_required, tier_required, validity_days, display_order, icon, is_active)
VALUES
    ('drink_free', 'Bebida Gratis', 'Free Drink',
     'Canjea por cualquier bebida del menú (hasta 500 ¥)',
     'Redeem for any drink from our menu (up to ¥500)',
     5, 'bronze', 30, 1, 'bi-cup-hot', TRUE),

    ('entry_free', 'Entrada Gratis', 'Free Entry',
     'Entrada gratuita (1 persona, válida para cualquier pase)',
     'Free entry (1 person, valid for any pass)',
     10, 'silver', 30, 2, 'bi-ticket-perforated', TRUE),

    ('discount_10', 'Descuento 10%', '10% Discount',
     '10% de descuento en tu próxima reserva',
     '10% discount on your next reservation',
     3, 'bronze', 30, 3, 'bi-percent', TRUE),

    ('discount_20', 'Descuento 20%', '20% Discount',
     '20% de descuento en tu próxima reserva',
     '20% discount on your next reservation',
     7, 'gold', 30, 4, 'bi-percent', TRUE),

    ('merch_discount', 'Descuento Tienda', 'Merch Discount',
     '15% de descuento en productos de la tienda',
     '15% discount on merchandise',
     4, 'bronze', 60, 5, 'bi-bag', TRUE);

SET FOREIGN_KEY_CHECKS = 1;
