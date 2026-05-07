<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Pagination;
use App\Domain\DTO\CafeDTO;
use App\Domain\Mappers\CafeMapper;
use App\Repositories\Contracts\CafeCatalogRepositoryInterface;
use Override;
use PDO;

final class CafeRepository extends AbstractRepository implements CafeCatalogRepositoryInterface
{
    private const string SQL_NOT_DELETED = 'deleted_at IS NULL';
    private const string SQL_FILTER_CATEGORY = 'category = :category';
    private const string SQL_AND = ' AND ';

    public function __construct(private readonly CafeMapper $mapper, ?PDO $db = null)
    {
        parent::__construct($db);
    }

    #[Override]
    protected function getTable(): string
    {
        return 'cafes';
    }

    #[Override]
    protected function getSelectFields(): array
    {
        return [
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
            'created_at',
            'updated_at',
        ];
    }

    #[Override]
    public function findById(int $id): ?CafeDTO
    {
        $row = $this->findByIdRaw($id);

        return $row ? $this->mapper->toDTO($row) : null;
    }

    public function findBySlug(string $slug): ?array
    {
        $fields = \implode(', ', $this->getSelectFields());

        $stmt = $this->getDb()->prepare(
            "SELECT $fields FROM cafes WHERE slug = :slug AND deleted_at IS NULL LIMIT 1"
        );
        $stmt->execute(['slug' => $slug]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    public function findActive(): array
    {
        $fields = \implode(', ', $this->getSelectFields());

        $stmt = $this->getDb()->query(
            "SELECT {$fields}
             FROM cafes
             WHERE is_active = 1
             AND deleted_at IS NULL
             ORDER BY name "
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findAvailableForReservation(): array
    {
        $stmt = $this->getDb()->query(
            'SELECT id, name, slug, location, category, animal_type, price_per_hour,
                    opening_time, closing_time, capacity_max, image_url,
                    latitude, longitude, timezone
             FROM cafes WHERE has_reservations = 1 AND is_active = 1'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findAvailableForReservationById(): array
    {
        $cafes = $this->findAvailableForReservation();
        $byId = [];

        foreach ($cafes as $cafe) {
            $byId[(int) $cafe['id']] = $cafe;
        }

        return $byId;
    }

    public function existsAndActive(int $cafeId): bool
    {
        $stmt = $this->getDb()->prepare('SELECT id FROM cafes WHERE id = :id AND is_active = 1');
        $stmt->execute(['id' => $cafeId]);

        return $stmt->fetch() !== false;
    }

    public function findByCategory(string $category): array
    {
        $fields = \implode(', ', $this->getSelectFields());

        $stmt = $this->getDb()->prepare(
            "SELECT {$fields}
             FROM cafes
             WHERE category = :category
             AND is_active = 1
             AND deleted_at IS NULL
             ORDER BY rating_avg DESC, name "
        );
        $stmt->execute(['category' => $category]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByAnimalType(string $animalType): array
    {
        $fields = \implode(', ', $this->getSelectFields());

        $stmt = $this->getDb()->prepare(
            "SELECT {$fields}
             FROM cafes
             WHERE animal_type = :animal_type
             AND is_active = 1
             AND deleted_at IS NULL
             ORDER BY rating_avg DESC, name "
        );
        $stmt->execute(['animal_type' => $animalType]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateRating(int $id): bool
    {
        $stmt = $this->getDb()->prepare(
            "UPDATE cafes c
             SET rating_avg = (
                 SELECT COALESCE(AVG(r.rating), 0)
                 FROM reviews r
                 WHERE r.cafe_id = c.id
                 AND r.status = 'approved'
             ),
             rating_count = (
                 SELECT COUNT(*)
                 FROM reviews r
                 WHERE r.cafe_id = c.id
                 AND r.status = 'approved'
             ),
             updated_at = NOW()
             WHERE c.id = :id"
        );

        return $stmt->execute(['id' => $id]);
    }

    public function findFiltered(array $filters = [], int $limit = 100, int $offset = 0): array
    {
        $where = [self::SQL_NOT_DELETED];
        $params = [];

        if (isset($filters['category'])) {
            $where[] = self::SQL_FILTER_CATEGORY;
            $params['category'] = $filters['category'];
        }

        if (isset($filters['animal_type'])) {
            $where[] = 'animal_type = :animal_type';
            $params['animal_type'] = $filters['animal_type'];
        }

        if (isset($filters['is_active'])) {
            $where[] = 'is_active = :is_active';
            $params['is_active'] = (int) $filters['is_active'];
        }

        $whereClause = \implode(self::SQL_AND, $where);
        $fields = \implode(', ', $this->getSelectFields());

        $sql = "SELECT {$fields}
                FROM cafes
                WHERE {$whereClause}
                ORDER BY name
                LIMIT :limit OFFSET :offset";

        $stmt = $this->getDb()->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue(":$key", $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function hasAvailableCapacity(int $cafeId, string $date, string $time): bool
    {
        $cafeStmt = $this->getDb()->prepare('SELECT capacity_max FROM cafes WHERE id = :id LIMIT 1');
        $cafeStmt->execute(['id' => $cafeId]);
        $cafe = $cafeStmt->fetch(PDO::FETCH_ASSOC);

        if (!$cafe || !isset($cafe['capacity_max'])) {
            return false;
        }

        $capacity = (int) $cafe['capacity_max'];

        $bookingStmt = $this->getDb()->prepare(
            "SELECT COALESCE(SUM(guest_count), 0) as booked
             FROM reservations
             WHERE cafe_id = :cafe_id
             AND reservation_date = :date
             AND reservation_time = :time
             AND status IN ('pending', 'confirmed', 'active')
             AND deleted_at IS NULL"
        );

        $bookingStmt->execute([
            'cafe_id' => $cafeId,
            'date' => $date,
            'time' => $time,
        ]);

        $result = $bookingStmt->fetch(PDO::FETCH_ASSOC);
        $booked = (int) ($result['booked'] ?? 0);

        return $booked < $capacity;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): bool
    {
        if (empty($data)) {
            return true;
        }

        $allowedFields = [
            'name',
            'japanese_name',
            'slug',
            'location',
            'category',
            'animal_type',
            'description',
            'price_per_hour',
            'opening_time',
            'closing_time',
            'capacity_max',
            'latitude',
            'longitude',
            'timezone',
            'is_active',
            'has_reservations',
            'image_url',
        ];

        $updates = [];
        $params = ['id' => $id];

        foreach ($data as $field => $value) {
            if (\in_array($field, $allowedFields, true)) {
                $updates[] = "$field = :$field";
                $params[$field] = $value;
            }
        }

        if (empty($updates)) {
            return true;
        }

        $sql = 'UPDATE cafes SET ' . \implode(', ', $updates) . ', updated_at = NOW() WHERE id = :id';

        return $this->getDb()->prepare($sql)->execute($params);
    }

    // ─────────────────────────────────────────────────────────────
    // Métodos adicionales (absorbidos de Cafe model)
    // ─────────────────────────────────────────────────────────────

    /**
     * Listado de cafés con filtros opcionales y orden configurable.
     * Equivalente al antiguo Cafe::findAll() con validación de whitelist.
     *
     * @param string|null $category Filtrar por categoría
     * @param string|null $animalType Filtrar por tipo de animal
     * @param string $orderBy Campo de orden (whitelist: name, rating_avg, price_per_hour, capacity_max)
     * @param string $order ASC | DESC
     * @return array<int, array<string, mixed>>
     */
    public function findAllFiltered(
        ?string $category = null,
        ?string $animalType = null,
        string $orderBy = 'name',
        string $order = 'ASC'
    ): array {
        $fields = \implode(', ', $this->getSelectFields());
        $where = ['is_active = 1', self::SQL_NOT_DELETED];
        $params = [];

        if ($category !== null) {
            $where[] = self::SQL_FILTER_CATEGORY;
            $params['category'] = $category;
        }

        if ($animalType !== null) {
            $where[] = 'animal_type = :animal_type';
            $params['animal_type'] = $animalType;
        }

        // Whitelist explícita para prevenir SQL injection en ORDER BY
        $validOrderBy = ['name', 'rating_avg', 'price_per_hour', 'capacity_max'];
        if (!\in_array($orderBy, $validOrderBy, true)) {
            $orderBy = 'name';
        }
        $order = \strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';

        $whereClause = \implode(self::SQL_AND, $where);
        $sql = "SELECT $fields FROM cafes WHERE $whereClause ORDER BY $orderBy $order";

        $stmt = $this->getDb()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene un café con sus animales activos embebidos.
     *
     * @return array<string, mixed>|null
     */
    public function findWithAnimals(string $slug): ?array
    {
        $cafe = $this->findBySlug($slug);

        if (!$cafe) {
            return null;
        }

        $stmt = $this->getDb()->prepare(
            "SELECT id, name, species_type, age, personality, description,
                    interaction_level, attributes, image_url, current_status
             FROM animals
             WHERE cafe_id = :cafe_id AND current_status IN ('active', 'resting')
             ORDER BY name"
        );
        $stmt->execute(['cafe_id' => $cafe['id']]);

        $cafe['animals'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $cafe;
    }

    /**
     * Obtiene las zonas de un café.
     *
     * @return array<int, array<string, mixed>>
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
     * Número de veces que un café ha sido marcado como favorito.
     */
    public function getFavoritesCount(int $cafeId): int
    {
        $stmt = $this->getDb()->prepare(
            'SELECT COUNT(*) FROM favorites WHERE cafe_id = :cafe_id'
        );
        $stmt->execute(['cafe_id' => $cafeId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Buscar cafés por múltiples IDs.
     *
     * @param array<int> $ids
     * @return array<int, array<string, mixed>>
     */
    public function findByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $ids = \array_map('intval', $ids);
        $placeholders = \implode(',', \array_fill(0, \count($ids), '?'));
        $fields = \implode(', ', $this->getSelectFields());

        $stmt = $this->getDb()->prepare(
            "SELECT $fields FROM cafes WHERE id IN ($placeholders) AND deleted_at IS NULL"
        );
        $stmt->execute($ids);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Búsqueda de texto libre en nombre, ubicación y descripción.
     *
     * @return array<int, array<string, mixed>>
     */
    public function search(string $query, int $limit = 10): array
    {
        $fields = \implode(', ', $this->getSelectFields());
        $q = '%' . \mb_substr(\trim($query), 0, 100) . '%';

        $sql = "SELECT $fields FROM cafes
                WHERE is_active = 1 AND deleted_at IS NULL
                  AND (name LIKE :q1 OR japanese_name LIKE :q2 OR location LIKE :q3 OR description LIKE :q4)
                ORDER BY rating_avg DESC
                LIMIT :limit";

        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue('q1', $q);
        $stmt->bindValue('q2', $q);
        $stmt->bindValue('q3', $q);
        $stmt->bindValue('q4', $q);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Paginación para el panel admin con filtros de búsqueda, categoría y estado.
     * Usa sentinel row (+1) para detectar has_next_page sin COUNT(*).
     *
     * @return array<int, array<string, mixed>>
     */
    public function findPaginatedAdmin(
        Pagination $pagination,
        string $search = '',
        string $category = '',
        string $status = '',
        string $sort = 'name',
        string $sortDir = 'asc',
    ): array {
        $sortWhitelist = ['id', 'name', 'category', 'animal_type', 'capacity_max', 'is_active', 'created_at'];
        if (!\in_array($sort, $sortWhitelist, true)) {
            $sort = 'name';
        }
        $sortDir = \strtolower($sortDir) === 'desc' ? 'DESC' : 'ASC';

        $fields = \implode(', ', $this->getSelectFields());
        $where = [self::SQL_NOT_DELETED];
        $params = [];

        if ($search !== '') {
            $like = '%' . \mb_substr(\trim($search), 0, 100) . '%';
            $where[] = '(name LIKE :s1 OR japanese_name LIKE :s2 OR location LIKE :s3)';
            $params['s1'] = $like;
            $params['s2'] = $like;
            $params['s3'] = $like;
        }

        if ($category !== '') {
            $where[] = self::SQL_FILTER_CATEGORY;
            $params['category'] = $category;
        }

        if ($status === 'active') {
            $where[] = 'is_active = 1';
        } elseif ($status === 'inactive') {
            $where[] = 'is_active = 0';
        }

        $whereClause = \implode(self::SQL_AND, $where);
        $sql = "SELECT {$fields}
                        FROM cafes
                        WHERE {$whereClause}
                        ORDER BY {$sort} {$sortDir}
                        LIMIT :limit OFFSET :offset";

        $stmt = $this->getDb()->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(":$key", $value);
        }
        $stmt->bindValue(':limit', $pagination->fetchLimit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $pagination->offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Estadísticas para el panel admin de cafés.
     *
     * @return array{total_cafes: int, active_cafes: int, cafes_with_reservations: int, avg_rating: float}
     */
    public function getAdminStats(): array
    {
        $row = $this->getDb()->query(
            'SELECT
                COUNT(*)                                        AS total_cafes,
                SUM(IF(is_active = 1, 1, 0))                   AS active_cafes,
                SUM(IF(has_reservations = 1, 1, 0))             AS cafes_with_reservations,
                ROUND(AVG(NULLIF(rating_avg, 0)), 1)            AS avg_rating
             FROM cafes
             WHERE deleted_at IS NULL'
        )->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : ['total_cafes' => 0, 'active_cafes' => 0, 'cafes_with_reservations' => 0, 'avg_rating' => 0.0];
    }
}
