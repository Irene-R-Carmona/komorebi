-- Migration 024: Generated Column for loyalty_cards.current_tier
--
-- Añade columna STORED generada en DB para eliminar la necesidad de
-- calcular el tier en PHP y luego llamar a UPDATE loyalty_cards SET current_tier.
-- La lógica de umbrales refleja las constantes de LoyaltyService:
--   TIER_SILVER_MIN  = 10 visitas
--   TIER_GOLD_MIN    = 30 visitas
--   TIER_PLATINUM_MIN = 50 visitas
--
-- La columna `current_tier` existente pasa a ser VIRTUAL GENERATED ALWAYS,
-- por lo que LoyaltyService::addStamp() ya no necesita llamar a updateTier().

ALTER TABLE loyalty_cards
    MODIFY COLUMN current_tier VARCHAR(20)
        GENERATED ALWAYS AS (
            CASE
                WHEN visits_count >= 50 THEN 'platinum'
                WHEN visits_count >= 30 THEN 'gold'
                WHEN visits_count >= 10 THEN 'silver'
                ELSE 'bronze'
            END
        ) STORED;
