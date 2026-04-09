-- 012_waitlist.sql
-- Tabla de entradas de waitlist (ya creada en 011, aquí solo ajustes)
-- FASE 2.3: WaitlistService

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- La tabla waitlist ya fue creada en 011_time_slots_waitlist.sql
-- Aquí solo agregamos vistas y índices adicionales

-- Drop vista si existe
DROP VIEW IF EXISTS waitlist_active;

-- Recrear vista con estructura correcta de 011
CREATE OR REPLACE VIEW waitlist_active AS
SELECT
    w.id,
    w.position,
    w.token,
    w.guest_count AS guests,
    w.status,
    w.expires_at,
    w.created_at,
    w.notified_at,
    w.confirmed_at,
    u.name AS user_name,
    u.email AS user_email,
    w.contact_phone,
    ts.cafe_id,
    c.name AS cafe_name,
    ts.slot_date,
    ts.slot_time
FROM waitlist w
INNER JOIN users u ON w.user_id = u.id
INNER JOIN time_slots ts ON w.time_slot_id = ts.id
INNER JOIN cafes c ON ts.cafe_id = c.id
WHERE w.status IN ('waiting', 'notified')
  AND w.expires_at > NOW()
ORDER BY w.position ASC;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- FIN MIGRACIÓN 012
-- ============================================================================
