<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Repositories\Contracts\AnimalIncidentRepositoryInterface;
use PDO;

final class AnimalIncidentRepository implements AnimalIncidentRepositoryInterface
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    public function getActiveIncidents(): array
    {
        $stmt = $this->db->query('
            SELECT ai.*, a.name AS animal_name, a.species_type AS species
            FROM animal_incidents ai
            JOIN animals a ON ai.animal_id = a.id
            WHERE ai.resolved_at IS NULL
            ORDER BY ai.severity DESC, ai.created_at DESC
        ');

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('
            SELECT ai.*, a.name AS animal_name, a.species_type AS species
            FROM animal_incidents ai
            JOIN animals a ON ai.animal_id = a.id
            WHERE ai.id = :id
        ');
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare('
            INSERT INTO animal_incidents
            (animal_id, severity, description, reported_by_user_id, reported_at, status)
            VALUES (:animal_id, :severity, :description, :user_id, NOW(), "open")
        ');
        $stmt->execute([
            'animal_id'   => $data['animal_id'],
            'severity'    => $data['severity'],
            'description' => $data['description'],
            'user_id'     => $data['reported_by_user_id'] ?? null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function resolve(int $id, ?string $resolution, ?int $userId): bool
    {
        $stmt = $this->db->prepare('
            UPDATE animal_incidents
            SET status = "resolved", resolution = :resolution,
                resolved_by_user_id = :user_id, resolved_at = NOW()
            WHERE id = :id
        ');

        return $stmt->execute(['resolution' => $resolution, 'user_id' => $userId, 'id' => $id]);
    }
}
