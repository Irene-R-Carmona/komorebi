<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Repositories\Contracts\AnimalRepositoryInterface;
use PDO;

/**
 * Repositorio de Animales
 *
 * Implementa acceso a datos de animales con prepared statements.
 * Sigue el principio SOLID de Inversión de Dependencias.
 */
final class AnimalRepository implements AnimalRepositoryInterface
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * {@inheritDoc}
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                id, cafe_id, current_zone_id, name, species_type, age,
                personality, description, interaction_level, attributes,
                image_url, current_status, last_check_at, last_health_check,
                deleted_at, created_at, updated_at
            FROM animals
            WHERE id = ? AND deleted_at IS NULL
        ");

        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    /**
     * {@inheritDoc}
     */
    public function findActiveByCafe(int $cafeId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                id, cafe_id, name, species_type, current_status,
                interaction_level, image_url, personality
            FROM animals
            WHERE cafe_id = ?
              AND current_status = 'active'
              AND deleted_at IS NULL
            ORDER BY name ASC
        ");

        $stmt->execute([$cafeId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * {@inheritDoc}
     */
    public function isAvailable(int $animalId): bool
    {
        $stmt = $this->db->prepare("
            SELECT current_status
            FROM animals
            WHERE id = ? AND deleted_at IS NULL
        ");

        $stmt->execute([$animalId]);
        $animal = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$animal) {
            return false;
        }

        // Solo animales con status 'active' están disponibles
        return $animal['current_status'] === 'active';
    }

    /**
     * {@inheritDoc}
     */
    public function isResting(int $animalId): bool
    {
        // Verificar si el animal tiene status de descanso
        $stmt = $this->db->prepare("
            SELECT current_status
            FROM animals
            WHERE id = ? AND deleted_at IS NULL
        ");

        $stmt->execute([$animalId]);
        $animal = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$animal) {
            return false;
        }

        // Si está en estado resting, sick o retired, está descansando
        $restingStatuses = ['resting', 'sick', 'retired'];

        return in_array($animal['current_status'], $restingStatuses, true);
    }

    /**
     * {@inheritDoc}
     */
    public function getAnimalsWithCafeInfoOptimized(): array
    {
        $stmt = $this->db->query("
            SELECT
                a.*,
                c.name as cafe_name,
                COUNT(hc.id) as logs_today
            FROM animals a
            LEFT JOIN cafes c ON a.cafe_id = c.id
            LEFT JOIN animal_health_checks hc
                ON hc.animal_id = a.id
                AND DATE(hc.check_date) = CURDATE()
            WHERE a.deleted_at IS NULL
            GROUP BY
                a.id, a.cafe_id, a.current_zone_id, a.name, a.species_type,
                a.age, a.personality, a.description, a.interaction_level,
                a.attributes, a.current_status, a.image_url,
                a.last_check_at, a.last_health_check, a.deleted_at,
                a.created_at, a.updated_at, c.name
            ORDER BY a.name
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * {@inheritDoc}
     */
    public function getHealthStatistics(): array
    {
        // Una sola query con SUM + CASE para contar por estado

        $stmt = $this->db->query("
            SELECT
                COUNT(*) as total_animals,
                SUM(CASE WHEN current_status = 'active' THEN 1 ELSE 0 END) as healthy,
                SUM(CASE WHEN current_status = 'resting' THEN 1 ELSE 0 END) as monitoring,
                SUM(CASE WHEN current_status = 'sick' THEN 1 ELSE 0 END) as sick
            FROM animals
            WHERE deleted_at IS NULL
        ");

        $animalStats = $stmt->fetch(PDO::FETCH_ASSOC);

        // Query separada para logs de hoy (no afecta performance)
        $logsStmt = $this->db->query("
            SELECT COUNT(*) as logs_today
            FROM animal_health_checks
            WHERE check_date = CURDATE()
        ");

        $logsData = $logsStmt->fetch(PDO::FETCH_ASSOC);

        return [
            'total_animals' => (int) $animalStats['total_animals'],
            'healthy' => (int) $animalStats['healthy'],
            'monitoring' => (int) $animalStats['monitoring'],
            'sick' => (int) $animalStats['sick'],
            'logs_today' => (int) $logsData['logs_today'],
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function getRecentLogs(int $limit = 20): array
    {
        $stmt = $this->db->prepare("
            SELECT
                hc.*,
                a.name as animal_name,
                a.species_type as species,
                u.name as keeper_name
            FROM animal_health_checks hc
            JOIN animals a ON hc.animal_id = a.id
            LEFT JOIN users u ON hc.checked_by = u.id
            WHERE hc.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ORDER BY hc.created_at DESC
            LIMIT :limit
        ");

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * {@inheritDoc}
     */
    public function getActiveIncidents(): array
    {
        $stmt = $this->db->query("
            SELECT
                ai.*,
                a.name as animal_name,
                a.species_type as species
            FROM animal_incidents ai
            JOIN animals a ON ai.animal_id = a.id
            WHERE ai.resolved_at IS NULL
            ORDER BY ai.severity DESC, ai.created_at DESC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * {@inheritDoc}
     */
    public function findIncidentById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                ai.*,
                a.name as animal_name,
                a.species_type as species
            FROM animal_incidents ai
            JOIN animals a ON ai.animal_id = a.id
            WHERE ai.id = :id
        ");
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    /**
     * {@inheritDoc}
     */
    public function updateImageUrl(int $animalId, string $imageUrl): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE animals SET image_url = :image_url, updated_at = NOW() WHERE id = :id AND deleted_at IS NULL'
        );

        return $stmt->execute([
            'image_url' => $imageUrl,
            'id' => $animalId,
        ]);
    }
}
