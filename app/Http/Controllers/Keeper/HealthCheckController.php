<?php

declare(strict_types=1);

namespace App\Http\Controllers\Keeper;

use App\Core\Csrf;
use App\Core\Database;
use App\Core\Flash;
use App\Core\Session;
use App\Core\View;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Services\HealthCheckService;
use App\Repositories\AnimalRepository;
use App\Repositories\HealthCheckRepository;

/**
 * Controlador de Chequeos de Salud Animal - Sistema de Health Checks Diarios
 *
 * Responsabilidades:
 * - Dashboard de chequeos completados y pendientes
 * - Formularios de checklist interactivo
 * - Registro y visualización de chequeos históricos
 * - Timeline de salud por animal
 */
final class HealthCheckController
{
    private HealthCheckService $healthCheckService;
    private AnimalRepository $animalRepo;

    public function __construct(
        ?HealthCheckService $healthCheckService = null,
        ?AnimalRepository $animalRepo = null,
    ) {
        $db = Database::getConnection();
        $healthCheckRepo = new HealthCheckRepository($db);
        $this->healthCheckService = $healthCheckService ?? new HealthCheckService($healthCheckRepo);
        $this->animalRepo = $animalRepo ?? new AnimalRepository($db);
    }

    /**
     * GET /keeper/health-checks
     * Dashboard: Chequeos de hoy (completados y pendientes) + alertas activas
     */
    public function index(): void
    {
        // Obtener datos del dashboard
        $dashboardData = $this->healthCheckService->getTodayDashboard();
        $activeAlerts = $this->healthCheckService->getActiveAlerts(7);

        View::render('backoffice/keeper/health-checks/index', [
            'titulo' => 'Chequeos de Salud - Dashboard',
            'completed_checks' => $dashboardData['completed'],
            'pending_animals' => $dashboardData['pending'],
            'completed_count' => $dashboardData['completed_count'],
            'pending_count' => $dashboardData['pending_count'],
            'active_alerts' => $activeAlerts,
            'csrf_token' => Csrf::token(),
        ], [], 'backoffice');
    }

    /**
     * GET /keeper/health-checks/create/{animal_id}
     * Formulario de checklist interactivo para un animal específico
     *
     * @param int $animalId ID del animal a chequear
     * @throws NotFoundException Si el animal no existe
     * @throws ValidationException Si ya existe un chequeo hoy
     */
    public function create(int $animalId): void
    {
        // Verificar que no exista ya un chequeo hoy
        if ($this->healthCheckService->hasCheckToday($animalId)) {
            throw ValidationException::withMessage(
                'Ya existe un chequeo registrado hoy para este animal',
                400
            );
        }

        $animal = $this->animalRepo->findById($animalId);

        if ($animal === null) {
            throw new NotFoundException('Animal no encontrado');
        }

        View::render('backoffice/keeper/health-checks/create', [
            'titulo' => 'Nuevo Chequeo de Salud',
            'animal' => $animal,
            'csrf_token' => Csrf::token(),
        ], [], 'backoffice');
    }

    /**
     * POST /keeper/health-checks
     * Procesar y guardar un chequeo de salud
     *
     * @throws ValidationException Si CSRF inválido o datos incorrectos
     */
    public function store(): void
    {
        // Validar CSRF
        if (!Csrf::validate()) {
            throw ValidationException::withMessage('Token de seguridad inválido', 419);
        }

        // Obtener usuario actual (keeper)
        $user = Session::user();
        if ($user === null) {
            throw ValidationException::withMessage('Usuario no autenticado', 401);
        }

        $keeperId = (int) $user['id'];

        // Extraer y sanitizar datos del formulario
        $animalId = (int) ($_POST['animal_id'] ?? 0);
        $checkData = [
            'weight_kg' => !empty($_POST['weight_kg']) ? (float) $_POST['weight_kg'] : null,
            'temperature_c' => !empty($_POST['temperature_c']) ? (float) $_POST['temperature_c'] : null,
            'appetite' => $_POST['appetite'] ?? 'normal',
            'energy_level' => $_POST['energy_level'] ?? 'normal',
            'coat_condition' => $_POST['coat_condition'] ?? 'good',
            'eyes_clear' => isset($_POST['eyes_clear']) && $_POST['eyes_clear'] === '1',
            'breathing_normal' => isset($_POST['breathing_normal']) && $_POST['breathing_normal'] === '1',
            'mobility_normal' => isset($_POST['mobility_normal']) && $_POST['mobility_normal'] === '1',
            'notes' => trim($_POST['notes'] ?? ''),
        ];

        // Validar animal_id
        if ($animalId <= 0) {
            throw ValidationException::withMessage('ID de animal inválido', 400);
        }

        // Crear chequeo mediante el servicio
        $result = $this->healthCheckService->createHealthCheck($animalId, $keeperId, $checkData);

        if (!$result->isOk()) {
            throw ValidationException::withMessage($result->getMessage(), 400);
        }

        // Extraer datos del resultado
        $resultData = $result->data;
        $alerts = $resultData['alerts'] ?? [];

        // Mensaje de éxito diferenciado según si hay alertas
        if (!empty($alerts)) {
            $alertMessage = 'Chequeo registrado con ' . count($alerts) . ' alerta(s) detectada(s)';
            Flash::set('warning', $alertMessage);
            Flash::set('alerts', $alerts);
        } else {
            Flash::set('success', 'Chequeo de salud registrado exitosamente');
        }

        // Redireccionar al dashboard de health checks
        header('Location: /keeper/health-checks');
        exit;
    }

    /**
     * GET /keeper/health-checks/{id}
     * Visualizar un chequeo histórico específico con todos los detalles
     *
     * @param int $checkId ID del chequeo
     * @throws NotFoundException Si el chequeo no existe
     */
    public function show(int $checkId): void
    {
        $check = $this->healthCheckService->getCheckById($checkId);

        if ($check === null) {
            throw new NotFoundException('Chequeo no encontrado');
        }

        View::render('backoffice/keeper/health-checks/show', [
            'titulo' => 'Detalle de Chequeo',
            'check' => $check,
            'csrf_token' => Csrf::token(),
        ], [], 'backoffice');
    }

    /**
     * GET /keeper/health-checks/history/{animal_id}
     * Timeline de chequeos de un animal (últimos 30 por defecto)
     *
     * @param int $animalId ID del animal
     * @throws NotFoundException Si el animal no existe
     */
    public function history(int $animalId): void
    {
        // Obtener límite desde query params (default: 30)
        $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 30;
        $limit = max(1, min($limit, 100)); // Entre 1 y 100

        // Obtener historial desde el servicio
        $history = $this->healthCheckService->getAnimalHistory($animalId, $limit);

        $animal = $this->animalRepo->findById($animalId);

        if ($animal === null) {
            throw new NotFoundException('Animal no encontrado');
        }

        View::render('backoffice/keeper/health-checks/history', [
            'titulo' => 'Historial de Chequeos',
            'animal' => $animal,
            'history' => $history,
            'limit' => $limit,
            'csrf_token' => Csrf::token(),
        ], [], 'backoffice');
    }
}
