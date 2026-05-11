<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Repositories\Contracts\AdoptionRepositoryInterface;
use Override;
use PDO;

/**
 * Repositorio para solicitudes de adopción de animales.
 *
 * Gestiona las operaciones sobre `animal_adoption_requests` y
 * la vista `v_adoptable_animals` para consultar animales disponibles.
 */
final class AdoptionRepository extends AbstractRepository implements AdoptionRepositoryInterface
{
    #[Override]
    protected function getTable(): string
    {
        return 'animal_adoption_requests';
    }

    #[Override]
    protected function getSelectFields(): array
    {
        return ['id', 'animal_id', 'user_id', 'status', 'message', 'keeper_notes', 'reviewed_by', 'reviewed_at', 'created_at', 'updated_at'];
    }

    // ─── Animales adoptables ─────────────────────────────────────────────────

    /**
     * @return array<int, array<string, mixed>>
     */
    #[Override]
    public function findAdoptable(): array
    {
        $stmt = $this->getDb()->query('SELECT * FROM v_adoptable_animals');

        return $stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    // ─── Solicitudes ─────────────────────────────────────────────────────────

    /**
     * @return array<int, array<string, mixed>>
     */
    #[Override]
    public function findPendingRequests(?int $cafeId = null): array
    {
        if ($cafeId !== null) {
            $stmt = $this->getDb()->prepare('SELECT * FROM v_pending_adoptions WHERE cafe_id = :cafe_id');
            $stmt->execute(['cafe_id' => $cafeId]);
        } else {
            $stmt = $this->getDb()->query('SELECT * FROM v_pending_adoptions');
        }

        return $stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    #[Override]
    public function findRequestsByUser(int $userId): array
    {
        $stmt = $this->getDb()->prepare(
            'SELECT aar.*, a.name AS animal_name, a.species_type
             FROM animal_adoption_requests aar
             INNER JOIN animals a ON a.id = aar.animal_id
             WHERE aar.user_id = :user_id
             ORDER BY aar.created_at DESC'
        );
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<string, mixed>|null
     */
    #[Override]
    public function findRequestById(int $id): ?array
    {
        $stmt = $this->getDb()->prepare(
            'SELECT aar.*, a.name AS animal_name, a.species_type, a.is_adoptable, a.adopted_at,
                    a.cafe_id AS animal_cafe_id,
                    u.name AS applicant_name, u.email AS applicant_email,
                    r.name AS reviewer_name
             FROM animal_adoption_requests aar
             INNER JOIN animals a ON a.id = aar.animal_id
             INNER JOIN users u ON u.id = aar.user_id
             LEFT JOIN users r ON r.id = aar.reviewed_by
             WHERE aar.id = :id'
        );
        $stmt->execute(['id' => $id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    #[Override]
    public function hasPendingRequest(int $animalId, int $userId): bool
    {
        $stmt = $this->getDb()->prepare(
            "SELECT COUNT(*) FROM animal_adoption_requests
             WHERE animal_id = :animal_id AND user_id = :user_id AND status = 'pending'"
        );
        $stmt->execute(['animal_id' => $animalId, 'user_id' => $userId]);

        return (int) $stmt->fetchColumn() > 0;
    }

    #[Override]
    public function createRequest(int $animalId, int $userId, ?string $message): int
    {
        $stmt = $this->getDb()->prepare(
            "INSERT INTO animal_adoption_requests (animal_id, user_id, status, message, created_at, updated_at)
             VALUES (:animal_id, :user_id, 'pending', :message, NOW(), NOW())"
        );
        $stmt->execute([
            'animal_id' => $animalId,
            'user_id' => $userId,
            'message' => $message,
        ]);

        return (int) $this->getDb()->lastInsertId();
    }

    #[Override]
    public function updateRequest(
        int    $id,
        string $status,
        ?int   $reviewedBy,
        ?string $keeperNotes
    ): bool {
        $stmt = $this->getDb()->prepare(
            'UPDATE animal_adoption_requests
             SET status = :status, reviewed_by = :reviewed_by, keeper_notes = :keeper_notes,
                 reviewed_at = NOW(), updated_at = NOW()
             WHERE id = :id'
        );

        return $stmt->execute([
            'status' => $status,
            'reviewed_by' => $reviewedBy,
            'keeper_notes' => $keeperNotes,
            'id' => $id,
        ]) && $stmt->rowCount() > 0;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    #[Override]
    public function findProcessedRequests(?int $cafeId = null): array
    {
        if ($cafeId !== null) {
            $stmt = $this->getDb()->prepare(
                "SELECT aar.id, aar.status, aar.message, aar.keeper_notes, aar.reviewed_at, aar.updated_at,
                        a.name AS animal_name, a.species_type, a.image_url,
                        u.name AS applicant_name, u.email AS applicant_email,
                        r.name AS reviewer_name
                 FROM animal_adoption_requests aar
                 INNER JOIN animals a ON a.id = aar.animal_id
                 INNER JOIN users u ON u.id = aar.user_id
                 LEFT JOIN users r ON r.id = aar.reviewed_by
                 WHERE aar.status IN ('approved', 'rejected') AND a.cafe_id = :cafe_id
                 ORDER BY aar.updated_at DESC
                 LIMIT 200"
            );
            $stmt->execute(['cafe_id' => $cafeId]);
        } else {
            $stmt = $this->getDb()->query(
                "SELECT aar.id, aar.status, aar.message, aar.keeper_notes, aar.reviewed_at, aar.updated_at,
                        a.name AS animal_name, a.species_type, a.image_url,
                        u.name AS applicant_name, u.email AS applicant_email,
                        r.name AS reviewer_name
                 FROM animal_adoption_requests aar
                 INNER JOIN animals a ON a.id = aar.animal_id
                 INNER JOIN users u ON u.id = aar.user_id
                 LEFT JOIN users r ON r.id = aar.reviewed_by
                 WHERE aar.status IN ('approved', 'rejected')
                 ORDER BY aar.updated_at DESC
                 LIMIT 200"
            );
        }

        return $stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }
}
