<?php

declare(strict_types=1);

namespace App\Http\Controllers\Keeper;

use App\Core\Csrf;
use App\Core\Database;
use App\Core\Flash;
use App\Core\Http\ResponseFactory;
use App\Core\Session;
use App\Exceptions\ValidationException;
use App\Repositories\AnimalRepository;
use App\Repositories\HealthCheckRepository;
use App\Services\AnimalCareService;
use App\Services\HealthCheckService;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Random\RandomException;
use function trim;

/**
 * Controlador de Cuidado Animal — Mutaciones y acciones de cuidado.
 *
 * Responsabilidades: registrar logs, actualizar salud, toggle, fotos,
 * incidentes, alimentación y controles de salud.
 */
final class AnimalCareController
{
    private AnimalCareService $animalCareService;
    private HealthCheckService $healthCheckService;
    private ResponseFactory $response;

    public function __construct(
        ?AnimalCareService $animalCareService = null,
        ?HealthCheckService $healthCheckService = null,
        ?ResponseFactory $response = null,
    ) {
        $this->response = $response ?? new ResponseFactory();

        if ($animalCareService !== null && $healthCheckService !== null) {
            $this->animalCareService = $animalCareService;
            $this->healthCheckService = $healthCheckService;

            return;
        }

        $db = Database::getConnection();
        $animalRepo = new AnimalRepository($db);

        $this->animalCareService = $animalCareService ?? new AnimalCareService($db, $animalRepo);
        $this->healthCheckService = $healthCheckService ?? new HealthCheckService(
            new HealthCheckRepository($db)
        );
    }

    /**
     * POST /keeper/animals/{id}/toggle — Activar/desactivar animal
     * @throws ValidationException
     * @throws JsonException
     * @throws RandomException
     */
    public function toggleActive(ServerRequestInterface $request, int $animalId): ResponseInterface
    {
        if (!Csrf::validate($request)) {
            throw ValidationException::withMessage('Token de seguridad inválido', 419);
        }

        $result = $this->animalCareService->toggleActive($animalId);

        if ($result->ok) {
            return $this->response->json(['ok' => true, 'data' => ['message' => $result->data ?? 'Estado actualizado correctamente']]);
        }

        throw ValidationException::withMessage($result->error ?? 'Error al actualizar el estado', 422);
    }

    // [Métodos legacy eliminados 2026-04-16: logCare, updateHealth, uploadPhoto, createIncident, resolveIncident, update, toggle]

    /**
     * POST /keeper/animals/{id}/feeding
     * Registrar una alimentación para un animal
     */
    public function recordFeeding(ServerRequestInterface $request): ResponseInterface
    {
        $id = (int) $request->getAttribute('id');
        $body = (array) $request->getParsedBody();

        $user = Session::user();
        $userId = $user ? (int) $user['id'] : null;

        $data = [
            'animal_id' => $id,
            'activity_type' => 'feeding',
            'notes' => isset($body['notes']) ? trim((string) $body['notes']) : null,
            'duration_minutes' => isset($body['duration_minutes']) ? (int) $body['duration_minutes'] : null,
            'mood_before' => $body['mood_before'] ?? null,
            'mood_after' => $body['mood_after'] ?? null,
            'logged_by_user_id' => $userId,
        ];

        $result = $this->animalCareService->createCareLog($data);

        if (!$result->ok) {
            Flash::error($result->getMessage());

            return $this->response->redirect('/keeper/animals/' . $id);
        }

        Flash::success('Alimentación registrada.');

        return $this->response->redirect('/keeper/animals/' . $id);
    }

    /**
     * POST /keeper/animals/{id}/health
     * Registrar un control de salud para un animal
     */
    public function recordHealth(ServerRequestInterface $request): ResponseInterface
    {
        $id = (int) $request->getAttribute('id');
        $body = (array) $request->getParsedBody();

        $user = Session::user();
        $keeperId = $user ? (int) $user['id'] : 0;

        $result = $this->healthCheckService->createHealthCheck($id, $keeperId, $body);

        if (!$result->ok) {
            Flash::error($result->getMessage());

            return $this->response->redirect('/keeper/animals/' . $id);
        }

        Flash::success('Control de salud registrado.');

        return $this->response->redirect('/keeper/animals/' . $id);
    }
}
