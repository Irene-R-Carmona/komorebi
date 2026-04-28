-- 025_performance_indexes.sql
-- Índices de rendimiento para consultas frecuentes
-- Aplica solo índices nuevos; los existentes no se duplican.
SET NAMES utf8mb4;

-- Productos activos (listado de menú: WHERE is_active = 1 AND deleted_at IS NULL)
ALTER TABLE products
    ADD INDEX idx_products_active (is_active, deleted_at);

-- Historial de reservas por usuario y estado
-- (idx_res_user y idx_res_status ya existen como columna simple; este compuesto cubre JOINs y WHERE user_id=? AND status=?)
ALTER TABLE reservations
    ADD INDEX idx_reservations_user_status (user_id, status);

-- Timeline de ítems en reserva (la tabla es reservation_items, no order_items)
-- idx_items_res (reservation_id) ya existe; este cubre ORDER BY created_at DESC
ALTER TABLE reservation_items
    ADD INDEX idx_reservation_items_timeline (reservation_id, created_at DESC);

-- Historial de revisiones de salud animal por fecha descendente + animal
-- (idx_animal_date y idx_check_date ya existen; este cubre listados por fecha con filtro animal)
ALTER TABLE animal_health_checks
    ADD INDEX idx_health_check_date (check_date DESC, animal_id);

-- Último sello de fidelización (last_stamp_at no tiene índice propio)
ALTER TABLE loyalty_cards
    ADD INDEX idx_loyalty_last_stamp (last_stamp_at DESC);
