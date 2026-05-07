<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Core\Container;
use App\Core\Http\ResponseFactory;
use App\Http\Controllers\Api\AbstractApiController;
use App\Repositories\Contracts\AnimalRepositoryInterface;
use App\Services\Contracts\AnimalCareServiceInterface;
use App\Services\Contracts\FileStorageServiceInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;

final class KeeperApiController extends AbstractApiController
{
    private AnimalCareServiceInterface $animalCareService;
    private FileStorageServiceInterface $fileStorageService;
    private AnimalRepositoryInterface $animalRepository;

    public function __construct(
        ?ResponseFactory $response = null,
        ?AnimalCareServiceInterface $animalCareService = null,
        ?FileStorageServiceInterface $fileStorageService = null,
        ?AnimalRepositoryInterface $animalRepository = null,
    ) {
        parent::__construct($response ?? new ResponseFactory());
        $this->animalCareService = $animalCareService ?? Container::make(AnimalCareServiceInterface::class);
        $this->fileStorageService = $fileStorageService ?? Container::make(FileStorageServiceInterface::class);
        $this->animalRepository = $animalRepository ?? Container::make(AnimalRepositoryInterface::class);
    }

    /**
     * POST /api/v1/keeper/animals/{id}/photo → 200 (multipart/form-data)
     */
    public function uploadPhoto(ServerRequestInterface $request, int $animalId): ResponseInterface
    {
        $animal = $this->animalRepository->findById($animalId);
        if (!$animal) {
            return $this->notFound("Animal $animalId no encontrado");
        }

        $cafeId = (int) ($request->getAttribute('user')['cafe_id'] ?? 0);
        if ($cafeId && $animal->cafe_id !== $cafeId) {
            return $this->forbidden('No tienes permisos para subir fotos de este animal');
        }

        $files = $request->getUploadedFiles();
        $uploadedFile = $files['photo'] ?? null;

        if (!($uploadedFile instanceof UploadedFileInterface) || $uploadedFile->getError() !== UPLOAD_ERR_OK) {
            return $this->badRequest('No se recibió ningún archivo válido');
        }

        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $mimeType = $uploadedFile->getClientMediaType() ?? '';
        if (!\in_array($mimeType, $allowedMimes, true)) {
            return $this->badRequest('Tipo de archivo no permitido. Solo imágenes JPEG, PNG, WEBP o GIF.');
        }

        $tmpPath = (string) ($uploadedFile->getStream()->getMetadata('uri') ?? '');
        if ($tmpPath === '') {
            return $this->badRequest('No se pudo leer el archivo subido');
        }

        $result = $this->fileStorageService->uploadImage($tmpPath, 'animals', "animal_{$animalId}");
        if (!$result->ok) {
            return $this->badRequest($result->error ?? 'Error al subir archivo');
        }

        $this->animalRepository->updateImageUrl($animalId, $result->data);

        return $this->success(['message' => 'Foto subida correctamente', 'image_url' => $result->data]);
    }

    /**
     * POST /api/v1/keeper/animals/{id}/care-log → 201
     */
    public function createCareLog(ServerRequestInterface $request, int $animalId): ResponseInterface
    {
        $body = (array) $request->getParsedBody();
        $userId = (int) $request->getAttribute('user_id');

        $data = [
            'animal_id' => $animalId,
            'activity_type' => $body['activity_type'] ?? '',
            'notes' => isset($body['notes']) ? \trim((string) $body['notes']) : null,
            'duration_minutes' => isset($body['duration_minutes']) ? (int) $body['duration_minutes'] : null,
            'mood_before' => $body['mood_before'] ?? null,
            'mood_after' => $body['mood_after'] ?? null,
            'logged_by_user_id' => $userId ?: null,
        ];

        $result = $this->animalCareService->createCareLog($data);
        if (!$result->ok) {
            return $this->unprocessable($result->error ?? 'Error al registrar el log');
        }

        $logId = \is_int($result->data) ? $result->data : null;

        return $this->created(['message' => 'Log registrado correctamente', 'log_id' => $logId]);
    }

    /**
     * PATCH /api/v1/keeper/animals/{id}/health → 200
     */
    public function updateHealth(ServerRequestInterface $request, int $animalId): ResponseInterface
    {
        $body = (array) $request->getParsedBody();
        $healthStatus = (string) ($body['health_status'] ?? '');
        $notes = isset($body['notes']) ? \trim((string) $body['notes']) : null;
        $userId = (int) $request->getAttribute('user_id');

        $result = $this->animalCareService->updateHealth($animalId, $healthStatus, $notes, $userId ?: null);
        if (!$result->ok) {
            return $this->unprocessable($result->error ?? 'Error al actualizar el estado');
        }

        return $this->success(['message' => 'Estado actualizado correctamente', 'health_status' => $healthStatus]);
    }

    /**
     * PATCH /api/v1/keeper/animals/{id}/toggle → 200
     */
    public function toggleActive(ServerRequestInterface $request, int $animalId): ResponseInterface
    {
        $result = $this->animalCareService->toggleActive($animalId);
        if (!$result->ok) {
            return $this->unprocessable($result->error ?? 'Error al actualizar el estado');
        }

        return $this->success(['message' => \is_string($result->data) ? $result->data : 'Estado actualizado correctamente']);
    }

    /**
     * POST /api/v1/keeper/incidents → 201
     */
    public function createIncident(ServerRequestInterface $request): ResponseInterface
    {
        $body = (array) $request->getParsedBody();
        $userId = (int) $request->getAttribute('user_id');

        $data = [
            'animal_id' => isset($body['animal_id']) ? (int) $body['animal_id'] : 0,
            'severity' => $body['severity'] ?? '',
            'description' => isset($body['description']) ? \trim((string) $body['description']) : '',
            'reported_by_user_id' => $userId ?: null,
        ];

        $result = $this->animalCareService->createIncident($data);
        if (!$result->ok) {
            return $this->unprocessable($result->error ?? 'Error al reportar incidente');
        }

        $incidentId = \is_int($result->data) ? $result->data : null;

        return $this->created(['message' => 'Incidente reportado correctamente', 'incident_id' => $incidentId]);
    }

    /**
     * PATCH /api/v1/keeper/incidents/{id}/resolve → 200
     */
    public function resolveIncident(ServerRequestInterface $request, int $incidentId): ResponseInterface
    {
        $body = (array) $request->getParsedBody();
        $resolution = isset($body['resolution']) ? \trim((string) $body['resolution']) : null;
        $userId = (int) $request->getAttribute('user_id');

        $result = $this->animalCareService->resolveIncident($incidentId, $resolution, $userId ?: null);
        if (!$result->ok) {
            return $this->unprocessable($result->error ?? 'Error al resolver incidente');
        }

        return $this->success(['message' => \is_string($result->data) ? $result->data : 'Incidente resuelto correctamente']);
    }
}
