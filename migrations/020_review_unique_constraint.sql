-- 020_review_unique_constraint.sql
-- Garantiza una sola reseña por usuario por café (Q-01)

ALTER TABLE reviews
    ADD CONSTRAINT uq_user_cafe_review UNIQUE (user_id, cafe_id);

