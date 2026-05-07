# Migraciones de base de datos

Aplica con: `make db-migrate`

## Índice de migraciones

| Archivo                          | Descripción                                                                           | Fecha aprox. |
|----------------------------------|---------------------------------------------------------------------------------------|--------------|
| `001_infrastructure.sql`         | Estructura base: cafés, zonas, trackers y geolocalización                             | Nov 2025     |
| `002_users_rbac.sql`             | Usuarios, roles y permisos RBAC (59 permisos granulares)                              | Nov 2025     |
| `003_reviews.sql`                | Reseñas, calificaciones (1–5 estrellas) y logs de auditoría                           | Nov 2025     |
| `004_reservations.sql`           | Reservas, catálogo de productos, categorías de menú y alérgenos                       | Nov 2025     |
| `005_email_auth.sql`             | Verificación de email, recuperación de contraseña, sesiones y rate limiting           | Nov 2025     |
| `006_telegram_bot.sql`           | Vinculación de cuentas con Telegram vía BotFather                                     | Dic 2025     |
| `007_external_cache.sql`         | Caché de APIs externas: datos meteorológicos (Open-Meteo) y festivos (Nager.Date)     | Dic 2025     |
| `008_animals.sql`                | Animales, especies, reglas de bienestar e interacciones                               | Dic 2025     |
| `009_system_settings.sql`        | Configuración centralizada del sistema (tabla `settings` clave-valor)                 | Dic 2025     |
| `010_newsletter.sql`             | Suscripción a newsletter con double opt-in (conforme RGPD)                            | Dic 2025     |
| `011_time_slots_waitlist.sql`    | Gestión de disponibilidad horaria (time slots) y lista de espera                      | Ene 2026     |
| `012_waitlist.sql`               | Ajustes y vistas adicionales para la lista de espera                                  | Ene 2026     |
| `012b_reservation_triggers.sql`  | Triggers de sincronización reservas ↔ time slots (deshabilitados, lógica en servicio) | Ene 2026     |
| `013_loyalty_system.sql`         | Sistema de fidelización con tarjetas de sellos, niveles y recompensas                 | Ene 2026     |
| `014_staff_shifts.sql`           | Gestión de turnos de staff por café y fecha                                           | Feb 2026     |
| `015_animal_health_checks.sql`   | Chequeos diarios de salud de animales realizados por keepers                          | Feb 2026     |
| `016_supervisor_assignments.sql` | Asignaciones de supervisores a cafés y zonas                                          | Feb 2026     |
| `017_product_stock.sql`          | Control de stock por producto (`stock_quantity`; NULL = ilimitado, 0 = agotado)       | Abr 2026     |
| `018_api_tokens.sql`             | Tokens Bearer para autenticación stateless de clientes externos (hash SHA-256)        | Abr 2026     |

## Aplicar migraciones

```bash
make db-migrate    # Aplica solo migraciones pendientes
make db-seed       # Ejecuta seeders de datos de prueba
make db-reset      # ⚠️ Destructivo: borra y recrea toda la BD
```

Las migraciones se aplican mediante el script `scripts/apply-db.php`, que ejecuta cada archivo `.sql` en orden numérico.
El script es idempotente: usa `CREATE TABLE IF NOT EXISTS` y no vuelve a aplicar archivos ya ejecutados.

## Dependencias entre migraciones

Las migraciones deben aplicarse **en orden**. Las dependencias principales son:

- `002` depende de `001` (FK `users.cafe_id → cafes.id`)
- `003`, `004`, `005`, `006`, `007`, `008` dependen de `002`
- `011`, `012`, `012b` dependen de `001` y `004`
- `013`, `014`, `015`, `016` dependen de `002` y `001`
- `017` depende de `004` (FK `products`)
- `018` depende de `002` (FK `users`)

## Crear una nueva migración

1. Crea el archivo con el siguiente número de secuencia: `017_nombre_descriptivo.sql`
2. Incluye el encabezado estándar con módulo y dependencias
3. Usa siempre `CREATE TABLE IF NOT EXISTS` para que sea reentrante
4. Ejecuta `make db-migrate` para aplicar
