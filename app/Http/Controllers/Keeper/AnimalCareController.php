<?php

declare(strict_types=1);

namespace App\Http\Controllers\Keeper;

use App\Core\Csrf;
use App\Core\Database;
use App\Core\Flash;
use App\Core\Http\ResponseFactory;
use App\Core\Session;
use App\Exceptions\DatabaseException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Models\Animal;
use App\Repositories\AnimalRepository;
use App\Repositories\Contracts\AnimalRepositoryInterface;
use App\Repositories\HealthCheckRepository;
use App\Services\AnimalCareService;
use App\Services\FileUploadService;
use App\Services\HealthCheckService;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Random\RandomException;

/**
 * Controlador de Cuidado Animal — Mutaciones y acciones de cuidado.
 *
 * Responsabilidades: registrar logs, actualizar salud, toggle, fotos,
 * incidentes, alimentación y controles de salud.
 */
final class AnimalCareController
{
    private AnimalCareService $animalCareService;
    private FileUploadService $fileUploadService;
    private HealthCheckService $healthCheckService;
    private AnimalRepositoryInterface $animalRepository;
    private ResponseFactory $response;

    public function __construct(
        ?AnimalCareService $animalCareService = null,
        ?FileUploadService $fileUploadService = null,
        ?HealthCheckService $healthCheckService = null,
        ?AnimalRepositoryInterface $animalRepository = null,
        ?ResponseFactory $response = null,
    ) {
        $db = Database::getConnection();
        $this->animalCareService  = $animalCareService ?? new AnimalCareService();
        $this->fileUploadService  = $fileUploadService ?? new FileUploadService();
        $this->healthCheckService = $healthCheckService ?? new HealthCheckService(
            new HealthCheckRepository($db)
        );
        $this->animalRepository   = $animalRepository ?? new AnimalRepository($db);
        $this->response           = $response ?? new ResponseFactory();
    }

    // TODO(plan7-cleanup): método legacy sin ruta activa — eliminar en fase2-psr7-migration
    /**
     * POST /keeper/log
     * Registrar log de cuidado de animal
     * @throws JsonException
     * @throws RandomException
     * @throws ValidationException
     */
    public function logCare(ServerRequestInterface $request): ResponseInterface
    {
        if (!Csrf::validate($request)) {
            throw ValidationException::withMessage('Token de seguridad inválido', 419);
        }

        $user   = Session::user();
        $userId = $user ? (int) $user['id'] : null;

        $body = (array) $request->getParsedBody();

        $data = [
            'animal_id'         => isset($body['animal_id']) ? (int) $body['animal_id'] : 0,
            'activity_type'     => $body['activity_type'] ?? '',
            'notes'             => isset($body['notes']) ? \trim((string) $body['notes']) : null,
            'duration_minutes'  => isset($body['duration_minutes']) ? (int) $body['duration_minutes'] : null,
            'mood_before'       => $body['mood_before'] ?? null,
            'mood_after'        => $body['mood_after'] ?? null,
            'logged_by_user_id' => $userId,
        ];

        $result = $this->animalCareService->createCareLog($data);

        if ($result->ok) {
            return $this->response->json(['ok' => true, 'data' => [
                'message' => \is_string($result->data) ? $result->data : 'Log registrado correctamente',
                'log_id'  => \is_int($result->data) ? $result->data : null,
            ]]);
        }

        throw ValidationException::withMessage($result->error ?? 'Error al registrar el log', 422);
    }

    // TODO(plan7-cleanup): método legacy sin ruta activa — eliminar en fase2-psr7-migration
    /**
     * POST /keeper/animal/{animalId}/health
     * Actualizar estado de salud de animal
     * @throws JsonException
     * @throws RandomException
     * @throws ValidationException
     */
    public function updateHealth(ServerRequestInterface $request, int $animalId): ResponseInterface
    {
        if (!Csrf::validate($request)) {
            throw ValidationException::withMessage('Token de seguridad inválido', 419);
        }

        $body         = (array) $request->getParsedBody();
        $healthStatus = (string) ($body['health_status'] ?? '');
        $notes        = isset($body['notes']) ? \trim((string) $body['notes']) : null;

        $user   = Session::user();
        $userId = $user ? (int) $user['id'] : null;

        $result = $this->animalCareService->updateHealth($animalId, $healthStatus, $notes, $userId);

        if ($result->ok) {
            return $this->response->json(['ok' => true, 'data' => [
                'message'       => 'Estado actualizado correctamente',
                'health_status' => $healthStatus,
            ]]);
        }

        throw ValidationException::withMessage($result->error ?? 'Error al actualizar el estado', 422);
    }

    // TODO(plan7-cleanup): método legacy sin ruta activa — eliminar en fase2-psr7-migration
    /**
     * POST /keeper/animal/{animalId}/toggle
     * Activar/desactivar animal (disponible para interacción)
     * @throws JsonException
     * @throws RandomException
     * @throws ValidationException
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

    // TODO(plan7-cleanup): método legacy sin ruta activa — eliminar en fase2-psr7-migration
    /**
     * POST /keeper/animal/{animalId}/upload-photo
     * Subir foto de un animal
     * @throws DatabaseException
     * @throws JsonException
     * @throws NotFoundException
     * @throws RandomException
     * @throws ValidationException
     */
    public function uploadPhoto(ServerRequestInterface $request, int $animalId): ResponseInterface
    {
        if (!Csrf::validate($request)) {
            throw ValidationException::withMessage('Token de seguridad inválido', 419);
        }

        $animalModel = new Animal(Database::getConnection());
        $animal      = $animalModel->findById($animalId);
        if (!$animal) {
            throw NotFoundException::forResource('Animal', $animalId);
        }

        $user = Session::user();
        if (!$user || !isset($user['cafe_id']) || $animal['cafe_id'] !== (int) $user['cafe_id']) {
            throw ValidationException::withMessage('No tienes permisos para subir fotos de este animal', 403);
        }

        $files        = $request->getUploadedFiles();
        $uploadedFile = $files['photo'] ?? null;

        if (!($uploadedFile instanceof UploadedFileInterface) || $uploadedFile->getError() !== UPLOAD_ERR_OK) {
            throw ValidationException::withMessage('No se recibió ningún archivo', 400);
        }

        // Adapter: FileUploadService espera array con claves error/tmp_name/name/size/type
        $fileArray = [
            'error'    => $uploadedFile->getError(),
            'tmp_name' => (string) ($uploadedFile->getStream()->getMetadata('uri') ?? ''),
            'name'     => $uploadedFile->getClientFilename() ?? '',
            'size'     => $uploadedFile->getSize() ?? 0,
            'type'     => $uploadedFile->getClientMediaType() ?? '',
        ];

        $result = $this->fileUploadService->uploadAnimalPhoto($fileArray, $animalId);

        if (!$result->ok) {
            throw ValidationException::withMessage($result->error ?? 'Error al subir archivo', 400);
        }

        if (!$this->animalRepository->updateImageUrl($animalId, $result->data)) {
            $this->fileUploadService->deleteFile($result->data);
            throw new DatabaseException('No se pudo actualizar la URL de la imagen');
        }

        return $this->response->json(['ok' => true, 'data' => [
            'message'   => 'Foto subida correctamente',
            'image_url' => $result->data,
        ]]);
    }

    // TODO(plan7-cleanup): método legacy sin ruta activa — eliminar en fase2-psr7-migration
    /**
     * POST /keeper/incident
     * Reportar incidente de un animal
     * @throws JsonException
     * @throws RandomException
     * @throws ValidationException
     */
    public function createIncident(ServerRequestInterface $request): ResponseInterface
    {
        if (!Csrf::validate($request)) {
            throw ValidationException::withMessage('Token de seguridad inválido', 419);
        }

        $user   = Session::user();
        $userId = $user ? (int) $user['id'] : null;

        $body = (array) $request->getParsedBody();

        $data = [
            'animal_id'           => isset($body['animal_id']) ? (int) $body['animal_id'] : 0,
            'severity'            => $body['severity'] ?? '',
            'description'         => isset($body['description']) ? \trim((string) $body['description']) : '',
            'reported_by_user_id' => $userId,
        ];

        $result = $this->animalCareService->createIncident($data);

        if ($result->ok) {
            return $this->response->json(['ok' => true, 'data' => [
                'message'     => \is_string($result->data) ? $result->data : 'Incidente reportado correctamente',
                'incident_id' => \is_int($result->data) ? $result->data : null,
            ]], 201);
        }

        throw ValidationException::withMessage($result->error ?? 'Error al reportar incidente', 422);
    }

    // TODO(plan7-cleanup): método legacy sin ruta activa — eliminar en fase2-psr7-migration
    /**
     * POST /keeper/incident/{incidentId}/resolve
     * Resolver incidente
     * @throws JsonException
     * @throws RandomException
     * @throws ValidationException
     */
    public function resolveIncident(ServerRequestInterface $request, int $incidentId): ResponseInterface
    {
        if (!Csrf::validate($request)) {
            throw ValidationException::withMessage('Token de seguridad inválido', 419);
        }

        $body       = (array) $request->getParsedBody();
        $resolution = isset($body['resolution']) ? \trim((string) $body['resolution']) : null;

        $user   = Session::user();
        $userId = $user ? (int) $user['id'] : null;

        $result = $this->animalCareService->resolveIncident($incidentId, $resolution, $userId);

        if ($result->ok) {
            return $this->response->json(['ok' => true, 'data' => ['message' => $result->data ?? 'Incidente resuelto correctamente']]);
        }

        throw ValidationException::withMessage($result->error ?? 'Error al resolver incidente', 500);
    }

    // TODO(plan7-cleanup): método legacy sin ruta activa — eliminar en fase2-psr7-migration
    /**
     * POST /keeper/log (mantener compatibilidad con método antiguo)
     * @throws JsonException
     * @throws RandomException
     * @throws ValidationException
     */
    public function update(ServerRequestInterface $request): ResponseInterface
    {
        return $this->logCare($request);
    }

    // TODO(plan7-cleanup): método legacy sin ruta activa — eliminar en fase2-psr7-migration
    /**
     * POST /keeper/toggle (mantener compatibilidad con método antiguo)
     * @throws JsonException
     * @throws RandomException
     * @throws ValidationException
     */
    public function toggle(ServerRequestInterface $request): ResponseInterface
    {
        $body     = (array) $request->getParsedBody();
        $animalId = isset($body['animal_id']) ? (int) $body['animal_id'] : 0;
        if ($animalId > 0) {
            return $this->toggleActive($request, $animalId);
        }
        throw ValidationException::withMessage('ID de animal requerido', 422);
    }

    /**
     * POST /keeper/animals/{id}/feeding
     * Registrar una alimentación para un animal
     */
    public function recordFeeding(ServerRequestInterface $request): ResponseInterface
    {
        $id   = (int) $request->getAttribute('id');
        $body = (array) $request->getParsedBody();

        $user   = Session::user();
        $userId = $user ? (int) $user['id'] : null;

        $data = [
            'animal_id'         => $id,
            'activity_type'     => 'feeding',
            'notes'             => isset($body['notes']) ? \trim((string) $body['notes']) : null,
            'duration_minutes'  => isset($body['duration_minutes']) ? (int) $body['duration_minutes'] : null,
            'mood_before'       => $body['mood_before'] ?? null,
            'mood_after'        => $body['mood_after'] ?? null,
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
        $id   = (int) $request->getAttribute('id');
        $body = (array) $request->getParsedBody();

        $user     = Session::user();
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
