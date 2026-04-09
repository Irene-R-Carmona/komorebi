<?php

declare(strict_types=1);

namespace App\Http\Controllers\Keeper;

use App\Core\Csrf;
use App\Core\Database;
use App\Core\Session;
use App\Core\View;
use App\Exceptions\DatabaseException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Models\Animal;
use App\Services\AnimalCareService;
use App\Services\FileUploadService;
use App\Services\HealthCheckService;
use App\Core\Flash;
use App\Core\Http\ResponseFactory;
use App\Repositories\AnimalRepository;
use App\Repositories\HealthCheckRepository;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Random\RandomException;

/**
 * Controlador de Bienestar Animal - Gestión y Logs de Animales
 *
 * Responsabilidades:
 * - Manejo de peticiones HTTP para dashboard de bienestar
 * - Validación de CSRF y permisos
 * - Renderizado de vistas y respuestas JSON
 * - Delegación de lógica de negocio a AnimalCareService
 */
final class AnimalController
{
    private AnimalCareService $animalCareService;
    private FileUploadService $fileUploadService;
    private HealthCheckService $healthCheckService;
    private ResponseFactory $response;
    private AnimalRepository $animalRepository;

    public function __construct()
    {
        $this->animalCareService = new AnimalCareService();
        $this->fileUploadService = new FileUploadService();

        // Instanciar HealthCheckService
        $db = Database::getConnection();
        $healthCheckRepo = new HealthCheckRepository($db);
        $this->healthCheckService = new HealthCheckService($healthCheckRepo);
        $this->response = new ResponseFactory();
        $this->animalRepository = new AnimalRepository($db);
    }

    /**
     * GET /keeper/dashboard
     * Dashboard de bienestar animal con logs recientes
     */
    public function dashboard(): void
    {
        // Obtener datos del servicio existente
        $data = $this->animalCareService->getDashboardData();

        // Obtener datos de health checks
        $healthCheckData = $this->healthCheckService->getTodayDashboard();
        $activeAlerts = $this->healthCheckService->getActiveAlerts(7);

        View::render('backoffice/keeper/dashboard', [
            'titulo' => 'Dashboard - Bienestar Animal',
            'animals' => $data['animals'],
            'stats' => $data['stats'],
            'recent_logs' => $data['recent_logs'],
            'active_incidents' => $data['active_incidents'],
            // Health checks data
            'pending_checks_count' => $healthCheckData['pending_count'],
            'pending_animals' => $healthCheckData['pending'],
            'active_alerts' => $activeAlerts,
            'csrf_token' => Csrf::token(),
        ], [], 'backoffice');
    }

    /**
     * POST /keeper/log
     * Registrar log de cuidado de animal
     * @throws JsonException
     * @throws RandomException
     * @throws ValidationException
     */
    public function logCare(): ResponseInterface
    {
        // Validar CSRF
        if (!Csrf::validate()) {
            throw ValidationException::withMessage('Token de seguridad inválido', 419);
        }

        // Obtener usuario actual
        $user = Session::user();
        $userId = $user ? (int) $user['id'] : null;

        // Preparar datos para el servicio
        $data = [
            'animal_id' => isset($_POST['animal_id']) ? (int) $_POST['animal_id'] : 0,
            'activity_type' => $_POST['activity_type'] ?? '',
            'notes' => isset($_POST['notes']) ? \trim($_POST['notes']) : null,
            'duration_minutes' => isset($_POST['duration_minutes']) ? (int) $_POST['duration_minutes'] : null,
            'mood_before' => $_POST['mood_before'] ?? null,
            'mood_after' => $_POST['mood_after'] ?? null,
            'logged_by_user_id' => $userId,
        ];

        // Delegar al servicio
        $result = $this->animalCareService->createCareLog($data);

        if ($result->ok) {
            return $this->response->json(['ok' => true, 'data' => [
                'message' => \is_string($result->data) ? $result->data : 'Log registrado correctamente',
                'log_id' => \is_int($result->data) ? $result->data : null,
            ]]);
        }

        throw ValidationException::withMessage($result->error ?? 'Error al registrar el log', 422);
    }

    /**
     * POST /keeper/animal/{animalId}/health
     * Actualizar estado de salud de animal
     * @param integer $animalId
     * @throws JsonException
     * @throws RandomException
     * @throws ValidationException
     */
    public function updateHealth(int $animalId): ResponseInterface
    {
        // Validar CSRF
        if (!Csrf::validate()) {
            throw ValidationException::withMessage('Token de seguridad inválido', 419);
        }

        $healthStatus = $_POST['health_status'] ?? '';
        $notes = isset($_POST['notes']) ? \trim($_POST['notes']) : null;

        $user = Session::user();
        $userId = $user ? (int) $user['id'] : null;

        // Delegar al servicio
        $result = $this->animalCareService->updateHealth($animalId, $healthStatus, $notes, $userId);

        if ($result->ok) {
            return $this->response->json(['ok' => true, 'data' => [
                'message' => 'Estado actualizado correctamente',
                'health_status' => $healthStatus,
            ]]);
        }

        throw ValidationException::withMessage($result->error ?? 'Error al actualizar el estado', 422);
    }

    /**
     * POST /keeper/animal/{animalId}/toggle
     * Activar/desactivar animal (disponible para interacción)
     * @param integer $animalId
     * @throws JsonException
     * @throws RandomException
     * @throws ValidationException
     */
    public function toggleActive(int $animalId): ResponseInterface
    {
        // Validar CSRF
        if (!Csrf::validate()) {
            throw ValidationException::withMessage('Token de seguridad inválido', 419);
        }

        // Delegar al servicio
        $result = $this->animalCareService->toggleActive($animalId);

        if ($result->ok) {
            return $this->response->json(['ok' => true, 'data' => ['message' => $result->data ?? 'Estado actualizado correctamente']]);
        }

        throw ValidationException::withMessage($result->error ?? 'Error al actualizar el estado', 422);
    }

    /**
     * POST /keeper/animal/{animalId}/upload-photo
     * Subir foto de un animal
     * @param integer $animalId
     * @throws DatabaseException
     * @throws JsonException
     * @throws NotFoundException
     * @throws RandomException
     * @throws ValidationException
     */
    public function uploadPhoto(int $animalId): ResponseInterface
    {
        // Validar CSRF
        if (!Csrf::validate()) {
            throw ValidationException::withMessage('Token de seguridad inválido', 419);
        }

        // Verificar que el animal existe
        $animalModel = new Animal(Database::getConnection());
        $animal = $animalModel->findById($animalId);
        if (!$animal) {
            throw NotFoundException::forResource('Animal', $animalId);
        }

        // Verificar permisos del usuario
        $user = Session::user();
        if (!$user || !isset($user['cafe_id']) || $animal['cafe_id'] !== (int) $user['cafe_id']) {
            throw ValidationException::withMessage('No tienes permisos para subir fotos de este animal', 403);
        }

        // Verificar que se subió un archivo
        if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== 0) {
            throw ValidationException::withMessage('No se recibió ningún archivo', 400);
        }

        // Usar FileUploadService para subir la foto
        $result = $this->fileUploadService->uploadAnimalPhoto($_FILES['photo'], $animalId);

        if (!$result->ok) {
            throw ValidationException::withMessage($result->error ?? 'Error al subir archivo', 400);
        }

        // Actualizar la URL en la base de datos via repositorio
        if (!$this->animalRepository->updateImageUrl($animalId, $result->data)) {
            $this->fileUploadService->deleteFile($result->data);
            throw new DatabaseException('No se pudo actualizar la URL de la imagen');
        }

        return $this->response->json(['ok' => true, 'data' => [
            'message' => 'Foto subida correctamente',
            'image_url' => $result->data,
        ]]);
    }

    /**
     * @throws JsonException
     * @throws RandomException
     * @throws ValidationException
     */
    public function createIncident(): ResponseInterface
    {
        // Validar CSRF
        if (!Csrf::validate()) {
            throw ValidationException::withMessage('Token de seguridad inválido', 419);
        }

        $user = Session::user();
        $userId = $user ? (int) $user['id'] : null;

        // Preparar datos para el servicio
        $data = [
            'animal_id' => isset($_POST['animal_id']) ? (int) $_POST['animal_id'] : 0,
            'severity' => $_POST['severity'] ?? '',
            'description' => isset($_POST['description']) ? \trim($_POST['description']) : '',
            'reported_by_user_id' => $userId,
        ];

        // Delegar al servicio
        $result = $this->animalCareService->createIncident($data);

        if ($result->ok) {
            return $this->response->json(['ok' => true, 'data' => [
                'message' => \is_string($result->data) ? $result->data : 'Incidente reportado correctamente',
                'incident_id' => \is_int($result->data) ? $result->data : null,
            ]], 201);
        }

        throw ValidationException::withMessage($result->error ?? 'Error al reportar incidente', 422);
    }

    /**
     * POST /keeper/incident/{incidentId}/resolve
     * Resolver incidente
     * @param integer $incidentId
     * @throws JsonException
     * @throws RandomException
     * @throws ValidationException
     */
    public function resolveIncident(int $incidentId): ResponseInterface
    {
        // Validar CSRF
        if (!Csrf::validate()) {
            throw ValidationException::withMessage('Token de seguridad inválido', 419);
        }

        $resolution = isset($_POST['resolution']) ? \trim($_POST['resolution']) : null;

        $user = Session::user();
        $userId = $user ? (int) $user['id'] : null;

        // Delegar al servicio
        $result = $this->animalCareService->resolveIncident($incidentId, $resolution, $userId);

        if ($result->ok) {
            return $this->response->json(['ok' => true, 'data' => ['message' => $result->data ?? 'Incidente resuelto correctamente']]);
        }

        throw ValidationException::withMessage($result->error ?? 'Error al resolver incidente', 500);
    }

    /**
     * POST /keeper/log (mantener compatibilidad con método antiguo)
     */
    public function update(): ResponseInterface
    {
        return $this->logCare();
    }

    /**
     * POST /keeper/toggle (mantener compatibilidad con método antiguo)
     * @throws JsonException
     * @throws RandomException
     * @throws ValidationException
     */
    public function toggle(): ResponseInterface
    {
        $animalId = isset($_POST['animal_id']) ? (int) $_POST['animal_id'] : 0;
        if ($animalId > 0) {
            return $this->toggleActive($animalId);
        }
        throw ValidationException::withMessage('ID de animal requerido', 422);
    }

    /**
     * GET /keeper/animals
     * Lista todos los animales
     */
    public function index(ServerRequestInterface $request): ?ResponseInterface
    {
        $animals = $this->animalCareService->getAnimalsWithCafeInfo();
        View::render('backoffice/keeper/animals/index', ['animals' => $animals], [], 'backoffice');
        return null;
    }

    /**
     * GET /keeper/animals/{id}
     * Detalle de un animal
     */
    public function show(ServerRequestInterface $request): ?ResponseInterface
    {
        $id = (int) $request->getAttribute('id');
        $animal = $this->animalRepository->findById($id);

        if ($animal === null) {
            Flash::error('Animal no encontrado.');
            return $this->response->redirect('/keeper/animals');
        }

        View::render('backoffice/keeper/animals/show', ['animal' => $animal], [], 'backoffice');
        return null;
    }

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
