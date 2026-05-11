<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Domain\DTO\AnimalIncidentDTO;
use App\Domain\Mappers\AnimalIncidentMapper;
use App\Repositories\Contracts\AnimalIncidentRepositoryInterface;
use Override;
use PDO;

final class AnimalIncidentRepository extends AbstractRepository implements AnimalIncidentRepositoryInterface
{
    private AnimalIncidentMapper $mapper;

    public function __construct(?PDO $db = null, ?AnimalIncidentMapper $mapper = null)
    {
        parent::__construct($db);
        $this->mapper = $mapper ?? new AnimalIncidentMapper();
    }

    #[Override]
    protected function getTable(): string
    {
        return 'animal_incidents';
    }

    #[Override]
    protected function getSelectFields(): array
    {
        return ['id', 'animal_id', 'incident_type', 'severity', 'status', 'description', 'resolution', 'resolved_at', 'created_at'];
    }

    public function getActiveIncidents(?int $cafeId = null): array
    {
        if ($cafeId !== null) {
            $stmt = $this->getDb()->prepare('
                SELECT ai.*, a.name AS animal_name, a.species_type AS species
                FROM animal_incidents ai
                JOIN animals a ON ai.animal_id = a.id
                WHERE ai.resolved_at IS NULL AND a.cafe_id = :cafe_id
                ORDER BY ai.severity DESC, ai.created_at DESC
                LIMIT 200
            ');
            $stmt->execute(['cafe_id' => $cafeId]);
        } else {
            $stmt = $this->getDb()->query('
                SELECT ai.*, a.name AS animal_name, a.species_type AS species
                FROM animal_incidents ai
                JOIN animals a ON ai.animal_id = a.id
                WHERE ai.resolved_at IS NULL
                ORDER BY ai.severity DESC, ai.created_at DESC
                LIMIT 200
            ');
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    #[Override]
    public function findById(int $id): ?AnimalIncidentDTO
    {
        $stmt = $this->getDb()->prepare('
            SELECT ai.*, a.name AS animal_name, a.species_type AS species
            FROM animal_incidents ai
            JOIN animals a ON ai.animal_id = a.id
            WHERE ai.id = :id
        ');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $this->mapper->toDTO($row) : null;
    }

    #[Override]
    public function create(array $data): int
    {
        $stmt = $this->getDb()->prepare('
            INSERT INTO animal_incidents
            (animal_id, incident_type, severity, description, reported_by, status)
            VALUES (:animal_id, :incident_type, :severity, :description, :reported_by, \'open\')
        ');
        $stmt->execute([
            'animal_id' => $data['animal_id'],
            'incident_type' => $data['incident_type'] ?? 'general',
            'severity' => $data['severity'],
            'description' => $data['description'],
            'reported_by' => $data['reported_by_user_id'] ?? null,
        ]);

        return (int) $this->getDb()->lastInsertId();
    }

    public function resolve(int $id, ?string $resolution, ?int $userId): bool
    {
        $stmt = $this->getDb()->prepare('
            UPDATE animal_incidents
            SET status = \'resolved\', resolution = :resolution,
                resolved_by = :user_id, resolved_at = NOW()
            WHERE id = :id
        ');

        return $stmt->execute(['resolution' => $resolution, 'user_id' => $userId, 'id' => $id]);
    }
}
