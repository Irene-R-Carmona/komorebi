---
mode: agent
description: "Plantilla para crear una migración SQL numerada. Detecta el número siguiente, crea el archivo en migrations/ y verifica la sintaxis."
---

# Nueva Migración SQL

Voy a crear una migración SQL con el número secuencial correcto.

## Parámetros

**Descripción de la migración:** ${input:migration_description:Ej: add_loyalty_points_to_users}
**Operación principal:** ${input:operation:Ej: CREATE TABLE, ALTER TABLE, ADD INDEX, DROP COLUMN}

## Lo que voy a hacer

### 1. Detectar el número siguiente

Leer `migrations/` para encontrar el número más alto actual y usar el siguiente.
Formato: `NNN_nombre_en_snake_case.sql` (3 dígitos, con ceros a la izquierda).

### 2. Crear el archivo

Ubicación: `migrations/NNN_${input:migration_description:}.sql`

Estructura estándar:

```sql
-- ============================================================
-- Migration NNN: ${input:migration_description:}
-- Created: YYYY-MM-DD
-- ============================================================

-- ${input:operation:}

-- Ejemplo de CREATE TABLE con buenas prácticas:
CREATE TABLE IF NOT EXISTS table_name (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    -- campos de negocio
    created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    -- índices de las columnas usadas en WHERE / JOIN
    INDEX idx_table_name_col (col)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
```

### Convenciones de migraciones en este proyecto

- **Sin framework ORM** — SQL puro
- `IF NOT EXISTS` / `IF EXISTS` para idempotencia
- `ENGINE=InnoDB`, `CHARSET=utf8mb4`, `COLLATE=utf8mb4_unicode_ci` siempre
- Foreign keys con `ON DELETE` y `ON UPDATE` explícitos
- Nombres de índices: `idx_{tabla}_{columna}` o `uq_{tabla}_{columna}` para únicos
- Timestamps en UTC: `TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP`
- IDs: `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT`
- Campos booleanos: `TINYINT(1) NOT NULL DEFAULT 0`
- Campos de texto variable: `VARCHAR(255)` máximo (o `TEXT` para contenido largo)
- **No incluir datos de seed** — usar seeders separados en `scripts/`

### 3. Aplicar la migración

```bash
make db-migrate   # Aplica todas las migraciones pendientes
make db-verify    # Verifica el estado del esquema
```

### 4. Actualizar README de migrations si es un módulo nuevo

Si la migración crea un módulo nuevo, añadir entrada en `migrations/README.md`.

## Checklist de validación

- [ ] Número secuencial correcto (sin saltos, sin duplicados)
- [ ] `IF NOT EXISTS` / `IF EXISTS` para idempotencia
- [ ] `ENGINE=InnoDB DEFAULT CHARSET=utf8mb4`
- [ ] Índices en columnas de búsqueda frecuente
- [ ] `make db-migrate` ejecutado sin errores
- [ ] `make db-verify` confirma el estado

---
**Referencia:** `migrations/README.md` | `scripts/apply-db.php` | `make db-migrate`

