---
mode: agent
description: "Scaffolding de Repository extendiendo AbstractRepository. Implementa los dos métodos abstractos obligatorios y añade queries PDO personalizadas."
---

# Nuevo Repository — Scaffolding

Voy a crear un nuevo repository extendiendo `AbstractRepository`.

## Parámetros

**Nombre del repository:** ${input:repository_name:Ej: ProductRepository}
**Tabla de BD:** ${input:table_name:Ej: products}
**Campos SELECT:** ${input:select_fields:Ej: id, slug, name, price, is_active}

## Lo que voy a generar

### 1. El Repository
Ubicación: `app/Repositories/${input:repository_name:}.php`

Reglas obligatorias:
- `declare(strict_types=1)` — primera línea
- Es `final` — nunca se extiende
- Extiende `AbstractRepository`
- `#[\Override]` en `getTable()` y `getSelectFields()`
- **Nunca `SELECT *`** — siempre campos explícitos
- PDO: `$this->db` (de AbstractRepository) para queries
- `Database::getConnection()` para obtener PDO directamente si se necesita fuera del constructor
- Nunca `Container::make(PDO::class)` en el constructor del repository

```php
<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Repositories\AbstractRepository;

final class ${input:repository_name:} extends AbstractRepository
{
    #[\Override]
    protected function getTable(): string
    {
        return '${input:table_name:}';
    }

    #[\Override]
    protected function getSelectFields(): array
    {
        return [${input:select_fields:}];
    }

    // Queries personalizadas usando $this->db (PDO)
    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT ' . implode(', ', $this->getSelectFields()) .
            ' FROM ' . $this->getTable() .
            ' WHERE slug = :slug AND is_active = 1 LIMIT 1'
        );
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }
}
```

### 2. Registro en `bootstrap/container.php`

```php
Container::singleton(
    ${input:repository_name:}::class,
    fn() => new ${input:repository_name:}(Database::getConnection())
);
```

### 3. Tests de integración (si hay queries custom)
En `tests/Integration/${input:repository_name:}Test.php`:
- Extiende `BaseIntegrationTest`
- Docblock obligatorio (3 preguntas)
- Usa IDs altos para fixtures de test (`const TEST_ID = 99999`)

## Checklist de validación

- [ ] `declare(strict_types=1)` presente
- [ ] Repository es `final`
- [ ] `#[\Override]` en `getTable()` y `getSelectFields()`
- [ ] Sin `SELECT *` — campos explícitos
- [ ] Registrado en `bootstrap/container.php`
- [ ] `make phpstan` pasa sin errores nuevos

---
**Referencia:** `.github/instructions/php-backend.instructions.md` | `app/Repositories/AbstractRepository.php`

