<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Repositories\Contracts\CafeRepositoryInterface;
use PDO;

/**
 * Repositorio de Cafés.
 *
 * Encapsula la lógica de acceso a datos de cafés,
 * incluyendo búsquedas por categoría, ubicación y disponibilidad.
 */
final class CafeRepository extends AbstractRepository implements CafeRepositoryInterface
{
    #[\Override]
    protected function getTable(): string
    {
        return 'cafes';
    }

    #[\Override]
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

    /**
     * Buscar café por slug.
     */
    public function findBySlug(string $slug): ?array
    {
        $fields = implode(', ', $this->getSelectFields());

        $stmt = $this->db->prepare(
            "SELECT $fields FROM cafes WHERE slug = :slug AND deleted_at IS NULL LIMIT 1"
        );
        $stmt->execute(['slug' => $slug]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    /**
     * Buscar cafés activos.
     */
    public function findActive(): array
    {
        $fields = implode(', ', $this->getSelectFields());

        $stmt = $this->db->query(
            "SELECT {$fields}
             FROM cafes
             WHERE is_active = 1
             AND deleted_at IS NULL
             ORDER BY name "
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar cafés disponibles para reserva.
     */
    public function findAvailableForReservation(): array
    {
        $stmt = $this->db->query(
            'SELECT id, name, slug, location, category, animal_type, price_per_hour,
                    opening_time, closing_time, capacity_max, image_url,
                    latitude, longitude, timezone
             FROM cafes WHERE has_reservations = 1 AND is_active = 1'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar cafés disponibles para reserva, indexados por ID.
     *
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

    /**
     * Verificar que un café existe y está activo.
     */
    public function existsAndActive(int $cafeId): bool
    {
        $stmt = $this->db->prepare('SELECT id FROM cafes WHERE id = :id AND is_active = 1');
        $stmt->execute(['id' => $cafeId]);

        return $stmt->fetch() !== false;
    }

    /**
     * Buscar por categoría.
     */
    public function findByCategory(string $category): array
    {
        $fields = implode(', ', $this->getSelectFields());

        $stmt = $this->db->prepare(
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

    /**
     * Buscar por tipo de animal.
     */
    public function findByAnimalType(string $animalType): array
    {
        $fields = implode(', ', $this->getSelectFields());

        $stmt = $this->db->prepare(
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

    /**
     * Buscar cafés cercanos (por coordenadas).
     */
    public function findNearby(float $latitude, float $longitude, float $radiusKm = 10): array
    {
        $fields = implode(', ', $this->getSelectFields());

        // Fórmula Haversine para distancia
        $stmt = $this->db->prepare(
            "SELECT $fields,
             (6371 * acos(cos(radians(:lat)) * cos(radians(latitude))
             * cos(radians(longitude) - radians(:lng))
             + sin(radians(:lat)) * sin(radians(latitude)))) AS distance
             FROM cafes
             WHERE is_active = 1
             AND deleted_at IS NULL
             AND latitude IS NOT NULL
             AND longitude IS NOT NULL
             HAVING distance <= :radius
             ORDER BY distance "
        );
        $stmt->execute([
            'lat' => $latitude,
            'lng' => $longitude,
            'radius' => $radiusKm,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Actualizar rating promedio (llamado desde triggers o manualmente).
     */
    public function updateRating(int $id): bool
    {
        $stmt = $this->db->prepare(
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

    /**
     * Obtener cafés mejor valorados.
     */
    public function findTopRated(int $limit = 5): array
    {
        $fields = implode(', ', $this->getSelectFields());

        $stmt = $this->db->prepare(
            "SELECT {$fields}
             FROM cafes
             WHERE is_active = 1
             AND deleted_at IS NULL
             AND rating_count > 0
             ORDER BY rating_avg DESC, rating_count DESC
             LIMIT :limit"
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Verificar si un café está abierto en un momento específico.
     */
    public function isOpenAt(int $id, string $time): bool
    {
        $cafe = $this->findById($id);

        if (!$cafe || !$cafe['is_active']) {
            return false;
        }

        return $time >= $cafe['opening_time'] && $time <= $cafe['closing_time'];
    }

    /**
     * Obtener capacidad disponible en un momento dado.
     */
    public function getAvailableCapacity(int $id, string $date, string $time): int
    {
        $cafe = $this->findById($id);

        if (!$cafe) {
            return 0;
        }

        // Contar reservas activas en ese momento
        $stmt = $this->db->prepare(
            "SELECT COALESCE(SUM(guest_count), 0) as occupied
             FROM reservations
             WHERE cafe_id = :cafe_id
             AND reservation_date = :date
             AND reservation_time = :time
             AND status IN ('pending', 'confirmed', 'active')
             AND deleted_at IS NULL"
        );
        $stmt->execute([
            'cafe_id' => $id,
            'date' => $date,
            'time' => $time,
        ]);

        $occupied = (int) $stmt->fetchColumn();
        $maxCapacity = (int) $cafe['capacity_max'];

        return max(0, $maxCapacity - $occupied);
    }

    /**
     * Buscar cafés con filtros múltiples y paginación.
     *
     * @param array $filters Filtros: category, animal_type, is_active
     * @param int $limit Límite de resultados
     * @param int $offset Offset para paginación
     * @return array Lista de cafés
     */
    public function findFiltered(array $filters = [], int $limit = 100, int $offset = 0): array
    {
        $where = ['deleted_at IS NULL'];
        $params = [];

        if (isset($filters['category'])) {
            $where[] = 'category = :category';
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

        $whereClause = implode(' AND ', $where);
        $fields = implode(', ', $this->getSelectFields());

        $sql = "SELECT {$fields}
                FROM cafes
                WHERE {$whereClause}
                ORDER BY name
                LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue(":$key", $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Comprueba si un café tiene capacidad disponible
     */
    public function hasAvailableCapacity(int $cafeId, string $date, string $time): bool
    {
        // Obtener capacidad del café
        $cafeStmt = $this->db->prepare("SELECT capacity_max FROM cafes WHERE id = :id LIMIT 1");
        $cafeStmt->execute(['id' => $cafeId]);
        $cafe = $cafeStmt->fetch(PDO::FETCH_ASSOC);

        if (!$cafe || !isset($cafe['capacity_max'])) {
            return false;
        }

        $capacity = (int) $cafe['capacity_max'];

        // Count current bookings
        $bookingStmt = $this->db->prepare(
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
     * Update cafe fields
     *
     * @param int $id
     * @param array<string, mixed> $data
     * @return bool
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
            if (in_array($field, $allowedFields, true)) {
                $updates[] = "{$field} = :{$field}";
                $params[$field] = $value;
            }
        }

        if (empty($updates)) {
            return true;
        }

        $sql = "UPDATE cafes SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id = :id";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute($params);
    }
}
