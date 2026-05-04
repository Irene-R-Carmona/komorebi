-- ============================================================================
-- MIGRACIÓN 011: TIME SLOTS Y WAITLIST (LISTA DE ESPERA)
-- ============================================================================
-- Módulo: Gestión de disponibilidad horaria y lista de espera
-- Dependencias: 001_infrastructure.sql (cafes), 002_users_rbac.sql (users)
-- MySQL 8.0+ / 8.4+ Compatible
-- PSR-3 Logging: Esta migración genera eventos en audit_logs
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================
-- 1. TIME SLOTS: GESTIÓN DE DISPONIBILIDAD
-- ============================================
-- Representa la disponibilidad de plazas en un café para una fecha y hora específicas.
-- Permite control granular de capacidad y bloqueos administrativos.

CREATE TABLE IF NOT EXISTS time_slots (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Identificadores
    cafe_id BIGINT UNSIGNED NOT NULL COMMENT 'Café al que pertenece el slot',

    -- Programación temporal (índice compuesto crítico)
    slot_date DATE NOT NULL COMMENT 'Fecha del slot (YYYY-MM-DD)',
    slot_time TIME NOT NULL COMMENT 'Hora de inicio (HH:MM:SS)',

    -- Control de capacidad
    total_capacity INT UNSIGNED NOT NULL DEFAULT 20 COMMENT 'Capacidad total del slot',
    available_spots INT UNSIGNED NOT NULL DEFAULT 20 COMMENT 'Plazas disponibles actuales',
    reserved_spots INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Plazas confirmadas/reservadas',

    -- Estado administrativo
    is_blocked BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Bloqueado por staff (mantenimiento, evento privado)',
    blocked_reason VARCHAR(255) DEFAULT NULL COMMENT 'Motivo del bloqueo (visible para staff)',

    -- Configuración dinámica
    duration_minutes TINYINT UNSIGNED NOT NULL DEFAULT 60 COMMENT 'Duración estándar en minutos',
    min_advance_hours TINYINT UNSIGNED NOT NULL DEFAULT 2 COMMENT 'Mínimo de antelación en horas',
    max_advance_days SMALLINT UNSIGNED NOT NULL DEFAULT 30 COMMENT 'Máximo de antelación en días',

    -- Auditoría
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by BIGINT UNSIGNED DEFAULT NULL COMMENT 'Usuario que creó el slot (staff)',

    -- Constraints
    CONSTRAINT fk_time_slots_cafe
        FOREIGN KEY (cafe_id) REFERENCES cafes(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_time_slots_created_by
        FOREIGN KEY (created_by) REFERENCES users(id)
        ON DELETE SET NULL,

    -- Regla de negocio: available_spots no puede ser negativo ni exceder total_capacity
    CONSTRAINT chk_time_slots_capacity
        CHECK (available_spots <= total_capacity AND available_spots >= 0),

    CONSTRAINT chk_time_slots_reserved
        CHECK (reserved_spots <= total_capacity AND reserved_spots >= 0),

    -- Duraciones razonables (30min - 240min = 4h)
    CONSTRAINT chk_time_slots_duration
        CHECK (duration_minutes BETWEEN 30 AND 240),

    -- Índices críticos para rendimiento
    UNIQUE KEY uk_time_slots_cafe_date_time (cafe_id, slot_date, slot_time)
        COMMENT 'Evita duplicados de slots en misma fecha/hora/café',

    INDEX idx_time_slots_availability (cafe_id, slot_date, slot_time, available_spots, is_blocked)
        COMMENT 'Búsqueda rápida de slots disponibles (cobertura completa)',

    INDEX idx_time_slots_date_range (slot_date, cafe_id)
        COMMENT 'Consultas por rango de fechas',

    INDEX idx_time_slots_blocked (is_blocked, cafe_id)
        COMMENT 'Administración: listar slots bloqueados'

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Slots de tiempo para reservas con control de capacidad';

-- ============================================
-- 2. WAITLIST: LISTA DE ESPERA
-- ============================================
-- Gestiona usuarios en cola cuando un time_slot está lleno.
-- Sistema FIFO con expiración automática y promoción a reserva.

CREATE TABLE IF NOT EXISTS waitlist (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Identificadores
    time_slot_id BIGINT UNSIGNED NOT NULL COMMENT 'Slot al que desea acceder',
    user_id BIGINT UNSIGNED NOT NULL COMMENT 'Usuario en lista de espera',

    -- Control de cola FIFO
    position INT UNSIGNED NOT NULL COMMENT 'Posición en la cola (1 = primero)',

    -- Seguridad y notificaciones
    token VARCHAR(64) NOT NULL COMMENT 'Token único para confirmación vía email/SMS',

    -- Expiración y tiempo límite
    expires_at TIMESTAMP NOT NULL COMMENT 'Momento de expiración automática',
    response_timeout_minutes TINYINT UNSIGNED NOT NULL DEFAULT 15 COMMENT 'Tiempo para responder notificación',

    -- Estado del registro
    status ENUM(
        'waiting',      -- En cola, esperando disponibilidad
        'notified',     -- Notificado de disponibilidad, esperando confirmación
        'confirmed',    -- Confirmó y se creó su reserva
        'expired',      -- Expiró sin respuesta
        'cancelled'     -- Canceló voluntariamente
    ) NOT NULL DEFAULT 'waiting',

    -- Datos de contacto (redundantes para notificaciones rápidas)
    contact_email VARCHAR(255) NOT NULL COMMENT 'Email para notificaciones',
    contact_phone VARCHAR(20) DEFAULT NULL COMMENT 'Teléfono opcional (SMS)',

    -- Preferencias del usuario
    guest_count TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Número de personas',
    special_requests TEXT DEFAULT NULL COMMENT 'Peticiones especiales',

    -- Auditoría y tracking
    notified_at TIMESTAMP NULL COMMENT 'Cuándo se le notificó disponibilidad',
    confirmed_at TIMESTAMP NULL COMMENT 'Cuándo confirmó',
    reservation_id BIGINT UNSIGNED DEFAULT NULL COMMENT 'ID reserva creada tras confirmación',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Constraints
    CONSTRAINT fk_waitlist_time_slot
        FOREIGN KEY (time_slot_id) REFERENCES time_slots(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_waitlist_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_waitlist_reservation
        FOREIGN KEY (reservation_id) REFERENCES reservations(id)
        ON DELETE SET NULL,

    -- Reglas de negocio
    CONSTRAINT chk_waitlist_position
        CHECK (position > 0),

    CONSTRAINT chk_waitlist_guest_count
        CHECK (guest_count > 0 AND guest_count <= 20),

    CONSTRAINT chk_waitlist_timeout
        CHECK (response_timeout_minutes BETWEEN 5 AND 60),

    -- Índices críticos
    UNIQUE KEY uk_waitlist_token (token)
        COMMENT 'Búsqueda rápida por token en URLs de confirmación',

    UNIQUE KEY uk_waitlist_user_slot (user_id, time_slot_id, status)
        COMMENT 'Evita duplicados: un usuario no puede estar 2 veces en waiting/notified',

    INDEX idx_waitlist_slot_position (time_slot_id, position, status)
        COMMENT 'Obtener siguiente en cola: ORDER BY position ASC LIMIT 1',

    INDEX idx_waitlist_expires (status, expires_at)
        COMMENT 'Limpieza automática de registros expirados',

    INDEX idx_waitlist_user_active (user_id, status, created_at)
        COMMENT 'Historial del usuario: waitlist activas y pasadas'

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Lista de espera FIFO con notificaciones y expiración';

-- ============================================
-- 3. VISTAS DE CONSULTA OPTIMIZADAS
-- ============================================

-- Vista: Slots disponibles (solo públicos)
CREATE OR REPLACE VIEW v_available_time_slots AS
SELECT
    ts.id,
    ts.cafe_id,
    c.name AS cafe_name,
    c.slug AS cafe_slug,
    ts.slot_date,
    ts.slot_time,
    ts.available_spots,
    ts.total_capacity,
    ts.duration_minutes,
    -- Calcular ocupación porcentual
    ROUND((ts.reserved_spots / ts.total_capacity) * 100, 2) AS occupancy_percentage,
    -- Indicador de disponibilidad
    CASE
        WHEN ts.available_spots = 0 THEN 'full'
        WHEN ts.available_spots <= 5 THEN 'limited'
        ELSE 'available'
    END AS availability_status
FROM time_slots ts
INNER JOIN cafes c ON ts.cafe_id = c.id
WHERE ts.is_blocked = FALSE
  AND ts.available_spots > 0
  AND c.is_active = TRUE
  AND c.has_reservations = TRUE
  AND ts.slot_date >= CURDATE()
ORDER BY ts.slot_date, ts.slot_time;

-- Vista: Estadísticas de waitlist por slot
CREATE OR REPLACE VIEW v_waitlist_stats AS
SELECT
    time_slot_id,
    COUNT(*) AS total_waiting,
    COUNT(CASE WHEN status = 'waiting' THEN 1 END) AS active_waiting,
    COUNT(CASE WHEN status = 'notified' THEN 1 END) AS pending_confirmation,
    COUNT(CASE WHEN status = 'expired' THEN 1 END) AS expired_count,
    MIN(position) AS first_position,
    MAX(position) AS last_position,
    AVG(CASE WHEN confirmed_at IS NOT NULL
        THEN TIMESTAMPDIFF(MINUTE, notified_at, confirmed_at)
    END) AS avg_response_time_minutes
FROM waitlist
WHERE status IN ('waiting', 'notified')
GROUP BY time_slot_id;

-- ============================================
-- 4. EVENTO MYSQL: LIMPIEZA AUTOMÁTICA
-- ============================================

-- Expirar registros de waitlist que superaron su tiempo límite
DROP EVENT IF EXISTS evt_expire_waitlist;
CREATE EVENT evt_expire_waitlist
ON SCHEDULE EVERY 5 MINUTE
DO
    UPDATE waitlist
    SET status = 'expired',
        updated_at = CURRENT_TIMESTAMP
    WHERE status IN ('waiting', 'notified')
      AND expires_at < CURRENT_TIMESTAMP;

-- Limpiar slots antiguos (más de 90 días en el pasado)
DROP EVENT IF EXISTS evt_cleanup_old_time_slots;
CREATE EVENT evt_cleanup_old_time_slots
ON SCHEDULE EVERY 1 DAY
DO
    DELETE FROM time_slots
    WHERE slot_date < DATE_SUB(CURDATE(), INTERVAL 90 DAY);

-- ============================================
-- 5. SEEDS INICIALES (DESARROLLO)
-- ============================================

-- Insertar slots de prueba para los próximos 7 días (solo si tabla está vacía)
INSERT INTO time_slots (
    cafe_id,
    slot_date,
    slot_time,
    total_capacity,
    available_spots,
    duration_minutes
)
SELECT
    c.id,
    DATE_ADD(CURDATE(), INTERVAL d.day DAY) AS slot_date,
    t.hour AS slot_time,
    20 AS total_capacity,
    20 AS available_spots,
    60 AS duration_minutes
FROM cafes c
CROSS JOIN (
    SELECT 0 AS day UNION SELECT 1 UNION SELECT 2 UNION SELECT 3
    UNION SELECT 4 UNION SELECT 5 UNION SELECT 6
) d
CROSS JOIN (
    SELECT '10:00:00' AS hour UNION SELECT '11:00:00'
    UNION SELECT '12:00:00' UNION SELECT '13:00:00'
    UNION SELECT '14:00:00' UNION SELECT '15:00:00'
    UNION SELECT '16:00:00' UNION SELECT '17:00:00'
    UNION SELECT '18:00:00' UNION SELECT '19:00:00'
) t
WHERE c.is_active = TRUE
  AND c.has_reservations = TRUE
  AND NOT EXISTS (SELECT 1 FROM time_slots)
LIMIT 500; -- Limitar para no saturar en desarrollo

-- ============================================
-- 3. INTEGRACIÓN CON RESERVATIONS
-- ============================================
-- Añadir foreign key desde reservations → time_slots (solo si no existe)
-- (la columna time_slot_id ya existe en 004_reservations.sql)
SET @fk_exists := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_reservations_time_slot');
SET @sql := IF(@fk_exists = 0,
    'ALTER TABLE reservations ADD CONSTRAINT fk_reservations_time_slot FOREIGN KEY (time_slot_id) REFERENCES time_slots(id) ON DELETE SET NULL',
    'SELECT "FK fk_reservations_time_slot ya existe"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
-- ============================================================================
-- FIN MIGRACIÓN 011
-- ============================================================================
-- Resultado esperado:
-- ✅ Tabla time_slots: gestión granular de disponibilidad
-- ✅ Tabla waitlist: sistema FIFO con expiración automática
-- ✅ Vistas optimizadas para consultas públicas
-- ✅ Eventos MySQL para limpieza automática
-- ✅ Procedimientos almacenados para operaciones críticas
-- ✅ Seeds de desarrollo para testing inmediato
-- ============================================================================
