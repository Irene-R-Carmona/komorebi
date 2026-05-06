<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Domain\DTO\ReviewDTO;
use App\Domain\Mappers\ReviewMapper;
use App\Repositories\Contracts\ReviewRepositoryInterface;
use Override;
use PDO;

/**
 * Repositorio de Reseñas
 *
 * Encapsula el acceso a datos de reseñas de cafés.
 */
final class ReviewRepository extends AbstractRepository implements ReviewRepositoryInterface
{
    private ReviewMapper $mapper;

    public function __construct(?PDO $db = null)
    {
        parent::__construct($db);
        $this->mapper = new ReviewMapper();
    }

    #[Override]
    protected function getTable(): string
    {
        return 'reviews';
    }

    #[Override]
    protected function getSelectFields(): array
    {
        return ['id', 'user_id', 'cafe_id', 'reservation_id', 'rating', 'title', 'body', 'status', 'rejection_reason', 'created_at', 'updated_at'];
    }

    #[Override]
    public function findById(int $id): ?ReviewDTO
    {
        $sql = '
            SELECT r.*, u.name as user_name, c.name as cafe_name
            FROM reviews r
            INNER JOIN users u ON r.user_id = u.id
            INNER JOIN cafes c ON r.cafe_id = c.id
            WHERE r.id = :id
            LIMIT 1
        ';

        $stmt = $this->getDb()->prepare($sql);
        $stmt->execute(['id' => $id]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result !== false ? $this->mapper->toDTO($result) : null;
    }

    /**
     * Obtener reseñas de un usuario
     */
    public function findByUserId(int $userId): array
    {
        $sql = '
            SELECT r.*, c.name as cafe_name
            FROM reviews r
            INNER JOIN cafes c ON r.cafe_id = c.id
            WHERE r.user_id = :user_id
            ORDER BY r.created_at DESC
        ';

        $stmt = $this->getDb()->prepare($sql);
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Obtener reseñas de un café
     */
    public function findByCafeId(int $cafeId, string $status = 'approved'): array
    {
        $sql = '
            SELECT r.*, u.name as user_name
            FROM reviews r
            INNER JOIN users u ON r.user_id = u.id
            WHERE r.cafe_id = :cafe_id
              AND r.status = :status
            ORDER BY r.created_at DESC
        ';

        $stmt = $this->getDb()->prepare($sql);
        $stmt->execute(['cafe_id' => $cafeId, 'status' => $status]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Obtener reseñas aprobadas con paginación
     */
    public function findApprovedPaginated(int $cafeId, int $page = 1, int $perPage = 10): array
    {
        $offset = ($page - 1) * $perPage;

        $countSql = "
            SELECT COUNT(*) as total
            FROM reviews
            WHERE cafe_id = :cafe_id
              AND status = 'approved'
        ";
        $countStmt = $this->getDb()->prepare($countSql);
        $countStmt->execute(['cafe_id' => $cafeId]);
        $total = (int) ($countStmt->fetchColumn() ?: 0);

        $sql = "
            SELECT r.*, u.name as user_name
            FROM reviews r
            INNER JOIN users u ON r.user_id = u.id
            WHERE r.cafe_id = :cafe_id
              AND r.status = 'approved'
            ORDER BY r.created_at DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':cafe_id', $cafeId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $data = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $pages = $perPage > 0 ? (int) \ceil($total / $perPage) : 1;

        return ['data' => $data, 'total' => $total, 'pages' => $pages];
    }

    /**
     * Obtener reseñas pendientes de moderación
     */
    public function findPendingPaginated(int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;

        $sql = "
            SELECT r.*, u.name as user_name, c.name as cafe_name
            FROM reviews r
            INNER JOIN users u ON r.user_id = u.id
            INNER JOIN cafes c ON r.cafe_id = c.id
            WHERE r.status = 'pending'
            ORDER BY r.created_at ASC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Obtener reseñas de un café (todos los estados) con paginación sentinel
     */
    public function findAllStatusesPaginated(int $cafeId, ?string $status = null, int $page = 1): array
    {
        $page = \max(1, $page);
        $perPage = 20;
        $fetchLimit = $perPage + 1;
        $offset = ($page - 1) * $perPage;

        $where = ['r.cafe_id = :cafe_id'];
        $params = ['cafe_id' => $cafeId];

        if ($status !== null && $status !== '') {
            $where[] = 'r.status = :status';
            $params['status'] = $status;
        }

        $whereClause = \implode(' AND ', $where);

        $stmt = $this->getDb()->prepare("
            SELECT r.*, u.name AS user_name
            FROM reviews r
            INNER JOIN users u ON r.user_id = u.id
            WHERE {$whereClause}
            ORDER BY r.created_at DESC
            LIMIT {$fetchLimit} OFFSET {$offset}
        ");
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    #[Override]
    public function create(array $data): int
    {
        $sql = '
            INSERT INTO reviews (user_id, cafe_id, rating, title, body, status, rejection_reason)
            VALUES (:user_id, :cafe_id, :rating, :title, :body, :status, :rejection_reason)
        ';

        $stmt = $this->getDb()->prepare($sql);
        $stmt->execute([
            'user_id' => $data['user_id'],
            'cafe_id' => $data['cafe_id'],
            'rating' => $data['rating'],
            'title' => $data['title'],
            'body' => $data['body'],
            'status' => $data['status'] ?? 'pending',
            'rejection_reason' => $data['rejection_reason'] ?? null,
        ]);

        return (int) $this->getDb()->lastInsertId();
    }

    #[Override]
    public function update(int $id, array $data): bool
    {
        $allowedFields = ['rating', 'title', 'body', 'status', 'rejection_reason'];
        $updates = [];
        $params = ['id' => $id];

        foreach ($data as $field => $value) {
            if (\in_array($field, $allowedFields, true)) {
                $updates[] = "{$field} = :{$field}";
                $params[$field] = $value;
            }
        }

        if (empty($updates)) {
            return true;
        }

        $sql = 'UPDATE reviews SET ' . \implode(', ', $updates) . ', updated_at = NOW() WHERE id = :id';

        $stmt = $this->getDb()->prepare($sql);

        return $stmt->execute($params);
    }

    /**
     * Actualizar estado de reseña
     */
    public function updateStatus(int $id, string $status): bool
    {
        $sql = 'UPDATE reviews SET status = :status, updated_at = NOW() WHERE id = :id';

        $stmt = $this->getDb()->prepare($sql);

        return $stmt->execute(['id' => $id, 'status' => $status]);
    }

    /**
     * Calcular rating promedio de un café
     */
    public function calculateAverageRating(int $cafeId): float
    {
        $sql = "
            SELECT AVG(rating) as avg_rating
            FROM reviews
            WHERE cafe_id = :cafe_id
              AND status = 'approved'
        ";

        $stmt = $this->getDb()->prepare($sql);
        $stmt->execute(['cafe_id' => $cafeId]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result && $result['avg_rating'] !== null ? (float) $result['avg_rating'] : 0.0;
    }

    /**
     * Verificar si usuario ya tiene reseña en un café
     */
    public function userHasReview(int $userId, int $cafeId): bool
    {
        $sql = '
            SELECT 1
            FROM reviews
            WHERE user_id = :user_id
              AND cafe_id = :cafe_id
            LIMIT 1
        ';

        $stmt = $this->getDb()->prepare($sql);
        $stmt->execute(['user_id' => $userId, 'cafe_id' => $cafeId]);

        return (bool) $stmt->fetch();
    }

    /**
     * Obtener estadísticas de rating de un café
     */
    public function getRatingStats(int $cafeId): array
    {
        $sql = "
            SELECT
                COUNT(*) as total_reviews,
                AVG(rating) as avg_rating,
                MIN(rating) as min_rating,
                MAX(rating) as max_rating,
                SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as five_stars,
                SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as four_stars,
                SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as three_stars,
                SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as two_stars,
                SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as one_star
            FROM reviews
            WHERE cafe_id = :cafe_id
              AND status = 'approved'
        ";

        $stmt = $this->getDb()->prepare($sql);
        $stmt->execute(['cafe_id' => $cafeId]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: [
            'total_reviews' => 0,
            'avg_rating' => 0.0,
            'min_rating' => 0,
            'max_rating' => 0,
            'five_stars' => 0,
            'four_stars' => 0,
            'three_stars' => 0,
            'two_stars' => 0,
            'one_star' => 0,
        ];
    }
}
