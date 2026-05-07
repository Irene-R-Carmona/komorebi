<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Modelo Favorite
 *
 * Gestiona los cafés favoritos de los usuarios.
 */
final class Favorite
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    /**
     * Añade un café a favoritos.
     */
    public function add(int $userId, int $cafeId): bool
    {
        // Usar INSERT IGNORE para evitar duplicados
        $stmt = $this->db->prepare(
            'INSERT IGNORE INTO favorites (user_id, cafe_id) VALUES (:user_id, :cafe_id)'
        );

        return $stmt->execute([
            'user_id' => $userId,
            'cafe_id' => $cafeId,
        ]);
    }

    /**
     * Elimina un café de favoritos.
     */
    public function remove(int $userId, int $cafeId): bool
    {
        $stmt = $this->db->prepare(
            'DELETE FROM favorites WHERE user_id = :user_id AND cafe_id = :cafe_id'
        );

        return $stmt->execute([
            'user_id' => $userId,
            'cafe_id' => $cafeId,
        ]);
    }

    /**
     * Toggle: añade si no existe, elimina si existe.
     *
     * @return boolean True si se añadió, false si se eliminó
     */
    public function toggle(int $userId, int $cafeId): bool
    {
        if ($this->exists($userId, $cafeId)) {
            $this->remove($userId, $cafeId);

            return false;
        }

        $this->add($userId, $cafeId);

        return true;
    }

    /**
     * Verifica si un café está en favoritos.
     */
    public function exists(int $userId, int $cafeId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM favorites
             WHERE user_id = :user_id AND cafe_id = :cafe_id
             LIMIT 1'
        );
        $stmt->execute([
            'user_id' => $userId,
            'cafe_id' => $cafeId,
        ]);

        return (bool) $stmt->fetch();
    }

    /**
     * Obtiene los IDs de cafés favoritos de un usuario.
     * Útil para marcar favoritos en listados.
     *
     * @return array<int>
     */
    public function getCafeIds(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT cafe_id FROM favorites WHERE user_id = :user_id'
        );
        $stmt->execute(['user_id' => $userId]);

        return \array_column($stmt->fetchAll(), 'cafe_id');
    }

    /**
     * Obtiene los cafés favoritos de un usuario con detalles.
     */
    public function getByUser(int $userId): array
    {
        $sql = 'SELECT c.id, c.name, c.japanese_name, c.slug, c.location,
                       c.category, c.animal_type, c.price_per_hour, c.rating_avg,
                       c.image_url, f.created_at AS favorited_at
                FROM favorites f
                JOIN cafes c ON c.id = f.cafe_id
                WHERE f.user_id = :user_id AND c.is_active = 1
                ORDER BY f.created_at DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll();
    }

    /**
     * Cuenta el total de favoritos de un usuario.
     */
    public function countByUser(int $userId): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM favorites WHERE user_id = :user_id'
        );
        $stmt->execute(['user_id' => $userId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Obtiene los usuarios que tienen un café como favorito.
     * Útil para notificaciones o estadísticas.
     */
    public function getUsersByCafe(int $cafeId): array
    {
        $sql = 'SELECT u.id, u.name, u.email, f.created_at AS favorited_at
                FROM favorites f
                JOIN users u ON u.id = f.user_id
                WHERE f.cafe_id = :cafe_id AND u.is_active = 1
                ORDER BY f.created_at DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['cafe_id' => $cafeId]);

        return $stmt->fetchAll();
    }

    /**
     * Obtiene los cafés más populares (más favoritos).
     */
    public function getMostPopular(int $limit = 10): array
    {
        $sql = 'SELECT c.id, c.name, c.slug, c.category, c.animal_type,
                       c.rating_avg, c.image_url,
                       COUNT(f.user_id) as favorites_count
                FROM cafes c
                JOIN favorites f ON f.cafe_id = c.id
                WHERE c.is_active = 1
                GROUP BY c.id
                ORDER BY favorites_count DESC
                LIMIT :limit';

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
