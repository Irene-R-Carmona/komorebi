<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;
use RuntimeException;

/**
 * Modelo Review
 *
 * Gestiona reseñas y ratings de cafés.
 * Tabla: reviews (id, cafe_id, user_id, reservation_id, rating, title, body, status, created_at)
 *
 * Regla: Un usuario final final final solo puede crear UNA reseña por café.
 * Se requiere tener al menos UNA reserva completada en ese café.
 */
class Review
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    // ─────────────────────────────────────────────────────────────
    // Creación y lectura
    // ─────────────────────────────────────────────────────────────

    /**
     * Crea una nueva reseña.
     *
     * @throws RuntimeException Si usuario ya tiene reseña para ese café
     */
    public function create(
        int $cafeId,
        int $userId,
        string $title,
        string $body,
        int $rating,
        ?int $reservationId = null
    ): int {
        // Validar rating
        if ($rating < 1 || $rating > 5) {
            throw new RuntimeException('Rating debe estar entre 1 y 5');
        }

        // Validar que usuario no tenga ya reseña para este café
        $existing = $this->findByUserAndCafe($userId, $cafeId);

        if ($existing) {
            throw new RuntimeException('Ya existe una reseña de este usuario para este café');
        }

        // Validar que usuario tenga reserva completada en este café
        if (!$this->userHasCompletedReservation($userId, $cafeId)) {
            throw new RuntimeException('El usuario no tiene reservas completadas en este café');
        }

        $stmt = $this->db->prepare(
            'INSERT INTO reviews (cafe_id, user_id, reservation_id, rating, title, body, status)
             VALUES (:cafe_id, :user_id, :reservation_id, :rating, :title, :body, :status)'
        );

        $stmt->execute([
            'cafe_id' => $cafeId,
            'user_id' => $userId,
            'reservation_id' => $reservationId,
            'rating' => $rating,
            'title' => $title,
            'body' => $body,
            'status' => 'pending',
        ]);

        return (int) $this->db->lastInsertId();
    }

    private const SELECT_FIELDS = 'id, cafe_id, user_id, reservation_id, rating, title, body, status, created_at, updated_at';

    /**
     * Obtiene reseña por ID.
     *
     * @return array<string,mixed>|null
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT ' . self::SELECT_FIELDS . ' FROM reviews WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : (array) $row;
    }

    /**
     * Obtiene reseña de usuario para café específico.
     */
    public function findByUserAndCafe(int $userId, int $cafeId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT ' . self::SELECT_FIELDS . ' FROM reviews WHERE user_id = :user_id AND cafe_id = :cafe_id LIMIT 1'
        );
        $stmt->execute(['user_id' => $userId, 'cafe_id' => $cafeId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : (array) $row;
    }

    /**
     * Obtiene reseñas APROBADAS de un café (paginado).
     *
     * @return array{data: array, total: int, pages: int}
     */
    public function getApprovedByCafe(int $cafeId, int $page = 1, int $perPage = 10): array
    {
        // Total
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) as count FROM reviews WHERE cafe_id = :cafe_id AND status = :status'
        );
        $stmt->execute(['cafe_id' => $cafeId, 'status' => 'approved']);
        $countRow = $stmt->fetch(PDO::FETCH_ASSOC);
        $total = (int) ($countRow['count'] ?? 0);
        $pages = \ceil($total / $perPage) ?: 1;

        // Data
        $offset = ($page - 1) * $perPage;
        $stmt = $this->db->prepare(
            'SELECT r.id, r.cafe_id, r.user_id, r.rating, r.title, r.body, r.status, r.created_at,
                    u.name, u.avatar
             FROM reviews r
             JOIN users u ON r.user_id = u.id
             WHERE r.cafe_id = :cafe_id AND r.status = :status
             ORDER BY r.created_at DESC
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue('cafe_id', $cafeId, PDO::PARAM_INT);
        $stmt->bindValue('status', 'approved', PDO::PARAM_STR);
        $stmt->bindValue('limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total,
            'pages' => $pages,
        ];
    }

    /**
     * Obtiene reseñas PENDIENTES (para backoffice).
     *
     * @return array<array>
     */
    public function getPending(int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;
        $stmt = $this->db->prepare(
            'SELECT r.id, r.cafe_id, r.user_id, r.rating, r.title, r.body, r.status, r.created_at,
                    c.name as cafe_name, u.name as user_name
             FROM reviews r
             JOIN cafes c ON r.cafe_id = c.id
             JOIN users u ON r.user_id = u.id
             WHERE r.status = :status
             ORDER BY r.created_at
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue('status', 'pending', PDO::PARAM_STR);
        $stmt->bindValue('limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene reseñas del usuario.
     *
     * @return array<array>
     */
    public function getByUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT r.id, r.cafe_id, r.user_id, r.rating, r.title, r.body, r.status, r.created_at,
                    c.name as cafe_name, c.slug
             FROM reviews r
             JOIN cafes c ON r.cafe_id = c.id
             WHERE r.user_id = :user_id
             ORDER BY r.created_at DESC'
        );
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener reseñas aprobadas sencillas por cafe (formato array)
     */
    public function getByCafeId(int $cafeId): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, cafe_id, user_id, rating, title, body, status, created_at
             FROM reviews
             WHERE cafe_id = :cafe_id AND status = :status
             ORDER BY created_at DESC'
        );
        $stmt->execute(['cafe_id' => $cafeId, 'status' => 'approved']);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Actualiza el estado (status) de una reseña.
     */
    public function updateStatus(int $reviewId, string $status): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE reviews SET status = :status WHERE id = :id'
        );

        $ok = $stmt->execute(['status' => $status, 'id' => $reviewId]);

        if ($ok && $status === 'approved') {
            $review = $this->findById($reviewId);
            if ($review) {
                $this->updateCafeRatingCache((int) $review['cafe_id']);
            }
        }

        return $ok;
    }

    // ─────────────────────────────────────────────────────────────
    // Moderación (Backoffice)
    // ─────────────────────────────────────────────────────────────

    /**
     * Aprueba una reseña y recalcula ratings.
     */
    public function approve(int $reviewId): bool
    {
        $review = $this->findById($reviewId);

        if (!$review) {
            throw new RuntimeException('Reseña no encontrada');
        }

        $stmt = $this->db->prepare(
            'UPDATE reviews SET status = :status WHERE id = :id'
        );
        $result = $stmt->execute(['status' => 'approved', 'id' => $reviewId]);

        if ($result) {
            $this->updateCafeRatingCache((int) $review['cafe_id']);
        }

        return $result;
    }

    /**
     * Rechaza una reseña con motivo.
     */
    public function reject(int $reviewId, string $reason): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE reviews SET status = :status, rejection_reason = :reason WHERE id = :id'
        );

        return $stmt->execute([
            'status' => 'rejected',
            'reason' => $reason,
            'id' => $reviewId,
        ]);
    }

    /**
     * Edita una reseña (vuelve a pending).
     */
    public function update(int $reviewId, int $rating, string $title, string $body): bool
    {
        if ($rating < 1 || $rating > 5) {
            throw new RuntimeException('Rating debe estar entre 1 y 5');
        }

        $stmt = $this->db->prepare(
            'UPDATE reviews SET rating = :rating, title = :title, body = :body, status = :status
             WHERE id = :id'
        );

        return $stmt->execute([
            'rating' => $rating,
            'title' => $title,
            'body' => $body,
            'status' => 'pending',
            'id' => $reviewId,
        ]);
    }

    /**
     * Elimina una reseña.
     */
    public function delete(int $reviewId): bool
    {
        return $this->db->prepare('DELETE FROM reviews WHERE id = :id')->execute(['id' => $reviewId]);
    }

    // ─────────────────────────────────────────────────────────────
    // Ratings calculados
    // ─────────────────────────────────────────────────────────────

    /**
     * Calcula el rating promedio de un café.
     *
     * @return array{avg: float, count: int}
     */
    public function calculateCafeRating(int $cafeId): array
    {
        $stmt = $this->db->prepare(
            'SELECT AVG(rating) as avg, COUNT(*) as count FROM reviews
             WHERE cafe_id = :cafe_id AND status = :status'
        );
        $stmt->execute(['cafe_id' => $cafeId, 'status' => 'approved']);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'avg' => \round((float) ($result['avg'] ?? 0), 2),
            'count' => (int) ($result['count'] ?? 0),
        ];
    }

    /**
     * Actualiza caché de ratings en tabla cafes.
     */
    public function updateCafeRatingCache(int $cafeId): void
    {
        $rating = $this->calculateCafeRating($cafeId);

        $stmt = $this->db->prepare(
            'UPDATE cafes SET rating_avg = :avg, rating_count = :count WHERE id = :id'
        );
        $stmt->execute([
            'avg' => $rating['avg'],
            'count' => $rating['count'],
            'id' => $cafeId,
        ]);
    }

    /**
     * Obtiene distribución de ratings (para gráficos).
     *
     * @return array{1: int, 2: int, 3: int, 4: int, 5: int}
     */
    public function getRatingDistribution(int $cafeId): array
    {
        $stmt = $this->db->prepare(
            'SELECT rating, COUNT(*) as count FROM reviews
             WHERE cafe_id = :cafe_id AND status = :status
             GROUP BY rating'
        );
        $stmt->execute(['cafe_id' => $cafeId, 'status' => 'approved']);

        $distribution = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $distribution[$row['rating']] = $row['count'];
        }

        return $distribution;
    }

    // ─────────────────────────────────────────────────────────────
    // Helpers privados
    // ─────────────────────────────────────────────────────────────

    /**
     * Verifica si usuario tiene reserva completada en café.
     */
    private function userHasCompletedReservation(int $userId, int $cafeId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM reservations
             WHERE user_id = :user_id AND cafe_id = :cafe_id AND status = :status
             LIMIT 1'
        );
        $stmt->execute([
            'user_id' => $userId,
            'cafe_id' => $cafeId,
            'status' => 'completed',
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
    }
}
