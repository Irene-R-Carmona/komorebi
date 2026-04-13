<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Models\Traits\ValidatesData;
use PDO;
use RuntimeException;

/**
 * Modelo Cafe
 *
 * Gestiona los cafés de animales de la franquicia.
 */
final class Cafe
{
    use ValidatesData;

    private ?PDO $db = null;

    // ─────────────────────────────────────────────────────────────
    // Constantes
    // ─────────────────────────────────────────────────────────────

    /** Categorías de café */
    public const string CATEGORY_LOUNGE = 'lounge';
    public const string CATEGORY_PLAYROOM = 'playroom';
    public const string CATEGORY_FARM = 'farm';
    public const string CATEGORY_ZEN = 'zen';

    public const array VALID_CATEGORIES = [
        self::CATEGORY_LOUNGE,
        self::CATEGORY_PLAYROOM,
        self::CATEGORY_FARM,
        self::CATEGORY_ZEN,
    ];

    /** Campos para SELECT público */
    private const array SELECT_FIELDS = [
        'id',
        'name',
        'japanese_name',
        'slug',
        'location',
        'category',
        'animal_type',
        'description',
        'price_per_hour',
        'rating_avg',
        'rating_count',
        'opening_time',
        'closing_time',
        'capacity_max',
        'latitude',
        'longitude',
        'timezone',
        'is_active',
        'has_reservations',
        'image_url',
        'deleted_at',
    ];

    // ─────────────────────────────────────────────────────────────
    // Constructor
    // ─────────────────────────────────────────────────────────────

    public function __construct(?PDO $db = null)
    {
        $this->db = $db;
    }

    private function getDb(): PDO
    {
        return $this->db ??= Database::getConnection();
    }

    // ─────────────────────────────────────────────────────────────
    // Búsqueda
    // ─────────────────────────────────────────────────────────────

    /**
     * Busca un café por ID.
     *
     * @return array<string,mixed>|null
     */
    public function findById(int $id): ?array
    {
        $fields = \implode(', ', self::SELECT_FIELDS);

        $stmt = $this->getDb()->prepare(
            "SELECT $fields FROM cafes WHERE id = :id LIMIT 1"
        );
        $stmt->execute(['id' => $id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : (array) $row;
    }

    /**
     * Busca un café por slug (para URLs amigables).
     *
     * @return array<string,mixed>|null
     */
    public function findBySlug(string $slug): ?array
    {
        $slug = $this->sanitizeSlug($slug);
        $fields = \implode(', ', self::SELECT_FIELDS);

        $stmt = $this->getDb()->prepare(
            "SELECT $fields FROM cafes WHERE slug = :slug AND is_active = 1 LIMIT 1"
        );
        $stmt->execute(['slug' => $slug]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : (array) $row;
    }

    /**
     * Busca cafés por múltiples IDs (para recently viewed)
     * Mantiene el orden de los IDs proporcionados
     *
     * @param array<int> $ids Array de IDs de cafés
     * @return array<int, array<string,mixed>> Array de cafés encontrados
     */
    public function findByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        // Validar que todos sean enteros
        $ids = \array_map('intval', $ids);
        $placeholders = \implode(',', \array_fill(0, \count($ids), '?'));
        $fields = \implode(', ', self::SELECT_FIELDS);

        $stmt = $this->getDb()->prepare(
            "SELECT $fields FROM cafes WHERE id IN ($placeholders) AND is_active = 1"
        );
        $stmt->execute($ids);

        $cafes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Mantener el orden original de los IDs
        $cafesById = [];
        foreach ($cafes as $cafe) {
            $cafesById[(int) $cafe['id']] = $cafe;
        }

        $result = [];
        foreach ($ids as $id) {
            if (isset($cafesById[$id])) {
                $result[] = $cafesById[$id];
            }
        }

        return $result;
    }

    /**
     * Verifica si un slug ya existe.
     */
    public function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $sql = 'SELECT 1 FROM cafes WHERE slug = :slug';
        $params = ['slug' => $this->sanitizeSlug($slug)];

        if ($excludeId !== null) {
            $sql .= ' AND id != :exclude_id';
            $params['exclude_id'] = $excludeId;
        }

        $stmt = $this->getDb()->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);

        return (bool) $stmt->fetch();
    }

    // ─────────────────────────────────────────────────────────────
    // Listados
    // ─────────────────────────────────────────────────────────────

    /**
     * Obtiene todos los cafés activos.
     *
     * @param string|null $category   Filtrar por categoría
     * @param string|null $animalType Filtrar por tipo de animal
     * @param string      $orderBy    Campo de ordenación
     * @param string      $order      ASC o DESC
     */
    /**
     * @return array<int, array<string,mixed>>
     */
    public function findAll(
        ?string $category = null,
        ?string $animalType = null,
        string $orderBy = 'name',
        string $order = 'ASC'
    ): array {
        $fields = \implode(', ', self::SELECT_FIELDS);
        $where = ['is_active = 1'];
        $params = [];

        if ($category !== null) {
            $this->validateInArray($category, self::VALID_CATEGORIES, 'category');
            $where[] = 'category = :category';
            $params['category'] = $category;
        }

        if ($animalType !== null) {
            $where[] = 'animal_type = :animal_type';
            $params['animal_type'] = $animalType;
        }

        // Validar ordenación (prevenir SQL injection)
        $validOrderBy = ['name', 'rating_avg', 'price_per_hour', 'capacity_max'];
        if (!\in_array($orderBy, $validOrderBy, true)) {
            $orderBy = 'name';
        }
        $order = \strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';

        $whereClause = \implode(' AND ', $where);
        $sql = "SELECT $fields FROM cafes WHERE $whereClause ORDER BY $orderBy $order";

        $stmt = $this->getDb()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene cafés con disponibilidad para reservas.
     */
    public function findWithReservations(): array
    {
        $fields = \implode(', ', self::SELECT_FIELDS);

        $stmt = $this->getDb()->query(
            "SELECT $fields FROM cafes
             WHERE is_active = 1 AND has_reservations = 1
             ORDER BY name "
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene cafés por tipo de animal.
     */
    public function findByAnimalType(string $animalType): array
    {
        return $this->findAll(animalType: $animalType);
    }

    /**
     * Búsqueda por texto (nombre, ubicación, descripción).
     */
    public function search(string $query, int $limit = 10): array
    {
        $fields = \implode(', ', self::SELECT_FIELDS);
        $query = '%' . $this->sanitizeString($query, 100) . '%';

        $sql = "SELECT $fields FROM cafes
                WHERE is_active = 1
                  AND (name LIKE :q OR japanese_name LIKE :q OR location LIKE :q OR description LIKE :q)
                ORDER BY rating DESC
                LIMIT :limit";

        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue('q', $query);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─────────────────────────────────────────────────────────────
    // Datos relacionados
    // ─────────────────────────────────────────────────────────────

    /**
     * Obtiene un café con sus animales activos.
     */
    public function findWithAnimals(string $slug): ?array
    {
        $cafe = $this->findBySlug($slug);

        if (!$cafe) {
            return null;
        }

        $stmt = $this->getDb()->prepare(
            "SELECT id, name, species_type, age, personality, description,
                    interaction_level, image_url, current_status
             FROM animals
             WHERE cafe_id = :cafe_id AND current_status IN ('active', 'resting')
             ORDER BY name "
        );
        $stmt->execute(['cafe_id' => $cafe['id']]);

        $cafe['animals'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $cafe;
    }

    /**
     * Obtiene las zonas de un café.
     */
    public function getZones(int $cafeId): array
    {
        $stmt = $this->getDb()->prepare(
            'SELECT id, name, type, status, capacity, requires_briefing, requires_shoes_off
             FROM cafe_zones
             WHERE cafe_id = :cafe_id
             ORDER BY type, name'
        );
        $stmt->execute(['cafe_id' => $cafeId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene los trackers de un café.
     */
    public function getTrackers(int $cafeId, ?string $status = null): array
    {
        $sql = 'SELECT id, code, type, status FROM trackers WHERE cafe_id = :cafe_id';
        $params = ['cafe_id' => $cafeId];

        if ($status !== null) {
            $sql .= ' AND status = :status';
            $params['status'] = $status;
        }

        $sql .= ' ORDER BY code ASC';

        $stmt = $this->getDb()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene trackers disponibles de un café.
     */
    public function getAvailableTrackers(int $cafeId): array
    {
        return $this->getTrackers($cafeId, 'available');
    }

    // ─────────────────────────────────────────────────────────────
    // Estadísticas
    // ─────────────────────────────────────────────────────────────

    /**
     * Obtiene estadísticas básicas de un café.
     */
    public function getStats(int $cafeId): array
    {
        // Conteo de animales por estado
        $stmt = $this->getDb()->prepare(
            'SELECT current_status, COUNT(*) as count
             FROM animals WHERE cafe_id = :cafe_id
             GROUP BY current_status'
        );
        $stmt->execute(['cafe_id' => $cafeId]);

        $animalStats = [];
        while ($row = $stmt->fetch()) {
            $animalStats[$row['current_status']] = (int) $row['count'];
        }

        // Reservas de hoy
        $stmt = $this->getDb()->prepare(
            "SELECT
                COUNT(*) as total_today,
                SUM(IF(status = 'active', guests, 0)) as current_guests
             FROM reservations
             WHERE cafe_id = :cafe_id AND reservation_date = CURDATE()"
        );
        $stmt->execute(['cafe_id' => $cafeId]);
        $reservationStats = $stmt->fetch();

        // Staff asignado
        $stmt = $this->getDb()->prepare(
            'SELECT COUNT(*) FROM users WHERE cafe_id = :cafe_id AND is_active = 1'
        );
        $stmt->execute(['cafe_id' => $cafeId]);
        $staffCount = (int) $stmt->fetchColumn();

        return [
            'animals' => $animalStats,
            'total_animals' => \array_sum($animalStats),
            'reservations_today' => (int) ($reservationStats['total_today'] ?? 0),
            'current_guests' => (int) ($reservationStats['current_guests'] ?? 0),
            'staff_count' => $staffCount,
        ];
    }

    /**
     * Obtiene el número de favoritos de un café.
     */
    public function getFavoritesCount(int $cafeId): int
    {
        $stmt = $this->getDb()->prepare(
            'SELECT COUNT(*) FROM favorites WHERE cafe_id = :cafe_id'
        );
        $stmt->execute(['cafe_id' => $cafeId]);

        return (int) $stmt->fetchColumn();
    }

    // ─────────────────────────────────────────────────────────────
    // Administración (CRUD completo)
    // ─────────────────────────────────────────────────────────────

    /**
     * Crea un nuevo café.
     *
     * @return integer ID del café creado
     */
    public function create(array $data): int
    {
        $this->validateRequired($data, [
            'name',
            'slug',
            'location',
            'category',
            'animal_type',
            'description',
            'price_per_hour',
            'opening_time',
            'closing_time',
            'capacity_max',
        ]);

        $slug = $this->sanitizeSlug($data['slug']);

        if ($this->slugExists($slug)) {
            throw new RuntimeException('El slug ya está en uso.');
        }

        $this->validateInArray($data['category'], self::VALID_CATEGORIES, 'category');

        $sql = 'INSERT INTO cafes (
                    name, japanese_name, slug, location, category, animal_type,
                    description, price_per_hour, opening_time, closing_time,
                    capacity_max, image_url
                ) VALUES (
                    :name, :japanese_name, :slug, :location, :category, :animal_type,
                    :description, :price_per_hour, :opening_time, :closing_time,
                    :capacity_max, :image_url
                )';

        $this->getDb()->prepare($sql)->execute([
            'name' => $this->sanitizeString($data['name'], 100),
            'japanese_name' => isset($data['japanese_name']) ? $this->sanitizeString($data['japanese_name'], 100) : null,
            'slug' => $slug,
            'location' => $this->sanitizeString($data['location'], 255),
            'category' => $data['category'],
            'animal_type' => $this->sanitizeString($data['animal_type'], 50),
            'description' => $data['description'],
            'price_per_hour' => $this->validatePositiveInt($data['price_per_hour'], 'price_per_hour'),
            'opening_time' => $data['opening_time'],
            'closing_time' => $data['closing_time'],
            'capacity_max' => $this->validatePositiveInt($data['capacity_max'], 'capacity_max'),
            'image_url' => $data['image_url'] ?? null,
        ]);

        return (int) $this->getDb()->lastInsertId();
    }

    /**
     * Actualiza un café.
     */
    public function update(int $id, array $data): bool
    {
        $sets = [];
        $params = ['id' => $id];

        $allowedFields = [
            'name',
            'japanese_name',
            'location',
            'description',
            'price_per_hour',
            'opening_time',
            'closing_time',
            'capacity_max',
            'image_url',
        ];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $sets[] = "$field = :$field";
                $params[$field] = $data[$field];
            }
        }

        // Slug requiere validación especial
        if (isset($data['slug'])) {
            $slug = $this->sanitizeSlug($data['slug']);
            if ($this->slugExists($slug, $id)) {
                throw new RuntimeException('El slug ya está en uso.');
            }
            $sets[] = 'slug = :slug';
            $params['slug'] = $slug;
        }

        // Categoría requiere validación
        if (isset($data['category'])) {
            $this->validateInArray($data['category'], self::VALID_CATEGORIES, 'category');
            $sets[] = 'category = :category';
            $params['category'] = $data['category'];
        }

        if (empty($sets)) {
            return false;
        }

        $sql = 'UPDATE cafes SET ' . \implode(', ', $sets) . ' WHERE id = :id';

        return $this->getDb()->prepare($sql)->execute($params);
    }

    /**
     * Activa/desactiva un café.
     */
    public function toggleActive(int $id): bool
    {
        $stmt = $this->getDb()->prepare(
            'UPDATE cafes SET is_active = NOT is_active WHERE id = :id'
        );

        return $stmt->execute(['id' => $id]);
    }

    /**
     * Activa/desactiva reservas de un café.
     */
    public function toggleReservations(int $id): bool
    {
        $stmt = $this->getDb()->prepare(
            'UPDATE cafes SET has_reservations = NOT has_reservations WHERE id = :id'
        );

        return $stmt->execute(['id' => $id]);
    }

    /**
     * Actualiza el rating de un café.
     */
    public function updateRating(int $id, float $rating): bool
    {
        $rating = \max(0, \min(5, $rating)); // Limitar entre 0 y 5

        $stmt = $this->getDb()->prepare(
            'UPDATE cafes SET rating_avg = :rating WHERE id = :id'
        );

        return $stmt->execute(['id' => $id, 'rating' => \round($rating, 1)]);
    }
}
