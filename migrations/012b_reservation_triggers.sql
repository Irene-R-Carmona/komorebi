-- ============================================================================
-- MIGRACIÓN 012b: TRIGGERS PARA GESTIÓN AUTOMÁTICA DE CAPACIDAD
-- ============================================================================
-- Módulo: Triggers de sincronización reservations ↔ time_slots
-- Dependencias: 012_integrate_time_slots_reservations.sql
-- MySQL 8.0+ / 8.4+ Compatible
-- NOTA: Triggers deshabilitados - requieren log_bin_trust_function_creators=1
--       La lógica de capacidad se gestiona desde ReservationService (más robusto)
-- ============================================================================

SET NAMES utf8mb4;

-- Los triggers están comentados. Para habilitarlos:
-- 1. Configurar en docker/mysql/my.cnf: log_bin_trust_function_creators=1
-- 2. Descomentar el siguiente código:

/*
DROP TRIGGER IF EXISTS trg_reservation_confirmed;
DROP TRIGGER IF EXISTS trg_reservation_cancelled;

-- ============================================================================
-- TRIGGER: Decrementar spots al confirmar reserva
-- ============================================================================
CREATE TRIGGER trg_reservation_confirmed
AFTER UPDATE ON reservations
FOR EACH ROW
BEGIN
    -- Solo si cambia a confirmed y tiene time_slot_id
    IF NEW.status = 'confirmed'
       AND OLD.status != 'confirmed'
       AND NEW.time_slot_id IS NOT NULL THEN

        UPDATE time_slots
        SET available_spots = GREATEST(available_spots - NEW.guest_count, 0),
            reserved_spots = reserved_spots + NEW.guest_count,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = NEW.time_slot_id;
    END IF;
END;

-- ============================================================================
-- TRIGGER: Liberar spots al cancelar reserva + promover waitlist
-- ============================================================================
CREATE TRIGGER trg_reservation_cancelled
AFTER UPDATE ON reservations
FOR EACH ROW
BEGIN
    DECLARE slot_capacity INT;

    -- Solo si cambia a cancelled y tenía time_slot_id
    IF NEW.status = 'cancelled'
       AND OLD.status IN ('confirmed', 'pending')
       AND NEW.time_slot_id IS NOT NULL THEN

        -- Obtener capacidad total del slot
        SELECT total_capacity INTO slot_capacity
        FROM time_slots
        WHERE id = NEW.time_slot_id;

        -- Liberar spots (sin exceder capacidad)
        UPDATE time_slots
        SET available_spots = LEAST(available_spots + OLD.guest_count, slot_capacity),
            reserved_spots = GREATEST(reserved_spots - OLD.guest_count, 0),
            updated_at = CURRENT_TIMESTAMP
        WHERE id = NEW.time_slot_id;

        -- Notificar al primero en waitlist si hay plazas disponibles
        UPDATE waitlist
        SET status = 'notified',
            notified_at = CURRENT_TIMESTAMP,
            expires_at = DATE_ADD(CURRENT_TIMESTAMP, INTERVAL response_timeout_minutes MINUTE),
            updated_at = CURRENT_TIMESTAMP
        WHERE time_slot_id = NEW.time_slot_id
          AND status = 'waiting'
        ORDER BY position ASC
        LIMIT 1;
    END IF;
END;
*/

-- ============================================================================
-- FIN MIGRACIÓN 012b
-- ============================================================================
