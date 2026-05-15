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

| `019_schema_migrations.sql`      | Tabla de trazabilidad de migraciones aplicadas (`schema_migrations`)                  | May 2026     |

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

1. Crea el archivo con el siguiente número de secuencia: `020_nombre_descriptivo.sql`
2. Incluye el encabezado estándar con módulo y dependencias
3. Usa siempre `CREATE TABLE IF NOT EXISTS` para que sea reentrante
4. Ejecuta `make db-migrate` para aplicar

---

## Reglas obligatorias para migraciones seguras en producción

> Estas reglas son válidas para todos los entornos que usen auto-deploy continuo (Railway, etc.)
> donde un commit a `main` desencadena el deploy sin aprobación manual.

### 1. Expand-only: añadir antes de borrar

Nunca eliminar una columna o tabla en el mismo deploy en que se elimina el código que la usa.
Sigue el patrón de **dos fases** para cualquier eliminación:

| Fase | Cuándo | Qué se hace |
|------|--------|-------------|
| Fase 1 | Deploy N | El código deja de leer/escribir la columna/tabla. La estructura sigue en BD. |
| Fase 2 | Deploy N+1 (días/semanas después) | La columna/tabla se borra mediante migración. |

Esto garantiza que si se hace rollback al deploy anterior, la BD sigue siendo compatible.

### 2. Sin transacciones alrededor de DDL

MySQL hace commit implícito antes y después de cualquier sentencia DDL (`CREATE TABLE`, `ALTER TABLE`, `DROP`).
Envolver DDL en `BEGIN ... ROLLBACK` **no funciona** — el `ROLLBACK` no deshace el DDL.
Por ello, las migraciones de este proyecto no usan transacciones. Cada sentencia debe ser idempotente por sí misma (`IF NOT EXISTS`, `IF EXISTS`).

### 3. Datos de configuración vs datos de desarrollo

| Tipo de dato | Dónde va | Llega a producción |
|---|---|---|
| Estructura de tabla | Migración SQL | ✅ Sí, en cada deploy |
| Datos de configuración base (roles, permisos, settings) | Migración SQL con `INSERT IGNORE` | ✅ Sí |
| Datos de desarrollo / demo (usuarios, reservas, animales de prueba) | Seeders (`app/Core/Seeders/`) | ❌ Nunca (bloqueado en `APP_ENV=production`) |

**Regla de oro:** Si un dato debe existir en producción desde el primer deploy, ponlo en una migración con `INSERT IGNORE INTO`. Los seeders son exclusivamente para entornos locales y de staging.

### 4. Guard de producción en seeders

El script `scripts/apply-db.php` tiene una guardia explícita:

- `APP_ENV=production` → los seeders **nunca** se ejecutan, salvo `FORCE_SEED=1` en Railway Variables.
- `--force` (alias legacy) y `--no-interaction` solo controlan si se muestra el prompt TTY.
- Para re-sembrar intencionalmente en staging: `--force-seed` o `FORCE_SEED=1`.

### 5. Tabla `schema_migrations` (desde migración 019)

Desde la migración 019, `apply-db.php` registra cada migración aplicada en la tabla `schema_migrations`.
En deploys sucesivos, las migraciones ya registradas se **saltan** automáticamente — no se re-ejecutan aunque el archivo siga en `migrations/`.
Excepción: la propia migración `019_schema_migrations.sql` se aplica siempre con `CREATE TABLE IF NOT EXISTS`.
