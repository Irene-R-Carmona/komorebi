-- 026_newsletter_expires_at.sql
-- Añade columna expires_at a newsletter_subscriptions para soportar
-- expiración de tokens de suscripción/baja.

ALTER TABLE newsletter_subscriptions
    ADD COLUMN expires_at TIMESTAMP NULL DEFAULT NULL AFTER created_at;
