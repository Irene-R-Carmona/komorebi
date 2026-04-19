# Consolidación Arquitectural: Repository como único patrón de acceso a datos

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Eliminar el patrón Active Record de `app/Models/`, consolidar todo el acceso a datos en `app/Repositories/`, y reducir los modelos a domain objects puros (constantes, enums, Value Objects sin PDO).

**Architecture:** Controller → ServiceInterface → RepositoryInterface → AbstractRepository → PDO. Los modelos no importan PDO. La validación de dominio vive en Value Objects y en los servicios. La auditoría usa eventos PSR-14.

**Tech Stack:** PHP 8.4, PDO, AbstractRepository, Symfony EventDispatcher PSR-14, PHPUnit 13.

---

## Principios obligatorios en TODOS los archivos creados/modificados

- `declare(strict_types=1)` primera línea de cada PHP
- Clases globales: `use PDO; use Throwable; use Override;` — nunca FQFN `\PDO`
- Funciones nativas: siempre `\implode()`, `\array_map()`, etc.
- `#[Override]` en TODO método que sobreescribe o implementa interfaz
- Repositorios extienden `AbstractRepository` e implementan `getTable(): string` + `getSelectFields(): array`
- Services retornan `Result::ok()` / `Result::fail()` — nunca lanzan excepciones esperadas
- Registrar en `bootstrap/container.php` con patrón singleton via interfaz

---

## Hotfix Seguridad (PRIORITARIO — no espera el refactor incremental)

### Task 0: Fix Animal::resolveIncident() — eliminar query information_schema

**Files:**
- Modify: `app/Models/Animal.php` (método `resolveIncident`)
- Create: `migrations/019_animal_incidents_status.sql`

- [ ] **Step 1: Crear migración SQL** que añade columna `status` a `animal_incidents`

```sql
-- migrations/019_animal_incidents_status.sql
ALTER TABLE animal_incidents
    ADD COLUMN IF NOT EXISTS status VARCHAR(20) NOT NULL DEFAULT 'open',
    ADD COLUMN IF NOT EXISTS resolved_at DATETIME NULL;

CREATE INDEX IF NOT EXISTS idx_animal_incidents_status ON animal_incidents(status);
```

- [ ] **Step 2: Reescribir `resolveIncident()`** en `app/Models/Animal.php` — eliminar TODA la lógica de introspección de schema:

```php
public function resolveIncident(int $incidentId): bool
{
    $stmt = $this->getDb()->prepare(
        "UPDATE animal_incidents SET status = 'resolved', resolved_at = NOW() WHERE id = :id"
    );
    $stmt->bindValue('id', $incidentId, PDO::PARAM_INT);

    return $stmt->execute();
}
```

- [ ] **Step 3: Verificar** con `get_errors()` en `app/Models/Animal.php`

### Task 0b: Fix User::SELECT_FIELDS — separar campos sensibles

**Files:**
- Modify: `app/Models/User.php`

- [ ] **Step 1: Dividir SELECT_FIELDS** en dos constantes separadas:

```php
/** Campos seguros para consultas generales (sin datos de autenticación) */
private const array SELECT_FIELDS = [
    'id', 'uuid', 'name', 'email', 'is_active', 'cafe_id',
    'avatar', 'preferences', 'deleted_at', 'anonymized_at',
    'created_at', 'updated_at',
];

/** Campos de autenticación — solo para findByEmail() usado en login */
private const array AUTH_FIELDS = [
    'id', 'uuid', 'name', 'email', 'password', 'is_active',
    'cafe_id', 'login_attempts', 'locked_until', 'deleted_at',
];
```

- [ ] **Step 2: Actualizar `findByEmail()`** para usar `AUTH_FIELDS` en lugar de `SELECT_FIELDS`:

En `findByEmail()`, cambiar `self::SELECT_FIELDS` → `self::AUTH_FIELDS`

- [ ] **Step 3: Verificar** con `get_errors()` en `app/Models/User.php`

---

## BC allergen — rama: refactor/bc-allergen

### Task 1: Crear AllergenRepositoryInterface

**Files:**
- Create: `app/Repositories/Contracts/AllergenRepositoryInterface.php`

- [ ] **Step 1: Crear interfaz**

```php
<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

interface AllergenRepositoryInterface
{
    /** @return array<int, array<string, mixed>> */
    public function findAll(bool $orderBySeverity = true): array;

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array;

    /** @return array<string, mixed>|null */
    public function findByName(string $name): ?array;

    /** @return array<int, array<string, mixed>> */
    public function findBySeverity(string $severity): array;

    /** @return array<int, array<string, mixed>> */
    public function findByProduct(int $productId): array;

    /** @return array<int> */
    public function getProductIds(int $allergenId): array;

    /** @return array<int, array<string, mixed>> */
    public function getStatistics(): array;

    public function create(array $data): int;

    public function update(int $id, array $data): bool;

    public function attachToProduct(int $productId, int $allergenId, ?string $notes = null): bool;

    public function detachFromProduct(int $productId, int $allergenId): bool;
}
```

### Task 2: Crear AllergenCodeGenerator (Value Object puro)

**Files:**
- Create: `app/Domain/Allergen/AllergenCodeGenerator.php`

- [ ] **Step 1: Crear clase**

```php
<?php

declare(strict_types=1);

namespace App\Domain\Allergen;

/**
 * Genera códigos cortos de alérgenos a partir de un nombre.
 * Clase pura: sin PDO, sin estado, sin efectos secundarios.
 */
final class AllergenCodeGenerator
{
    private const int MAX_LENGTH = 10;

    public static function fromName(string $name): string
    {
        $transliterated = (string) \iconv('UTF-8', 'ASCII//TRANSLIT', $name);
        $normalized = (string) \preg_replace('/[^A-Za-z0-9]/', '', $transliterated);
        $normalized = \strtoupper($normalized);

        if ($normalized === '') {
            return 'ALLERGEN';
        }

        return \substr($normalized, 0, self::MAX_LENGTH);
    }
}
```

### Task 3: Crear AllergenRepository

**Files:**
- Create: `app/Repositories/AllergenRepository.php`

- [ ] **Step 1: Crear repositorio** absorbiendo toda la lógica de queries de `Allergen` model:

```php
<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Domain\Allergen\AllergenCodeGenerator;
use App\Repositories\Contracts\AllergenRepositoryInterface;
use InvalidArgumentException;
use Override;
use PDO;
use Throwable;

final class AllergenRepository extends AbstractRepository implements AllergenRepositoryInterface
{
    #[Override]
    protected function getTable(): string
    {
        return 'allergens';
    }

    #[Override]
    protected function getSelectFields(): array
    {
        return ['id', 'code', 'name', 'japanese_name', 'icon_class', 'icon_color', 'severity', 'description'];
    }

    #[Override]
    public function findAll(bool $orderBySeverity = true): array
    {
        $fields = \implode(', ', $this->getSelectFields());
        $order = $orderBySeverity
            ? "ORDER BY CASE severity WHEN 'high' THEN 1 WHEN 'medium' THEN 2 WHEN 'low' THEN 3 END, name ASC"
            : 'ORDER BY name ASC';

        $rows = $this->getDb()->query("SELECT {$fields} FROM allergens {$order}")->fetchAll(PDO::FETCH_ASSOC);

        return \array_map([$this, 'normalizeRow'], $rows);
    }

    #[Override]
    public function findById(int $id): ?array
    {
        $stmt = $this->getDb()->prepare('SELECT ' . \implode(', ', $this->getSelectFields()) . ' FROM allergens WHERE id = :id LIMIT 1');
        $stmt->bindValue('id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->normalizeRow($row) : null;
    }

    #[Override]
    public function findByName(string $name): ?array
    {
        $stmt = $this->getDb()->prepare('SELECT ' . \implode(', ', $this->getSelectFields()) . ' FROM allergens WHERE name = :name LIMIT 1');
        $stmt->bindValue('name', $name, PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->normalizeRow($row) : null;
    }

    #[Override]
    public function findBySeverity(string $severity): array
    {
        if (!\in_array($severity, ['low', 'medium', 'high'], true)) {
            throw new InvalidArgumentException("Severidad inválida: {$severity}");
        }

        $stmt = $this->getDb()->prepare('SELECT ' . \implode(', ', $this->getSelectFields()) . ' FROM allergens WHERE severity = :severity ORDER BY name');
        $stmt->bindValue('severity', $severity, PDO::PARAM_STR);
        $stmt->execute();

        return \array_map([$this, 'normalizeRow'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    #[Override]
    public function findByProduct(int $productId): array
    {
        $fields = \implode(', ', \array_map(static fn ($f) => "a.{$f}", $this->getSelectFields()));
        $stmt = $this->getDb()->prepare(
            "SELECT {$fields}
             FROM allergens a
             JOIN product_allergens pa ON a.id = pa.allergen_id
             WHERE pa.product_id = :product_id
             ORDER BY CASE a.severity WHEN 'high' THEN 1 WHEN 'medium' THEN 2 WHEN 'low' THEN 3 END, a.name"
        );
        $stmt->bindValue('product_id', $productId, PDO::PARAM_INT);
        $stmt->execute();

        return \array_map([$this, 'normalizeRow'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    #[Override]
    public function getProductIds(int $allergenId): array
    {
        $stmt = $this->getDb()->prepare('SELECT product_id FROM product_allergens WHERE allergen_id = :allergen_id ORDER BY product_id');
        $stmt->bindValue('allergen_id', $allergenId, PDO::PARAM_INT);
        $stmt->execute();

        return \array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'product_id');
    }

    #[Override]
    public function getStatistics(): array
    {
        $stmt = $this->getDb()->query(
            'SELECT a.id, a.name, COUNT(pa.product_id) as product_count
             FROM allergens a
             LEFT JOIN product_allergens pa ON a.id = pa.allergen_id
             GROUP BY a.id, a.name
             ORDER BY product_count DESC, a.name'
        );

        return \array_map([$this, 'normalizeRow'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    #[Override]
    public function create(array $data): int
    {
        $severity = $data['severity'] ?? 'medium';
        if (!\in_array($severity, ['low', 'medium', 'high'], true)) {
            throw new InvalidArgumentException("Severidad inválida: {$severity}");
        }

        $code = isset($data['code']) ? \strtoupper(\substr(\trim((string) $data['code']), 0, 10)) : '';
        if ($code === '') {
            if (empty($data['name'])) {
                throw new InvalidArgumentException('El código o el nombre son obligatorios para crear un alérgeno.');
            }
            $code = AllergenCodeGenerator::fromName($data['name']);
        }

        $name = \trim($data['name'] ?? '');
        if ($name === '') {
            throw new InvalidArgumentException('El nombre del alérgeno es obligatorio.');
        }

        $this->getDb()->beginTransaction();

        try {
            $stmt = $this->getDb()->prepare(
                'INSERT INTO allergens (code, name, japanese_name, icon_class, icon_color, severity, description)
                 VALUES (:code, :name, :japanese_name, :icon_class, :icon_color, :severity, :description)'
            );
            $stmt->execute([
                'code'          => $code,
                'name'          => $name,
                'japanese_name' => $data['name_jp'] ?? $data['japanese_name'] ?? null,
                'icon_class'    => $data['icon'] ?? $data['icon_class'] ?? null,
                'icon_color'    => $data['icon_color'] ?? null,
                'severity'      => $severity,
                'description'   => $data['description'] ?? null,
            ]);
            $id = (int) $this->getDb()->lastInsertId();
            $this->getDb()->commit();

            return $id;
        } catch (Throwable $e) {
            $this->getDb()->rollBack();
            throw $e;
        }
    }

    #[Override]
    public function update(int $id, array $data): bool
    {
        $severity = $data['severity'] ?? 'medium';
        if (!\in_array($severity, ['low', 'medium', 'high'], true)) {
            throw new InvalidArgumentException("Severidad inválida: {$severity}");
        }

        $name = \trim($data['name'] ?? '');
        if ($name === '') {
            throw new InvalidArgumentException('El nombre del alérgeno es obligatorio.');
        }

        $code = isset($data['code']) ? \strtoupper(\substr(\trim((string) $data['code']), 0, 10)) : '';
        if ($code === '') {
            $existing = $this->findById($id);
            $code = ($existing && !empty($existing['code']))
                ? $existing['code']
                : AllergenCodeGenerator::fromName($name);
        }

        $stmt = $this->getDb()->prepare(
            'UPDATE allergens SET code = :code, name = :name, japanese_name = :japanese_name,
             icon_class = :icon_class, icon_color = :icon_color, severity = :severity,
             description = :description WHERE id = :id'
        );

        return $stmt->execute([
            'id'            => $id,
            'code'          => $code,
            'name'          => $name,
            'japanese_name' => $data['name_jp'] ?? $data['japanese_name'] ?? null,
            'icon_class'    => $data['icon'] ?? $data['icon_class'] ?? null,
            'icon_color'    => $data['icon_color'] ?? null,
            'severity'      => $severity,
            'description'   => $data['description'] ?? null,
        ]);
    }

    #[Override]
    public function attachToProduct(int $productId, int $allergenId, ?string $notes = null): bool
    {
        $stmt = $this->getDb()->prepare(
            'INSERT IGNORE INTO product_allergens (product_id, allergen_id, notes) VALUES (:product_id, :allergen_id, :notes)'
        );

        return $stmt->execute(['product_id' => $productId, 'allergen_id' => $allergenId, 'notes' => $notes]);
    }

    #[Override]
    public function detachFromProduct(int $productId, int $allergenId): bool
    {
        $stmt = $this->getDb()->prepare(
            'DELETE FROM product_allergens WHERE product_id = :product_id AND allergen_id = :allergen_id'
        );

        return $stmt->execute(['product_id' => $productId, 'allergen_id' => $allergenId]);
    }

    /**
     * Normaliza alias legacy: japanese_name→name_jp, icon_class→icon.
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeRow(array $row): array
    {
        if (isset($row['japanese_name']) && !isset($row['name_jp'])) {
            $row['name_jp'] = $row['japanese_name'];
        }
        if (isset($row['icon_class']) && !isset($row['icon'])) {
            $row['icon'] = $row['icon_class'];
        }

        return $row;
    }
}
```

### Task 4: Actualizar AllergenService para inyectar AllergenRepositoryInterface

**Files:**
- Modify: `app/Services/AllergenService.php`

- [ ] **Step 1: Reemplazar inyección de modelo concreto por interfaz de repositorio**

Cambiar constructor: `private Allergen $model` → `private AllergenRepositoryInterface $repository`
Actualizar todas las llamadas: `$this->model->getAll()` → `$this->repository->findAll()`, etc.
Añadir `use App\Repositories\Contracts\AllergenRepositoryInterface;`
Eliminar `use App\Models\Allergen;`

### Task 5: Reducir Allergen model a domain object puro

**Files:**
- Modify: `app/Models/Allergen.php`

- [ ] **Step 1: Eliminar TODOS los métodos query** — dejar solo constantes:

```php
<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Domain object para Allergen.
 * Solo constantes de dominio — sin PDO, sin queries.
 */
final class Allergen
{
    public const string SEVERITY_LOW    = 'low';
    public const string SEVERITY_MEDIUM = 'medium';
    public const string SEVERITY_HIGH   = 'high';

    public const array VALID_SEVERITIES = [
        self::SEVERITY_LOW,
        self::SEVERITY_MEDIUM,
        self::SEVERITY_HIGH,
    ];
}
```

### Task 6: Registrar AllergenRepository en container

**Files:**
- Modify: `bootstrap/container.php`

- [ ] **Step 1: Añadir bindings** antes de la sección "Fase 2: boot()":

```php
use App\Repositories\AllergenRepository;
use App\Repositories\Contracts\AllergenRepositoryInterface;
use App\Services\AllergenService;
use App\Services\Contracts\AllergenServiceInterface;

Container::singleton(AllergenRepositoryInterface::class, fn () => new AllergenRepository(
    Container::make(\PDO::class)
));
Container::singleton(AllergenServiceInterface::class, fn () => new AllergenService(
    Container::make(AllergenRepositoryInterface::class)
));
```

### Task 7: Escribir tests unitarios para AllergenRepository y AllergenCodeGenerator

**Files:**
- Create: `tests/Unit/Repositories/AllergenRepositoryTest.php`
- Create: `tests/Unit/Domain/Allergen/AllergenCodeGeneratorTest.php`

- [ ] **Step 1: Test de AllergenCodeGenerator** (puro, sin DB):

```php
<?php
/**
 * ¿Qué prueba aquí? Generación de códigos cortos de alérgenos desde nombres arbitrarios.
 * ¿Qué me quieres demostrar? fromName() normaliza, translitea y trunca correctamente.
 * ¿Qué va a fallar si se cambia el código? Si se cambia la longitud máxima, el charset o el fallback.
 */
declare(strict_types=1);

namespace Tests\Unit\Domain\Allergen;

use App\Domain\Allergen\AllergenCodeGenerator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AllergenCodeGenerator::class)]
final class AllergenCodeGeneratorTest extends TestCase
{
    public function testFromNameAscii(): void
    {
        $this->assertSame('GLUTEN', AllergenCodeGenerator::fromName('Gluten'));
    }

    public function testFromNameTruncatesAt10(): void
    {
        $this->assertSame('CRUSTACEOS', AllergenCodeGenerator::fromName('Crustáceos y derivados'));
    }

    public function testFromNameTransliteratesAccents(): void
    {
        $code = AllergenCodeGenerator::fromName('Lácteos');
        $this->assertMatchesRegularExpression('/^[A-Z0-9]+$/', $code);
        $this->assertLessThanOrEqual(10, \strlen($code));
    }

    public function testFromNameEmptyStringReturnsAllergen(): void
    {
        $this->assertSame('ALLERGEN', AllergenCodeGenerator::fromName(''));
    }

    public function testFromNameOnlySpecialCharsReturnsAllergen(): void
    {
        $this->assertSame('ALLERGEN', AllergenCodeGenerator::fromName('!@#$%'));
    }
}
```

- [ ] **Step 2: Test de AllergenRepository** (con stubs PDO):
Ver patrón en `tests/Support/RepositoryTestCase.php` — crear usando `makePdoWithStmt()`.

---

## Estado del plan

- [x] Task 0: Fix Animal::resolveIncident()
- [x] Task 0b: Fix User::SELECT_FIELDS
- [x] Task 1: AllergenRepositoryInterface
- [x] Task 2: AllergenCodeGenerator
- [x] Task 3: AllergenRepository
- [x] Task 4: AllergenService actualizado
- [x] Task 5: Allergen model reducido
- [x] Task 6: Registrar en container
- [x] Task 7: Tests unitarios

