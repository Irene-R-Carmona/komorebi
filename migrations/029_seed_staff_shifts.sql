-- Seed de turnos de staff para la semana ISO actual (lunes a domingo)
-- Usa subqueries por slug de rol a través de user_roles (RBAC many-to-many).
-- Si algún rol no tiene usuarios activos, las filas correspondientes se omiten.

SET @week_monday = DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY);
SET @cafe_id     = (SELECT id FROM cafes ORDER BY id LIMIT 1);
SET @created_by  = (SELECT u.id FROM users u JOIN user_roles ur ON ur.user_id = u.id JOIN roles r ON r.id = ur.role_id WHERE r.slug = 'manager'    AND u.is_active = 1 ORDER BY u.id LIMIT 1);
SET @supervisor  = (SELECT u.id FROM users u JOIN user_roles ur ON ur.user_id = u.id JOIN roles r ON r.id = ur.role_id WHERE r.slug = 'supervisor' AND u.is_active = 1 ORDER BY u.id LIMIT 1);
SET @reception   = (SELECT u.id FROM users u JOIN user_roles ur ON ur.user_id = u.id JOIN roles r ON r.id = ur.role_id WHERE r.slug = 'reception'  AND u.is_active = 1 ORDER BY u.id LIMIT 1);
SET @kitchen     = (SELECT u.id FROM users u JOIN user_roles ur ON ur.user_id = u.id JOIN roles r ON r.id = ur.role_id WHERE r.slug = 'kitchen'    AND u.is_active = 1 ORDER BY u.id LIMIT 1);
SET @keeper      = (SELECT u.id FROM users u JOIN user_roles ur ON ur.user_id = u.id JOIN roles r ON r.id = ur.role_id WHERE r.slug = 'keeper'     AND u.is_active = 1 ORDER BY u.id LIMIT 1);

INSERT INTO staff_shifts (user_id, cafe_id, shift_date, shift_start, shift_end, notes, created_by)
SELECT user_id, cafe_id, shift_date, shift_start, shift_end, notes, created_by
FROM (
-- Lunes
    SELECT @supervisor AS user_id, @cafe_id AS cafe_id, @week_monday AS shift_date, '09:00:00' AS shift_start, '17:00:00' AS shift_end, 'Turno mañana' AS notes, @created_by AS created_by UNION ALL
    SELECT @reception,  @cafe_id, @week_monday,                              '10:00:00', '18:00:00', 'Turno mañana',                    @created_by UNION ALL
    SELECT @kitchen,    @cafe_id, @week_monday,                              '08:00:00', '16:00:00', 'Apertura cocina',                 @created_by UNION ALL
    SELECT @keeper,     @cafe_id, @week_monday,                              '09:00:00', '17:00:00', 'Cuidado animales',                @created_by UNION ALL
    -- Martes
    SELECT @supervisor, @cafe_id, DATE_ADD(@week_monday, INTERVAL 1 DAY),   '09:00:00', '17:00:00', 'Turno mañana',                    @created_by UNION ALL
    SELECT @reception,  @cafe_id, DATE_ADD(@week_monday, INTERVAL 1 DAY),   '14:00:00', '22:00:00', 'Turno tarde',                     @created_by UNION ALL
    SELECT @kitchen,    @cafe_id, DATE_ADD(@week_monday, INTERVAL 1 DAY),   '12:00:00', '20:00:00', 'Turno mediodía',                  @created_by UNION ALL
    SELECT @keeper,     @cafe_id, DATE_ADD(@week_monday, INTERVAL 1 DAY),   '09:00:00', '15:00:00', 'Revisión veterinaria',            @created_by UNION ALL
    -- Miércoles
    SELECT @supervisor, @cafe_id, DATE_ADD(@week_monday, INTERVAL 2 DAY),   '14:00:00', '22:00:00', 'Turno tarde',                     @created_by UNION ALL
    SELECT @reception,  @cafe_id, DATE_ADD(@week_monday, INTERVAL 2 DAY),   '09:00:00', '17:00:00', 'Turno mañana',                    @created_by UNION ALL
    SELECT @kitchen,    @cafe_id, DATE_ADD(@week_monday, INTERVAL 2 DAY),   '08:00:00', '16:00:00', 'Apertura cocina',                 @created_by UNION ALL
    SELECT @keeper,     @cafe_id, DATE_ADD(@week_monday, INTERVAL 2 DAY),   '10:00:00', '18:00:00', 'Actividades con visitantes',      @created_by UNION ALL
    -- Jueves
    SELECT @supervisor, @cafe_id, DATE_ADD(@week_monday, INTERVAL 3 DAY),   '09:00:00', '17:00:00', NULL,                              @created_by UNION ALL
    SELECT @reception,  @cafe_id, DATE_ADD(@week_monday, INTERVAL 3 DAY),   '09:00:00', '17:00:00', NULL,                              @created_by UNION ALL
    SELECT @kitchen,    @cafe_id, DATE_ADD(@week_monday, INTERVAL 3 DAY),   '12:00:00', '20:00:00', 'Turno mediodía',                  @created_by UNION ALL
    -- Viernes
    SELECT @supervisor, @cafe_id, DATE_ADD(@week_monday, INTERVAL 4 DAY),   '09:00:00', '17:00:00', NULL,                              @created_by UNION ALL
    SELECT @reception,  @cafe_id, DATE_ADD(@week_monday, INTERVAL 4 DAY),   '14:00:00', '22:00:00', 'Turno tarde',                     @created_by UNION ALL
    SELECT @kitchen,    @cafe_id, DATE_ADD(@week_monday, INTERVAL 4 DAY),   '08:00:00', '16:00:00', 'Apertura cocina',                 @created_by UNION ALL
    SELECT @keeper,     @cafe_id, DATE_ADD(@week_monday, INTERVAL 4 DAY),   '09:00:00', '17:00:00', NULL,                              @created_by UNION ALL
    -- Sábado
    SELECT @supervisor, @cafe_id, DATE_ADD(@week_monday, INTERVAL 5 DAY),   '09:00:00', '17:00:00', 'Fin de semana',                   @created_by UNION ALL
    SELECT @reception,  @cafe_id, DATE_ADD(@week_monday, INTERVAL 5 DAY),   '09:00:00', '17:00:00', 'Fin de semana',                   @created_by UNION ALL
    SELECT @kitchen,    @cafe_id, DATE_ADD(@week_monday, INTERVAL 5 DAY),   '10:00:00', '18:00:00', 'Servicio especial fin de semana', @created_by UNION ALL
    SELECT @keeper,     @cafe_id, DATE_ADD(@week_monday, INTERVAL 5 DAY),   '09:00:00', '17:00:00', 'Fin de semana',                   @created_by UNION ALL
    -- Domingo
    SELECT @reception,  @cafe_id, DATE_ADD(@week_monday, INTERVAL 6 DAY),   '10:00:00', '16:00:00', 'Turno reducido domingo',          @created_by UNION ALL
    SELECT @kitchen,    @cafe_id, DATE_ADD(@week_monday, INTERVAL 6 DAY),   '10:00:00', '16:00:00', 'Turno reducido domingo',          @created_by UNION ALL
    SELECT @keeper,     @cafe_id, DATE_ADD(@week_monday, INTERVAL 6 DAY),   '10:00:00', '16:00:00', 'Turno reducido domingo',          @created_by
) AS seed_data
WHERE user_id IS NOT NULL
  AND cafe_id IS NOT NULL
  AND created_by IS NOT NULL;
