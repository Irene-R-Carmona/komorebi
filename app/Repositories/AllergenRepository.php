<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Domain\Allergen\AllergenCodeGenerator;
use App\Domain\DTO\AllergenDTO;
use App\Domain\Mappers\AllergenMapper;
use App\Repositories\Contracts\AllergenRepositoryInterface;
use InvalidArgumentException;
use Override;
use PDO;
use PDOStatement;
use Throwable;

/**
 * Repositorio de Alérgenos.
 *
 * Encapsula el acceso a datos de alérgenos y su relación con productos.
 * Extiende AbstractRepository para operaciones CRUD base y métricas de queries.
 * Implementa la normalización de alias legacy (name_jp, icon).
 */
final class AllergenRepository extends AbstractRepository implements AllergenRepositoryInterface
{
    private const array VALID_SEVERITIES = ['low', 'medium', 'high'];

    public function __construct(private readonly AllergenMapper $mapper, ?PDO $db = null)
    {
        parent::__construct($db);
    }

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

    // ─────────────────────────────────────────────────────────────
    // Consultas de lectura
    // ─────────────────────────────────────────────────────────────

    /**
     * @return array<int, AllergenDTO>
     */
    #[Override]
    public function findAll(bool $orderBySeverity = true): array
    {
        $fields = \implode(', ', $this->getSelectFields());
        $order = $orderBySeverity
            ? "ORDER BY CASE severity WHEN 'high' THEN 1 WHEN 'medium' THEN 2 WHEN 'low' THEN 3 END, name ASC"
            : 'ORDER BY name ASC';

        /** @var PDOStatement $stmt */
        $stmt = $this->execTimed(
            fn() => $this->getDb()->query("SELECT $fields FROM allergens $order"),
            "SELECT $fields FROM allergens $order"
        );

        return \array_map([$this->mapper, 'toDTO'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    #[Override]
    public function findById(int $id): ?AllergenDTO
    {
        $fields = \implode(', ', $this->getSelectFields());
        $sql = "SELECT $fields FROM allergens WHERE id = :id LIMIT 1";
        $params = ['id' => $id];

        $stmt = $this->getDb()->prepare($sql);
        $this->execTimed(fn() => $stmt->execute($params), $sql, $params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->mapper->toDTO($row) : null;
    }

    #[Override]
    public function findByName(string $name): ?array
    {
        $fields = \implode(', ', $this->getSelectFields());
        $sql = "SELECT $fields FROM allergens WHERE name = :name LIMIT 1";
        $params = ['name' => $name];

        $stmt = $this->getDb()->prepare($sql);
        $this->execTimed(fn() => $stmt->execute($params), $sql, $params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->normalizeRow($row) : null;
    }

    #[Override]
    public function findBySeverity(string $severity): array
    {
        if (!\in_array($severity, self::VALID_SEVERITIES, true)) {
            throw new InvalidArgumentException("Severidad inválida: $severity. Permitidas: low, medium, high.");
        }

        $fields = \implode(', ', $this->getSelectFields());
        $sql = "SELECT $fields FROM allergens WHERE severity = :severity ORDER BY name";
        $params = ['severity' => $severity];

        $stmt = $this->getDb()->prepare($sql);
        $this->execTimed(fn() => $stmt->execute($params), $sql, $params);

        return \array_map([$this, 'normalizeRow'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    #[Override]
    public function findByProduct(int $productId): array
    {
        $fields = \implode(', ', \array_map(static fn(string $f) => "a.$f", $this->getSelectFields()));
        $sql = "SELECT {$fields}, pa.notes AS allergen_notes
                FROM allergens a
                JOIN product_allergens pa ON a.id = pa.allergen_id
                WHERE pa.product_id = :product_id
                ORDER BY CASE a.severity WHEN 'high' THEN 1 WHEN 'medium' THEN 2 WHEN 'low' THEN 3 END, a.name";
        $params = ['product_id' => $productId];

        $stmt = $this->getDb()->prepare($sql);
        $this->execTimed(fn() => $stmt->execute($params), $sql, $params);

        return \array_map([$this, 'normalizeRow'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    #[Override]
    public function getProductIds(int $allergenId): array
    {
        $sql = 'SELECT product_id FROM product_allergens WHERE allergen_id = :allergen_id ORDER BY product_id';
        $params = ['allergen_id' => $allergenId];

        $stmt = $this->getDb()->prepare($sql);
        $this->execTimed(fn() => $stmt->execute($params), $sql, $params);

        return \array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'product_id');
    }

    #[Override]
    public function getStatistics(): array
    {
        $sql = 'SELECT a.id, a.name, COUNT(pa.product_id) as product_count
                FROM allergens a
                LEFT JOIN product_allergens pa ON a.id = pa.allergen_id
                GROUP BY a.id, a.name
                ORDER BY product_count DESC, a.name';

        /** @var PDOStatement $stmt */
        $stmt = $this->execTimed(fn() => $this->getDb()->query($sql), $sql);

        return \array_map([$this, 'normalizeRow'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    // ─────────────────────────────────────────────────────────────
    // Operaciones de escritura
    // ─────────────────────────────────────────────────────────────

    #[Override]
    public function create(array $data): int
    {
        $severity = $data['severity'] ?? 'medium';
        if (!\in_array($severity, self::VALID_SEVERITIES, true)) {
            throw new InvalidArgumentException("Severidad inválida: $severity.");
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
            $sql = 'INSERT INTO allergens (code, name, japanese_name, icon_class, icon_color, severity, description)
                    VALUES (:code, :name, :japanese_name, :icon_class, :icon_color, :severity, :description)';
            $params = [
                'code' => $code,
                'name' => $name,
                'japanese_name' => $data['name_jp'] ?? $data['japanese_name'] ?? null,
                'icon_class' => $data['icon'] ?? $data['icon_class'] ?? null,
                'icon_color' => $data['icon_color'] ?? null,
                'severity' => $severity,
                'description' => $data['description'] ?? null,
            ];

            $stmt = $this->getDb()->prepare($sql);
            $this->execTimed(fn() => $stmt->execute($params), $sql, $params);
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
        if (!\in_array($severity, self::VALID_SEVERITIES, true)) {
            throw new InvalidArgumentException("Severidad inválida: $severity.");
        }

        $name = \trim($data['name'] ?? '');
        if ($name === '') {
            throw new InvalidArgumentException('El nombre del alérgeno es obligatorio.');
        }

        $code = isset($data['code']) ? \strtoupper(\substr(\trim((string) $data['code']), 0, 10)) : '';
        if ($code === '') {
            $existing = $this->findById($id);
            $code = ($existing !== null && $existing->code !== '')
                ? $existing->code
                : AllergenCodeGenerator::fromName($name);
        }

        $sql = 'UPDATE allergens SET code = :code, name = :name, japanese_name = :japanese_name,
                icon_class = :icon_class, icon_color = :icon_color, severity = :severity,
                description = :description WHERE id = :id';
        $params = [
            'id' => $id,
            'code' => $code,
            'name' => $name,
            'japanese_name' => $data['name_jp'] ?? $data['japanese_name'] ?? null,
            'icon_class' => $data['icon'] ?? $data['icon_class'] ?? null,
            'icon_color' => $data['icon_color'] ?? null,
            'severity' => $severity,
            'description' => $data['description'] ?? null,
        ];

        $stmt = $this->getDb()->prepare($sql);

        return (bool) $this->execTimed(fn() => $stmt->execute($params), $sql, $params);
    }

    #[Override]
    public function attachToProduct(int $productId, int $allergenId, ?string $notes = null): bool
    {
        $sql = 'INSERT IGNORE INTO product_allergens (product_id, allergen_id, notes) VALUES (:product_id, :allergen_id, :notes)';
        $params = ['product_id' => $productId, 'allergen_id' => $allergenId, 'notes' => $notes];

        $stmt = $this->getDb()->prepare($sql);

        return (bool) $this->execTimed(fn() => $stmt->execute($params), $sql, $params);
    }

    #[Override]
    public function detachFromProduct(int $productId, int $allergenId): bool
    {
        $sql = 'DELETE FROM product_allergens WHERE product_id = :product_id AND allergen_id = :allergen_id';
        $params = ['product_id' => $productId, 'allergen_id' => $allergenId];

        $stmt = $this->getDb()->prepare($sql);

        return (bool) $this->execTimed(fn() => $stmt->execute($params), $sql, $params);
    }

    // ─────────────────────────────────────────────────────────────
    // Helpers privados
    // ─────────────────────────────────────────────────────────────

    /**
     * Normaliza alias legacy para compatibilidad con vistas existentes:
     * - japanese_name → name_jp
     * - icon_class → icon
     *
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
