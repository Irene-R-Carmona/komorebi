<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Repositories\AbstractRepository;
use App\Repositories\Contracts\FavoriteRepositoryInterface;
use Override;
use PDO;

final class FavoriteRepository extends AbstractRepository implements FavoriteRepositoryInterface
{
    public function __construct(?PDO $db = null)
    {
        parent::__construct($db);
    }

    #[Override]
    protected function getTable(): string
    {
        return 'favorites';
    }

    #[Override]
    protected function getSelectFields(): array
    {
        return ['user_id', 'cafe_id', 'created_at'];
    }

    public function add(int $userId, int $cafeId): bool
    {
        return $this->getDb()->prepare(
            'INSERT IGNORE INTO favorites (user_id, cafe_id) VALUES (:user_id, :cafe_id)'
        )->execute(['user_id' => $userId, 'cafe_id' => $cafeId]);
    }

    public function remove(int $userId, int $cafeId): bool
    {
        return $this->getDb()->prepare(
            'DELETE FROM favorites WHERE user_id = :user_id AND cafe_id = :cafe_id'
        )->execute(['user_id' => $userId, 'cafe_id' => $cafeId]);
    }

    public function toggle(int $userId, int $cafeId): bool
    {
        if ($this->existsForUser($userId, $cafeId)) {
            $this->remove($userId, $cafeId);

            return false;
        }

        $this->add($userId, $cafeId);

        return true;
    }

    public function existsForUser(int $userId, int $cafeId): bool
    {
        $stmt = $this->getDb()->prepare(
            'SELECT 1 FROM favorites WHERE user_id = :user_id AND cafe_id = :cafe_id LIMIT 1'
        );
        $stmt->execute(['user_id' => $userId, 'cafe_id' => $cafeId]);

        return (bool) $stmt->fetch();
    }

    public function getCafeIds(int $userId): array
    {
        $stmt = $this->getDb()->prepare('SELECT cafe_id FROM favorites WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);

        return \array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'cafe_id');
    }

    public function getByUser(int $userId): array
    {
        $stmt = $this->getDb()->prepare(
            'SELECT c.id, c.name, c.japanese_name, c.slug, c.location,
                    c.category, c.animal_type, c.price_per_hour, c.rating,
                    c.image_url, f.created_at AS favorited_at
             FROM favorites f
             JOIN cafes c ON c.id = f.cafe_id
             WHERE f.user_id = :user_id AND c.is_active = 1
             ORDER BY f.created_at DESC'
        );
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countByUser(int $userId): int
    {
        $stmt = $this->getDb()->prepare('SELECT COUNT(*) FROM favorites WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);

        return (int) $stmt->fetchColumn();
    }

    public function getUsersByCafe(int $cafeId): array
    {
        $stmt = $this->getDb()->prepare(
            'SELECT u.id, u.name, u.email, f.created_at AS favorited_at
             FROM favorites f
             JOIN users u ON u.id = f.user_id
             WHERE f.cafe_id = :cafe_id AND u.is_active = 1
             ORDER BY f.created_at DESC'
        );
        $stmt->execute(['cafe_id' => $cafeId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getMostPopular(int $limit = 10): array
    {
        $stmt = $this->getDb()->prepare(
            'SELECT c.id, c.name, c.slug, c.category, c.animal_type,
                    c.rating, c.image_url,
                    COUNT(f.user_id) as favorites_count
             FROM cafes c
             JOIN favorites f ON f.cafe_id = c.id
             WHERE c.is_active = 1
             GROUP BY c.id
             ORDER BY favorites_count DESC
             LIMIT :limit'
        );
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
