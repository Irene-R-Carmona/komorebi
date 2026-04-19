<?php

declare(strict_types=1);

namespace App\Http\Controllers\Keeper;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Http\ResponseFactory;
use App\Core\Session;
use App\Exceptions\ValidationException;
use App\Services\Contracts\AnimalCareServiceInterface;
use App\Services\Contracts\HealthCheckServiceInterface;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Random\RandomException;

/**
 * Controlador de Cuidado Animal — Mutaciones y acciones de cuidado.
 *
 * Responsabilidades: registrar logs, actualizar salud, toggle, fotos,
 * incidentes, alimentación y controles de salud.
 */
final class AnimalCareController
{
    private AnimalCareServiceInterface $animalCareService;
    private HealthCheckServiceInterface $healthCheckService;
    private ResponseFactory $response;

    public function __construct(
        ?AnimalCareServiceInterface $animalCareService = null,
        ?HealthCheckServiceInterface $healthCheckService = null,
        ?ResponseFactory $response = null,
    ) {
        $this->animalCareService  = $animalCareService ?? Container::make(AnimalCareServiceInterface::class);
        $this->healthCheckService = $healthCheckService ?? Container::make(HealthCheckServiceInterface::class);
        $this->response           = $response ?? new ResponseFactory();
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
            'notes' => isset($body['notes']) ? \trim((string) $body['notes']) : null,
            'duration_minutes' => isset($body['duration_minutes']) ? (int) $body['duration_minutes'] : null,
            'mood_before' => $body['mood_before'] ?? null,
            'mood_after' => $body['mood_after'] ?? null,
            'logged_by_user_id' => $userId,
        ];

        $result = $this->animalCareService->createCareLog($data);

        if (!$result->ok) {
            Flash::error($result->error ?? 'Error al registrar alimentación');

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
            Flash::error($result->error ?? 'Error al registrar control de salud');

            return $this->response->redirect('/keeper/animals/' . $id);
        }

        Flash::success('Control de salud registrado.');

        return $this->response->redirect('/keeper/animals/' . $id);
    }
}
