<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Repositories\Contracts\AnimalRepositoryInterface;
use Override;
use PDO;

/**
 * Repositorio de Animales.
 *
 * Encapsula el acceso a datos de animales, health_checks e incidentes
 * siguiendo el principio de Inversión de Dependencias (SOLID).
 */
final class AnimalRepository extends AbstractRepository implements AnimalRepositoryInterface
{
    #[Override]
    protected function getTable(): string
    {
        return 'animals';
    }

    #[Override]
    protected function getSelectFields(): array
    {
        return [
            'id', 'cafe_id', 'current_zone_id', 'name', 'species_type', 'age',
            'personality', 'description', 'interaction_level', 'attributes',
            'image_url', 'current_status', 'last_check_at', 'last_health_check',
            'deleted_at', 'created_at', 'updated_at',
        ];
    }

    // ─── Read ───────────────────────────────────────────────────────────────

    #[Override]
    public function findById(int $id): ?array
    {
        $fields = \implode(', ', $this->getSelectFields());
        $stmt = $this->getDb()->prepare(
            "SELECT $fields FROM animals WHERE id = :id AND deleted_at IS NULL LIMIT 1"
        );
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    #[Override]
    public function findActiveByCafe(int $cafeId): array
    {
        $stmt = $this->getDb()->prepare("
            SELECT id, cafe_id, name, species_type, current_status,
                   interaction_level, image_url, personality
            FROM animals
            WHERE cafe_id = :cafe_id AND current_status = 'active' AND deleted_at IS NULL
            ORDER BY name ASC
        ");
        $stmt->execute(['cafe_id' => $cafeId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    #[Override]
    public function isAvailable(int $animalId): bool
    {
        return $this->fetchCurrentStatus($animalId) === 'active';
    }

    #[Override]
    public function isResting(int $animalId): bool
    {
        return \in_array($this->fetchCurrentStatus($animalId), ['resting', 'sick', 'retired'], true);
    }

    private function fetchCurrentStatus(int $id): ?string
    {
        $stmt = $this->getDb()->prepare(
            'SELECT current_status FROM animals WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $row['current_status'] : null;
    }

    #[Override]
    public function getAnimalsWithCafeInfoOptimized(): array
    {
        $stmt = $this->getDb()->query('
            SELECT a.*, c.name as cafe_name, COUNT(hc.id) as logs_today
            FROM animals a
            LEFT JOIN cafes c ON a.cafe_id = c.id
            LEFT JOIN animal_health_checks hc
                ON hc.animal_id = a.id AND DATE(hc.check_date) = CURDATE()
            WHERE a.deleted_at IS NULL
            GROUP BY a.id, a.cafe_id, a.current_zone_id, a.name, a.species_type,
                     a.age, a.personality, a.description, a.interaction_level,
                     a.attributes, a.current_status, a.image_url,
                     a.last_check_at, a.last_health_check, a.deleted_at,
                     a.created_at, a.updated_at, c.name
            ORDER BY a.name
        ');

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    #[Override]
    public function getHealthStatistics(): array
    {
        $stmt = $this->getDb()->query("
            SELECT COUNT(*) as total_animals,
                   SUM(CASE WHEN current_status = 'active'  THEN 1 ELSE 0 END) as healthy,
                   SUM(CASE WHEN current_status = 'resting' THEN 1 ELSE 0 END) as monitoring,
                   SUM(CASE WHEN current_status = 'sick'    THEN 1 ELSE 0 END) as sick
            FROM animals WHERE deleted_at IS NULL
        ");
        $animalStats = $stmt->fetch(PDO::FETCH_ASSOC);

        $logsToday = (int) $this->getDb()
            ->query('SELECT COUNT(*) FROM animal_health_checks WHERE check_date = CURDATE()')
            ->fetchColumn();

        return [
            'total_animals' => (int) $animalStats['total_animals'],
            'healthy'       => (int) $animalStats['healthy'],
            'monitoring'    => (int) $animalStats['monitoring'],
            'sick'          => (int) $animalStats['sick'],
            'logs_today'    => $logsToday,
        ];
    }

    #[Override]
    public function countDistinctSpecies(): int
    {
        $stmt = $this->getDb()->query(
            "SELECT COUNT(DISTINCT species_type) FROM animals WHERE current_status IN ('active', 'resting')"
        );

        return (int) $stmt->fetchColumn();
    }

    #[Override]
    public function updateImageUrl(int $animalId, string $imageUrl): bool
    {
        $stmt = $this->getDb()->prepare(
            'UPDATE animals SET image_url = :url, updated_at = NOW() WHERE id = :id AND deleted_at IS NULL'
        );

        return $stmt->execute(['url' => $imageUrl, 'id' => $animalId]);
    }

    // ─── Write ──────────────────────────────────────────────────────────────

    #[Override]
    public function createAnimal(array $data): int
    {
        $stmt = $this->getDb()->prepare('
            INSERT INTO animals (cafe_id, name, species_type, age, personality, current_status, created_at, updated_at)
            VALUES (:cafe_id, :name, :species, :age, :personality, \'active\', NOW(), NOW())
        ');
        $stmt->execute([
            'cafe_id'     => $data['cafe_id'] ?? null,
            'name'        => $data['name'],
            'species'     => $data['species'],
            'age'         => $data['age_years'] ?? null,
            'personality' => $data['personality'] ?? null,
        ]);

        return (int) $this->getDb()->lastInsertId();
    }

    #[Override]
    public function updateAnimal(int $id, array $data): bool
    {
        $stmt = $this->getDb()->prepare('
            UPDATE animals
            SET name = :name, species_type = :species, age = :age,
                personality = :personality, cafe_id = :cafe_id, updated_at = NOW()
            WHERE id = :id AND deleted_at IS NULL
        ');

        return $stmt->execute([
            'name'        => $data['name'] ?? '',
            'species'     => $data['species'] ?? '',
            'age'         => $data['age_years'] ?? null,
            'personality' => $data['personality'] ?? null,
            'cafe_id'     => $data['cafe_id'] ?? null,
            'id'          => $id,
        ]) && $stmt->rowCount() > 0;
    }

    #[Override]
    public function softDeleteAnimal(int $id): bool
    {
        $stmt = $this->getDb()->prepare(
            'UPDATE animals SET deleted_at = NOW() WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }

    #[Override]
    public function updateStatus(int $id, string $status): bool
    {
        $stmt = $this->getDb()->prepare(
            'UPDATE animals SET current_status = :status, last_health_check = NOW() WHERE id = :id'
        );

        return $stmt->execute(['status' => $status, 'id' => $id]);
    }

    #[Override]
    public function toggleStatus(int $id): array
    {
        $current = $this->fetchCurrentStatus($id);
        if ($current === null) {
            return ['found' => false];
        }

        $newStatus = $current === 'active' ? 'resting' : 'active';

        $this->getDb()->prepare(
            'UPDATE animals SET current_status = :status, updated_at = NOW() WHERE id = :id'
        )->execute(['status' => $newStatus, 'id' => $id]);

        return ['found' => true, 'current_status' => $newStatus];
    }

}
