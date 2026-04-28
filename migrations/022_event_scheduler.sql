-- 022_event_scheduler.sql
-- Expiración automática de recompensas de fidelización vía MySQL Event Scheduler
-- Requiere event_scheduler=ON (configurado en docker-compose.override.yml con --event-scheduler=ON)

CREATE EVENT IF NOT EXISTS expire_loyalty_rewards
    ON SCHEDULE EVERY 1 HOUR
    STARTS CURRENT_TIMESTAMP
    COMMENT 'Marca como expired las recompensas de fidelización caducadas'
    DO
    UPDATE loyalty_rewards
    SET
        status    = 'expired',
        updated_at = NOW()
    WHERE status = 'pending'
      AND expires_at IS NOT NULL
      AND expires_at < NOW();
