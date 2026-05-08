-- Seed de turnos de staff para la semana ISO actual (lunes a domingo)
-- Usa subqueries por slug de rol para no depender de IDs hardcodeados.
-- Si algún rol no tiene usuarios, la fila correspondiente se omite (NULL en FK).

SET @week_monday = DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY);
SET @cafe_id     = (SELECT id FROM cafes ORDER BY id LIMIT 1);
SET @created_by  = (SELECT id FROM users WHERE role_id = (SELECT id FROM roles WHERE slug = 'manager') ORDER BY id LIMIT 1);
SET @supervisor  = (SELECT id FROM users WHERE role_id = (SELECT id FROM roles WHERE slug = 'supervisor') ORDER BY id LIMIT 1);
SET @reception   = (SELECT id FROM users WHERE role_id = (SELECT id FROM roles WHERE slug = 'reception') ORDER BY id LIMIT 1);
SET @kitchen     = (SELECT id FROM users WHERE role_id = (SELECT id FROM roles WHERE slug = 'kitchen') ORDER BY id LIMIT 1);
SET @keeper      = (SELECT id FROM users WHERE role_id = (SELECT id FROM roles WHERE slug = 'keeper') ORDER BY id LIMIT 1);

INSERT INTO staff_shifts (user_id, cafe_id, shift_date, shift_start, shift_end, notes, created_by) VALUES
-- Lunes
(@supervisor, @cafe_id, @week_monday,                               '09:00:00', '17:00:00', 'Turno mañana',                     @created_by),
(@reception,  @cafe_id, @week_monday,                               '10:00:00', '18:00:00', 'Turno mañana',                     @created_by),
(@kitchen,    @cafe_id, @week_monday,                               '08:00:00', '16:00:00', 'Apertura cocina',                  @created_by),
(@keeper,     @cafe_id, @week_monday,                               '09:00:00', '17:00:00', 'Cuidado animales',                 @created_by),

-- Martes
(@supervisor, @cafe_id, DATE_ADD(@week_monday, INTERVAL 1 DAY),     '09:00:00', '17:00:00', 'Turno mañana',                     @created_by),
(@reception,  @cafe_id, DATE_ADD(@week_monday, INTERVAL 1 DAY),     '14:00:00', '22:00:00', 'Turno tarde',                      @created_by),
(@kitchen,    @cafe_id, DATE_ADD(@week_monday, INTERVAL 1 DAY),     '12:00:00', '20:00:00', 'Turno mediodía',                   @created_by),
(@keeper,     @cafe_id, DATE_ADD(@week_monday, INTERVAL 1 DAY),     '09:00:00', '15:00:00', 'Revisión veterinaria',             @created_by),

-- Miércoles
(@supervisor, @cafe_id, DATE_ADD(@week_monday, INTERVAL 2 DAY),     '14:00:00', '22:00:00', 'Turno tarde',                      @created_by),
(@reception,  @cafe_id, DATE_ADD(@week_monday, INTERVAL 2 DAY),     '09:00:00', '17:00:00', 'Turno mañana',                     @created_by),
(@kitchen,    @cafe_id, DATE_ADD(@week_monday, INTERVAL 2 DAY),     '08:00:00', '16:00:00', 'Apertura cocina',                  @created_by),
(@keeper,     @cafe_id, DATE_ADD(@week_monday, INTERVAL 2 DAY),     '10:00:00', '18:00:00', 'Actividades con visitantes',       @created_by),

-- Jueves
(@supervisor, @cafe_id, DATE_ADD(@week_monday, INTERVAL 3 DAY),     '09:00:00', '17:00:00', NULL,                               @created_by),
(@reception,  @cafe_id, DATE_ADD(@week_monday, INTERVAL 3 DAY),     '09:00:00', '17:00:00', NULL,                               @created_by),
(@kitchen,    @cafe_id, DATE_ADD(@week_monday, INTERVAL 3 DAY),     '12:00:00', '20:00:00', 'Turno mediodía',                   @created_by),

-- Viernes
(@supervisor, @cafe_id, DATE_ADD(@week_monday, INTERVAL 4 DAY),     '09:00:00', '17:00:00', NULL,                               @created_by),
(@reception,  @cafe_id, DATE_ADD(@week_monday, INTERVAL 4 DAY),     '14:00:00', '22:00:00', 'Turno tarde',                      @created_by),
(@kitchen,    @cafe_id, DATE_ADD(@week_monday, INTERVAL 4 DAY),     '08:00:00', '16:00:00', 'Apertura cocina',                  @created_by),
(@keeper,     @cafe_id, DATE_ADD(@week_monday, INTERVAL 4 DAY),     '09:00:00', '17:00:00', NULL,                               @created_by),

-- Sábado
(@supervisor, @cafe_id, DATE_ADD(@week_monday, INTERVAL 5 DAY),     '09:00:00', '17:00:00', 'Fin de semana',                    @created_by),
(@reception,  @cafe_id, DATE_ADD(@week_monday, INTERVAL 5 DAY),     '09:00:00', '17:00:00', 'Fin de semana',                    @created_by),
(@kitchen,    @cafe_id, DATE_ADD(@week_monday, INTERVAL 5 DAY),     '10:00:00', '18:00:00', 'Servicio especial fin de semana',  @created_by),
(@keeper,     @cafe_id, DATE_ADD(@week_monday, INTERVAL 5 DAY),     '09:00:00', '17:00:00', 'Fin de semana',                    @created_by),

-- Domingo
(@reception,  @cafe_id, DATE_ADD(@week_monday, INTERVAL 6 DAY),     '10:00:00', '16:00:00', 'Turno reducido domingo',           @created_by),
(@kitchen,    @cafe_id, DATE_ADD(@week_monday, INTERVAL 6 DAY),     '10:00:00', '16:00:00', 'Turno reducido domingo',           @created_by),
(@keeper,     @cafe_id, DATE_ADD(@week_monday, INTERVAL 6 DAY),     '10:00:00', '16:00:00', 'Turno reducido domingo',           @created_by);

