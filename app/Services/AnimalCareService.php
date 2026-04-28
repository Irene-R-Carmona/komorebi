<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\BaseService;
use App\Core\Container;
use App\Core\Database;
use App\Core\Result;
use App\Domain\AnimalVocabulary;
use App\Domain\CareLogVocabulary;
use App\Repositories\Contracts\AnimalIncidentRepositoryInterface;
use App\Repositories\Contracts\AnimalRepositoryInterface;
use App\Repositories\Contracts\HealthCheckRepositoryInterface;
use App\Services\Contracts\AnimalCareServiceInterface;
use Exception;
use Override;

final class AnimalCareService extends BaseService implements AnimalCareServiceInterface
{
    private AnimalRepositoryInterface $animalRepo;
    private AnimalIncidentRepositoryInterface $incidentRepo;
    private HealthCheckRepositoryInterface $healthCheckRepo;

    public function __construct(
        ?AnimalRepositoryInterface $animalRepo = null,
        ?AnimalIncidentRepositoryInterface $incidentRepo = null,
        ?HealthCheckRepositoryInterface $healthCheckRepo = null,
    ) {
        $this->animalRepo = $animalRepo ?? Container::make(AnimalRepositoryInterface::class);
        $this->incidentRepo = $incidentRepo ?? Container::make(AnimalIncidentRepositoryInterface::class);
        $this->healthCheckRepo = $healthCheckRepo ?? Container::make(HealthCheckRepositoryInterface::class);
    }

    #[Override]
    public function getAllAnimals(): array
    {
        return $this->animalRepo->getAnimalsWithCafeInfoOptimized();
    }

    #[Override]
    public function getAnimalById(int $id): ?array
    {
        return $this->animalRepo->findById($id)?->toViewArray();
    }

    #[Override]
    public function createAnimal(array $data): Result
    {
        if (empty($data['name']) || empty($data['species'])) {
            return Result::fail('Nombre y especie son obligatorios');
        }

        if (!AnimalVocabulary::isValidSpecies((string) $data['species'])) {
            return Result::fail('Especie no válida', 'invalid_species');
        }

        $status = $data['status'] ?? 'active';
        if (empty($data['cafe_id']) && $status !== 'quarantine') {
            return Result::fail('El café es obligatorio (usa estado "quarantine" si el animal no está asignado a un café)', 'cafe_id_required');
        }

        if (isset($data['age_years'])) {
            $age = (int) $data['age_years'];
            if ($age < 0 || $age > 50) {
                return Result::fail('La edad del animal debe estar entre 0 y 50 años', 'invalid_age');
            }
        }

        try {
            return Result::ok($this->animalRepo->createAnimal($data));
        } catch (Exception $e) {
            return Result::fail('Error al crear animal: ' . $e->getMessage());
        }
    }

    #[Override]
    public function updateAnimal(int $id, array $data): Result
    {
        if (isset($data['age_years'])) {
            $age = (int) $data['age_years'];
            if ($age < 0 || $age > 50) {
                return Result::fail('La edad del animal debe estar entre 0 y 50 años', 'invalid_age');
            }
        }

        try {
            if (!$this->animalRepo->updateAnimal($id, $data)) {
                return Result::fail('Animal no encontrado');
            }

            return Result::ok(true);
        } catch (Exception $e) {
            return Result::fail('Error al actualizar animal: ' . $e->getMessage());
        }
    }

    #[Override]
    public function deleteAnimal(int $id): Result
    {
        try {
            if (!$this->animalRepo->softDeleteAnimal($id)) {
                return Result::fail('Animal no encontrado');
            }

            return Result::ok(true);
        } catch (Exception $e) {
            return Result::fail('Error al eliminar animal: ' . $e->getMessage());
        }
    }

    #[Override]
    public function getDashboardData(): array
    {
        return [
            'animals' => $this->animalRepo->getAnimalsWithCafeInfoOptimized(),
            'stats' => $this->animalRepo->getHealthStatistics(),
            'recent_logs' => $this->healthCheckRepo->getRecentLogs(20),
            'active_incidents' => $this->incidentRepo->getActiveIncidents(),
        ];
    }

    #[Override]
    public function getAnimalsWithCafeInfo(): array
    {
        return $this->getAllAnimals();
    }

    #[Override]
    public function getStatistics(): array
    {
        return $this->animalRepo->getHealthStatistics();
    }

    #[Override]
    public function getRecentLogs(int $limit = 20): array
    {
        return $this->healthCheckRepo->getRecentLogs($limit);
    }

    #[Override]
    public function getActiveIncidents(): array
    {
        return $this->incidentRepo->getActiveIncidents();
    }

    #[Override]
    public function getIncidentById(int $id): ?array
    {
        return $this->incidentRepo->findById($id)?->toViewArray();
    }

    #[Override]
    public function createCareLog(array $data): Result
    {
        $validation = $this->validateCareLogData($data);
        if ($validation->error !== null) {
            return $validation;
        }

        try {
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

            $logId = $this->healthCheckRepo->createCareLog([
                'animal_id' => $data['animal_id'],
                'logged_by_user_id' => $data['logged_by_user_id'] ?? 1,
                'notes' => $fullNotes,
            ]);

            return Result::ok($logId);
        } catch (Exception $e) {
            return Result::fail('Error al registrar chequeo de salud: ' . $e->getMessage());
        }
    }

    #[Override]
    public function updateHealth(int $animalId, string $healthStatus, ?string $notes = null, ?int $userId = null): Result
    {
        $validStatuses = ['healthy', 'monitoring', 'sick', 'recovering', 'quarantine'];
        if (!\in_array($healthStatus, $validStatuses, true)) {
            return Result::fail('Estado de salud inválido');
        }

        $statusMapping = [
            'healthy' => 'active',
            'monitoring' => 'resting',
            'sick' => 'sick',
            'recovering' => 'resting',
            'quarantine' => 'retired',
        ];
        $currentStatus = $statusMapping[$healthStatus];

        try {
            return Database::transaction(function () use ($animalId, $currentStatus, $notes, $userId): Result {
                $this->animalRepo->updateStatus($animalId, $currentStatus);

                if ($notes) {
                    $this->createCareLog([
                        'animal_id' => $animalId,
                        'activity_type' => 'observation',
                        'notes' => $notes,
                        'logged_by_user_id' => $userId,
                    ]);
                }

                return Result::ok(true);
            });
        } catch (Exception $e) {
            return Result::fail('Error al actualizar salud: ' . $e->getMessage());
        }
    }

    #[Override]
    public function toggleActive(int $animalId): Result
    {
        try {
            $result = $this->animalRepo->toggleStatus($animalId);

            if (!$result['found']) {
                return Result::fail('Animal no encontrado');
            }

            $newStatus = $result['current_status'];
            $statusText = $newStatus === 'active' ? 'activado' : 'puesto en descanso';

            return Result::ok([
                'current_status' => $newStatus,
                'message' => "Animal $statusText exitosamente",
            ]);
        } catch (Exception $e) {
            return Result::fail('Error al cambiar estado: ' . $e->getMessage());
        }
    }

    #[Override]
    public function createIncident(array $data): Result
    {
        $validation = $this->validateIncidentData($data);
        if (!$validation->ok) {
            return $validation;
        }

        try {
            return Database::transaction(function () use ($data): Result {
                $incidentId = $this->incidentRepo->create($data);

                if ($data['severity'] === 'critical') {
                    $this->animalRepo->updateStatus($data['animal_id'], 'sick');
                }

                return Result::ok($incidentId);
            });
        } catch (Exception $e) {
            return Result::fail('Error al crear incidente: ' . $e->getMessage());
        }
    }

    #[Override]
    public function resolveIncident(int $incidentId, ?string $resolution = null, ?int $userId = null): Result
    {
        try {
            $this->incidentRepo->resolve($incidentId, $resolution, $userId);

            return Result::ok(true);
        } catch (Exception $e) {
            return Result::fail('Error al resolver incidente: ' . $e->getMessage());
        }
    }

    private function validateCareLogData(array $data): Result
    {
        if (empty($data['animal_id']) || $data['animal_id'] <= 0) {
            return Result::fail('ID de animal inválido');
        }

        if (empty($data['activity_type']) || !CareLogVocabulary::isValidActivityType((string) $data['activity_type'])) {
            return Result::fail('Tipo de actividad inválido');
        }

        if (!empty($data['mood_before']) && !CareLogVocabulary::isValidMood((string) $data['mood_before'])) {
            return Result::fail('Estado de ánimo inicial inválido');
        }

        if (!empty($data['mood_after']) && !CareLogVocabulary::isValidMood((string) $data['mood_after'])) {
            return Result::fail('Estado de ánimo final inválido');
        }

        return Result::ok();
    }

    private function validateIncidentData(array $data): Result
    {
        if (empty($data['animal_id']) || $data['animal_id'] <= 0) {
            return Result::fail('ID de animal inválido');
        }

        if (!empty($data['incident_type']) && !AnimalVocabulary::isValidIncidentType((string) $data['incident_type'])) {
            return Result::fail('Tipo de incidente inválido');
        }

        if (!empty($data['incident_status']) && !AnimalVocabulary::isValidIncidentStatus((string) $data['incident_status'])) {
            return Result::fail('Estado de incidente inválido');
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
