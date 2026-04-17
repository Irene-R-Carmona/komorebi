<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Result;
use App\Core\TransactionalService;
use App\Repositories\Contracts\AnimalRepositoryInterface;
use App\Services\Contracts\AnimalCareServiceInterface;
use Exception;
use PDO;

/**
 * Servicio de gestión de bienestar animal
 *
 * Encapsula la lógica de negocio relacionada con animales,
 * logs de cuidado, estado de salud e incidentes.
 *
 * @package Komorebi\Services
 */
final class AnimalCareService extends TransactionalService implements AnimalCareServiceInterface
{
    private AnimalRepositoryInterface $animalRepository;

    public function __construct(PDO $db, AnimalRepositoryInterface $animalRepository)
    {
        parent::__construct($db);
        $this->animalRepository = $animalRepository;
    }

    /**
     * Obtiene todos los animales con información del café
     *
     * @return array
     */
    #[\Override]
    public function getAllAnimals(): array
    {
        return $this->animalRepository->getAnimalsWithCafeInfoOptimized();
    }

    /**
     * Obtiene un animal por ID
     *
     * @param integer $id
     * @return array|null
     */
    #[\Override]
    public function getAnimalById(int $id): ?array
    {
        return $this->animalRepository->findById($id);
    }

    /**
     * Crea un nuevo animal
     *
     * @param array $data Datos del animal (name, species, breed, age_years, personality, cafe_id)
     * @return Result ID del animal creado
     */
    #[\Override]
    public function createAnimal(array $data): Result
    {
        if (empty($data['name']) || empty($data['species'])) {
            return Result::fail('Nombre y especie son obligatorios');
        }

        try {
            $stmt = $this->db->prepare('
                INSERT INTO animals (cafe_id, name, species_type, age, personality, current_status, created_at, updated_at)
                VALUES (:cafe_id, :name, :species, :age, :personality, \'active\', NOW(), NOW())
            ');

            $stmt->execute([
                'cafe_id' => $data['cafe_id'] ?? null,
                'name' => $data['name'],
                'species' => $data['species'],
                'age' => $data['age_years'] ?? null,
                'personality' => $data['personality'] ?? null,
            ]);

            return Result::ok((int) $this->db->lastInsertId());
        } catch (Exception $e) {
            return Result::fail('Error al crear animal: ' . $e->getMessage());
        }
    }

    /**
     * Actualiza un animal existente
     *
     * @param integer $id   ID del animal
     * @param array   $data Datos a actualizar
     * @return Result
     */
    #[\Override]
    public function updateAnimal(int $id, array $data): Result
    {
        try {
            $stmt = $this->db->prepare('
                UPDATE animals
                SET name = :name, species_type = :species, age = :age,
                    personality = :personality, cafe_id = :cafe_id, updated_at = NOW()
                WHERE id = :id AND deleted_at IS NULL
            ');

            $stmt->execute([
                'name' => $data['name'] ?? '',
                'species' => $data['species'] ?? '',
                'age' => $data['age_years'] ?? null,
                'personality' => $data['personality'] ?? null,
                'cafe_id' => $data['cafe_id'] ?? null,
                'id' => $id,
            ]);

            if ($stmt->rowCount() === 0) {
                return Result::fail('Animal no encontrado');
            }

            return Result::ok(true);
        } catch (Exception $e) {
            return Result::fail('Error al actualizar animal: ' . $e->getMessage());
        }
    }

    /**
     * Elimina un animal (soft delete)
     *
     * @param integer $id ID del animal
     * @return Result
     */
    #[\Override]
    public function deleteAnimal(int $id): Result
    {
        try {
            $stmt = $this->db->prepare('
                UPDATE animals SET deleted_at = NOW() WHERE id = :id AND deleted_at IS NULL
            ');
            $stmt->execute(['id' => $id]);

            if ($stmt->rowCount() === 0) {
                return Result::fail('Animal no encontrado');
            }

            return Result::ok(true);
        } catch (Exception $e) {
            return Result::fail('Error al eliminar animal: ' . $e->getMessage());
        }
    }

    /**
     * Obtiene el dashboard completo de bienestar animal
     *
     * @return array{animals: array, stats: array, recent_logs: array, active_incidents: array}
     */
    #[\Override]
    public function getDashboardData(): array
    {
        return [
            'animals' => $this->animalRepository->getAnimalsWithCafeInfoOptimized(),
            'stats' => $this->animalRepository->getHealthStatistics(),
            'recent_logs' => $this->animalRepository->getRecentLogs(20),
            'active_incidents' => $this->animalRepository->getActiveIncidents(),
        ];
    }

    /**
     * Obtiene animales con información del café y logs de hoy
     *
     * @return array
     */
    #[\Override]
    public function getAnimalsWithCafeInfo(): array
    {
        return $this->animalRepository->getAnimalsWithCafeInfoOptimized();
    }

    /**
     * Obtiene estadísticas generales de animales
     *
     * @return array{total_animals: int, healthy: int, monitoring: int, sick: int, logs_today: int}
     */
    #[\Override]
    public function getStatistics(): array
    {
        return $this->animalRepository->getHealthStatistics();
    }

    /**
     * Obtiene logs recientes de cuidado
     *
     * @param integer $limit Número máximo de logs
     * @return array
     */
    #[\Override]
    public function getRecentLogs(int $limit = 20): array
    {
        return $this->animalRepository->getRecentLogs($limit);
    }

    /**
     * Obtiene incidentes activos
     *
     * @return array
     */
    #[\Override]
    public function getActiveIncidents(): array
    {
        return $this->animalRepository->getActiveIncidents();
    }

    /**
     * Obtener un incidente por su ID.
     *
     * @param int $id ID del incidente
     * @return array<string, mixed>|null
     */
    #[\Override]
    public function getIncidentById(int $id): ?array
    {
        return $this->animalRepository->findIncidentById($id);
    }

    /**
     * Registra un log de cuidado animal
     *
     * @param array $data Datos del log
     * @return Result ID del log creado
     */
    #[\Override]
    public function createCareLog(array $data): Result
    {
        // Validaciones de negocio
        $validation = $this->validateCareLogData($data);
        if ($validation->error !== null) {
            return $validation;
        }

        try {
            // Adaptar al esquema de animal_health_checks
            // Concatenar tipo de actividad con las notas
            $activityType = $data['activity_type'] ?? 'general';
            $userNotes = $data['notes'] ?? '';
            $fullNotes = "[{$activityType}]";

            if ($data['duration_minutes'] ?? null) {
                $fullNotes .= " ({$data['duration_minutes']} min)";
            }
            if ($data['mood_before'] ?? null) {
                $fullNotes .= " Ánimo inicial: {$data['mood_before']}";
            }
            if ($data['mood_after'] ?? null) {
                $fullNotes .= " → {$data['mood_after']}";
            }
            if ($userNotes) {
                $fullNotes .= "\n" . $userNotes;
            }

            $stmt = $this->db->prepare('
                INSERT INTO animal_health_checks
                (animal_id, checked_by, check_date, notes, created_at)
                VALUES (:animal_id, :checked_by, CURDATE(), :notes, NOW())
                ON DUPLICATE KEY UPDATE
                    notes = CONCAT(notes, "\n---\n", :notes_update),
                    created_at = NOW()
            ');

            $stmt->execute([
                'animal_id' => $data['animal_id'],
                'checked_by' => $data['logged_by_user_id'] ?? 1,
                'notes' => $fullNotes,
                'notes_update' => $fullNotes,
            ]);

            $logId = (int) $this->db->lastInsertId();

            return Result::ok($logId);
        } catch (Exception $e) {
            return Result::fail('Error al registrar log: ' . $e->getMessage());
        }
    }

    /**
     * Actualiza el estado de salud de un animal
     *
     * @param integer      $animalId     ID del animal
     * @param string       $healthStatus Nuevo estado de salud
     * @param string|null  $notes        Notas opcionales
     * @param integer|null $userId       ID del usuario que realiza la actualización
     * @return Result
     */
    #[\Override]
    public function updateHealth(int $animalId, string $healthStatus, ?string $notes = null, ?int $userId = null): Result
    {
        // Validación de estado
        $validStatuses = ['healthy', 'monitoring', 'sick', 'recovering', 'quarantine'];
        if (!\in_array($healthStatus, $validStatuses, true)) {
            return Result::fail('Estado de salud inválido');
        }

        // Mapeo de valores legacy a current_status enum
        $statusMapping = [
            'healthy' => 'active',
            'monitoring' => 'resting',
            'sick' => 'sick',
            'recovering' => 'resting',
            'quarantine' => 'retired',
        ];
        $currentStatus = $statusMapping[$healthStatus];

        return $this->transact(function () use ($animalId, $currentStatus, $notes, $userId): Result {
            // Actualizar estado del animal
            $stmt = $this->db->prepare('
                UPDATE animals
                SET current_status = :status, last_health_check = NOW()
                WHERE id = :animal_id
            ');
            $stmt->execute([
                'status' => $currentStatus,
                'animal_id' => $animalId,
            ]);

            // Si hay notas, crear log automático
            if ($notes) {
                $this->createCareLog([
                    'animal_id' => $animalId,
                    'activity_type' => 'health_check',
                    'notes' => $notes,
                    'logged_by_user_id' => $userId,
                ]);
            }

            return Result::ok(true);
        });
    }

    /**
     * Activa o desactiva un animal
     *
     * @param integer $animalId ID del animal
     * @return Result
     */
    #[\Override]
    public function toggleActive(int $animalId): Result
    {
        try {
            // Obtener estado actual
            $stmt = $this->db->prepare('SELECT current_status FROM animals WHERE id = :id AND deleted_at IS NULL');
            $stmt->execute(['id' => $animalId]);
            $animal = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$animal) {
                return Result::fail('Animal no encontrado');
            }

            // Alternar entre 'active' y 'resting'
            $newStatus = $animal['current_status'] === 'active' ? 'resting' : 'active';

            $stmt = $this->db->prepare('UPDATE animals SET current_status = :status, updated_at = NOW() WHERE id = :id');
            $stmt->execute([
                'status' => $newStatus,
                'id' => $animalId,
            ]);

            $statusText = $newStatus === 'active' ? 'activado' : 'puesto en descanso';

            return Result::ok([
                'current_status' => $newStatus,
                'message' => "Animal $statusText exitosamente",
            ]);
        } catch (Exception $e) {
            return Result::fail('Error al cambiar estado: ' . $e->getMessage());
        }
    }

    /**
     * Crea un nuevo incidente
     *
     * @param array $data Datos del incidente
     * @return Result ID del incidente creado
     */
    #[\Override]
    public function createIncident(array $data): Result
    {
        // Validaciones
        $validation = $this->validateIncidentData($data);
        if (!$validation->ok) {
            return $validation;
        }

        return $this->transact(function () use ($data): Result {
            $stmt = $this->db->prepare('
                INSERT INTO animal_incidents
                (animal_id, severity, description, reported_by_user_id, reported_at, status)
                VALUES (:animal_id, :severity, :description, :user_id, NOW(), "open")
            ');

            $stmt->execute([
                'animal_id' => $data['animal_id'],
                'severity' => $data['severity'],
                'description' => $data['description'],
                'user_id' => $data['reported_by_user_id'] ?? null,
            ]);

            $incidentId = (int) $this->db->lastInsertId();

            // Si es crítico, actualizar estado del animal automáticamente
            if ($data['severity'] === 'critical') {
                $stmt = $this->db->prepare('UPDATE animals SET health_status = "monitoring" WHERE id = :id');
                $stmt->execute(['id' => $data['animal_id']]);
            }

            return Result::ok($incidentId);
        });
    }

    /**
     * Resuelve un incidente
     *
     * @param integer      $incidentId ID del incidente
     * @param string|null  $resolution Descripción de la resolución
     * @param integer|null $userId     ID del usuario que resuelve
     * @return Result
     */
    #[\Override]
    public function resolveIncident(int $incidentId, ?string $resolution = null, ?int $userId = null): Result
    {
        try {
            $stmt = $this->db->prepare('
                UPDATE animal_incidents
                SET status = "resolved",
                    resolution = :resolution,
                    resolved_by_user_id = :user_id,
                    resolved_at = NOW()
                WHERE id = :id
            ');

            $stmt->execute([
                'resolution' => $resolution,
                'user_id' => $userId,
                'id' => $incidentId,
            ]);

            return Result::ok(true);
        } catch (Exception $e) {
            return Result::fail('Error al resolver incidente: ' . $e->getMessage());
        }
    }

    /**
     * Valida datos de log de cuidado
     *
     * @param array $data
     * @return Result
     */
    private function validateCareLogData(array $data): Result
    {
        if (empty($data['animal_id']) || $data['animal_id'] <= 0) {
            return Result::fail('ID de animal inválido');
        }

        $validActivities = ['feeding', 'grooming', 'health_check', 'play', 'cleaning', 'medication', 'exercise', 'other'];
        if (empty($data['activity_type']) || !\in_array($data['activity_type'], $validActivities, true)) {
            return Result::fail('Tipo de actividad inválido');
        }

        return Result::ok();
    }

    /**
     * Valida datos de incidente
     *
     * @param array $data
     * @return Result
     */
    private function validateIncidentData(array $data): Result
    {
        if (empty($data['animal_id']) || $data['animal_id'] <= 0) {
            return Result::fail('ID de animal inválido');
        }

        $validSeverities = ['low', 'medium', 'high', 'critical'];
        if (empty($data['severity']) || !\in_array($data['severity'], $validSeverities, true)) {
            return Result::fail('Nivel de severidad inválido');
        }

        if (empty($data['description']) || \strlen($data['description']) < 10) {
            return Result::fail('La descripción debe tener al menos 10 caracteres');
        }

        return Result::ok();
    }
}
