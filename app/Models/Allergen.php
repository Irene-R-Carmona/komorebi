<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use InvalidArgumentException;
use PDO;

/**
 * Modelo Allergen
 *
 * Gestiona los alérgenos del sistema.
 */
final class Allergen
{
    private PDO $db;

    // Niveles de severidad
    public const string SEVERITY_LOW = 'low';
    public const string SEVERITY_MEDIUM = 'medium';
    public const string SEVERITY_HIGH = 'high';

    public const array VALID_SEVERITIES = [
        self::SEVERITY_LOW,
        self::SEVERITY_MEDIUM,
        self::SEVERITY_HIGH,
    ];

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    // ─────────────────────────────────────────────────────────────
    // Consultas básicas
    // ─────────────────────────────────────────────────────────────

    /**
     * Obtiene todos los alérgenos.
     *
     * @return array<int,array>
     */
    public function getAll(bool $orderBySeverity = true): array
    {
        $sql = 'SELECT * FROM allergens';

        if ($orderBySeverity) {
            $sql .= ' ORDER BY
                CASE severity
                    WHEN \'high\' THEN 1
                    WHEN \'medium\' THEN 2
                    WHEN \'low\' THEN 3
                END,
                name ASC';
        } else {
            $sql .= ' ORDER BY name ASC';
        }

        $rows = $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        return \array_map([$this, 'normalizeRow'], $rows);
    }

    /**
     * Obtiene un alérgeno por ID.
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM allergens WHERE id = :id LIMIT 1');
        $stmt->bindValue('id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->normalizeRow($row) : null;
    }

    /**
     * Obtiene un alérgeno por nombre.
     */
    public function findByName(string $name): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM allergens WHERE name = :name LIMIT 1');
        $stmt->bindValue('name', $name, PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->normalizeRow($row) : null;
    }

    /**
     * Obtiene alérgenos por nivel de severidad.
     *
     * @return array<int,array>
     */
    public function getBySeverity(string $severity): array
    {
        if (!\in_array($severity, self::VALID_SEVERITIES, true)) {
            throw new InvalidArgumentException("Severidad inválida: $severity");
        }

        $stmt = $this->db->prepare('SELECT * FROM allergens WHERE severity = :severity ORDER BY name');
        $stmt->bindValue('severity', $severity, PDO::PARAM_STR);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return \array_map([$this, 'normalizeRow'], $rows);
    }

    /**
     * Obtiene alérgenos de un producto específico.
     *
     * @param integer $productId
     * @return array<int,array>
     */
    public function getByProduct(int $productId): array
    {
        $stmt = $this->db->prepare('SELECT a.*
            FROM allergens a
            JOIN product_allergens pa ON a.id = pa.allergen_id
            WHERE pa.product_id = :product_id
            ORDER BY
                CASE a.severity
                    WHEN \'high\' THEN 1
                    WHEN \'medium\' THEN 2
                    WHEN \'low\' THEN 3
                END,
                a.name
        ');
        $stmt->bindValue('product_id', $productId, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return \array_map([$this, 'normalizeRow'], $rows);
    }

    /**
     * Obtiene productos que contienen un alérgeno específico.
     *
     * @param integer $allergenId
     * @return array<int> IDs de productos
     */
    public function getProductIds(int $allergenId): array
    {
        $stmt = $this->db->prepare('SELECT product_id FROM product_allergens WHERE allergen_id = :allergen_id ORDER BY product_id');
        $stmt->bindValue('allergen_id', $allergenId, PDO::PARAM_INT);
        $stmt->execute();

        return \array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'product_id');
    }

    /**
     * Obtiene estadísticas de uso de alérgenos.
     */
    public function getStatistics(): array
    {
        $sql = 'SELECT a.id, a.name, COUNT(pa.product_id) as product_count FROM allergens a LEFT JOIN product_allergens pa ON a.id = pa.allergen_id GROUP BY a.id, a.name ORDER BY product_count DESC, a.name';
        $stmt = $this->db->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return \array_map([$this, 'normalizeRow'], $rows);
    }

    // ─────────────────────────────────────────────────────────────
    // Operaciones de escritura (solo admin)
    // ─────────────────────────────────────────────────────────────

    /**
     * Crea un nuevo alérgeno.
     * @throws \Throwable
     */
    public function create(array $data): int
    {
        $severity = $data['severity'] ?? self::SEVERITY_MEDIUM;
        if (!\in_array($severity, self::VALID_SEVERITIES, true)) {
            throw new InvalidArgumentException("Severidad inválida: $severity");
        }

        // Normalizar y validar code: obligatorio según migración (VARCHAR(10) NOT NULL UNIQUE)
        $code = isset($data['code']) ? (string) $data['code'] : '';
        $code = \strtoupper(\substr(\trim($code), 0, 10));
        if ($code === '') {
            // Generar a partir del nombre si no se proporciona
            if (empty($data['name'])) {
                throw new InvalidArgumentException('El código o el nombre son obligatorios para crear un alérgeno.');
            }
            $code = $this->generateCodeFromName($data['name']);
        }

        $name = \trim($data['name'] ?? '');
        if ($name === '') {
            throw new InvalidArgumentException('El nombre del alérgeno es obligatorio.');
        }

        $japaneseName = $data['name_jp'] ?? $data['japanese_name'] ?? null;
        $iconClass = $data['icon'] ?? $data['icon_class'] ?? null;
        $iconColor = $data['icon_color'] ?? null;
        $description = $data['description'] ?? null;

        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare('INSERT INTO allergens (code, name, japanese_name, icon_class, icon_color, severity, description) VALUES (:code, :name, :japanese_name, :icon_class, :icon_color, :severity, :description)');
            $stmt->execute([
                'code' => $code,
                'name' => $name,
                'japanese_name' => $japaneseName,
                'icon_class' => $iconClass,
                'icon_color' => $iconColor,
                'severity' => $severity,
                'description' => $description,
            ]);

            $id = (int) $this->db->lastInsertId();
            $this->db->commit();

            return $id;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Actualiza un alérgeno.
     */
    public function update(int $id, array $data): bool
    {
        $severity = $data['severity'] ?? self::SEVERITY_MEDIUM;
        if (!\in_array($severity, self::VALID_SEVERITIES, true)) {
            throw new InvalidArgumentException("Severidad inválida: $severity");
        }

        $name = \trim($data['name'] ?? '');
        if ($name === '') {
            throw new InvalidArgumentException('El nombre del alérgeno es obligatorio.');
        }

        // Normalizar code si viene, o generar si no existe en payload pero es necesario
        $code = isset($data['code']) ? \strtoupper(\substr(\trim((string) $data['code']), 0, 10)) : null;
        if ($code === null || $code === '') {
            // intentar conservar el code existente; si no existe, generarlo
            $existing = $this->findById($id);
            if ($existing && !empty($existing['code'])) {
                $code = $existing['code'];
            } else {
                $code = $this->generateCodeFromName($name);
            }
        }

        $japaneseName = $data['name_jp'] ?? $data['japanese_name'] ?? null;
        $iconClass = $data['icon'] ?? $data['icon_class'] ?? null;
        $iconColor = $data['icon_color'] ?? null;
        $description = $data['description'] ?? null;

        $stmt = $this->db->prepare('UPDATE allergens SET code = :code, name = :name, japanese_name = :japanese_name, icon_class = :icon_class, icon_color = :icon_color, severity = :severity, description = :description WHERE id = :id');

        return $stmt->execute([
            'id' => $id,
            'code' => $code,
            'name' => $name,
            'japanese_name' => $japaneseName,
            'icon_class' => $iconClass,
            'icon_color' => $iconColor,
            'severity' => $severity,
            'description' => $description,
        ]);
    }

    /**
     * Asocia un alérgeno a un producto (crea entrada en product_allergens si no existe).
     */
    public function attachProduct(int $productId, int $allergenId, ?string $notes = null): bool
    {
        $stmt = $this->db->prepare('INSERT IGNORE INTO product_allergens (product_id, allergen_id, notes) VALUES (:product_id, :allergen_id, :notes)');

        return $stmt->execute(['product_id' => $productId, 'allergen_id' => $allergenId, 'notes' => $notes]);
    }

    /**
     * Desvincula un alérgeno de un producto.
     */
    public function detachProduct(int $productId, int $allergenId): bool
    {
        $stmt = $this->db->prepare('DELETE FROM product_allergens WHERE product_id = :product_id AND allergen_id = :allergen_id');

        return $stmt->execute(['product_id' => $productId, 'allergen_id' => $allergenId]);
    }

    /**
     * Genera un código corto (máx 10) a partir del nombre (ASCII, mayúsculas).
     */
    private function generateCodeFromName(string $name): string
    {
        // Normalizar: eliminar acentos, caracteres no alfanuméricos y tomar primeras letras
        $normalized = \preg_replace('/[^A-Za-z0-9]/', '', \iconv('UTF-8', 'ASCII//TRANSLIT', $name));
        $normalized = \strtoupper($normalized);
        if ($normalized === '') {
            // Fallback seguro
            return \substr('ALLERGEN', 0, 10);
        }

        return \substr($normalized, 0, 10);
    }

    /**
     * Normaliza una fila de alérgeno para mantener compatibilidad con claves legacy
     * (por ejemplo `name_jp` o `icon`). Añade keys si faltan.
     */
    private function normalizeRow(array $row): array
    {
        // japanese_name -> name_jp (legacy)
        if (isset($row['japanese_name']) && !isset($row['name_jp'])) {
            $row['name_jp'] = $row['japanese_name'];
        }

        // icon_class -> icon (legacy)
        if (isset($row['icon_class']) && !isset($row['icon'])) {
            $row['icon'] = $row['icon_class'];
        }

        // keep icon_color, code, description as-is (they exist in migration)
        return $row;
    }
}
