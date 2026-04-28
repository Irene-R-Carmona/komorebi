-- 021_integrity_indexes.sql
-- Índices compuestos para consultas frecuentes en loyalty, reservations y animals

-- loyalty_rewards: consultas combinadas usuario+estado (recompensas pendientes del usuario)
ALTER TABLE loyalty_rewards
    ADD INDEX idx_user_status (user_id, status),
    ADD INDEX idx_status_expires (status, expires_at);

-- reservations: filtrado por café + fecha + estado (dashboard operativo)
ALTER TABLE reservations
    ADD INDEX idx_cafe_date_status (cafe_id, reservation_date, status);

-- animal_incidents: por animal y estado abierto (vista keeper)
ALTER TABLE animal_incidents
    ADD INDEX idx_animal_status (animal_id, status);
