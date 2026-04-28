-- =============================================================================
-- Migration 023 — CHECK constraints for data integrity
-- Plan: Fase 0.7 — Restricciones de integridad a nivel DB
-- =============================================================================

ALTER TABLE user_animal_visits
    ADD CONSTRAINT chk_interaction_rating
        CHECK (interaction_rating IS NULL OR interaction_rating BETWEEN 1 AND 5);
