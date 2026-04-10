<?php

declare(strict_types=1);

namespace App\Http\Controllers\Keeper;

use App\Core\Csrf;
use App\Core\Database;
use App\Core\Flash;
use App\Core\Http\ResponseFactory;
use App\Core\Session;
use App\Core\View;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Repositories\AnimalRepository;
use App\Repositories\Contracts\AnimalRepositoryInterface;
use App\Repositories\HealthCheckRepository;
use App\Services\HealthCheckService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

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
    private AnimalRepositoryInterface $animalRepo;
    private ResponseFactory $response;

    public function __construct(
        ?HealthCheckService $healthCheckService = null,
        ?AnimalRepositoryInterface $animalRepo = null,
        ?ResponseFactory $response = null,
    ) {
        $db = Database::getConnection();
        $healthCheckRepo = new HealthCheckRepository($db);
        $this->healthCheckService = $healthCheckService ?? new HealthCheckService($healthCheckRepo);
        $this->animalRepo         = $animalRepo ?? new AnimalRepository($db);
        $this->response           = $response ?? new ResponseFactory();
    }

    /**
     * GET /keeper/health-checks
     * Dashboard: Chequeos de hoy (completados y pendientes) + alertas activas
     */
    public function index(ServerRequestInterface $request): ?ResponseInterface
    {
        $dashboardData = $this->healthCheckService->getTodayDashboard();
        $activeAlerts  = $this->healthCheckService->getActiveAlerts(7);

        View::render('backoffice/keeper/health-checks/index', [
            'titulo'           => 'Chequeos de Salud - Dashboard',
            'completed_checks' => $dashboardData['completed'],
            'pending_animals'  => $dashboardData['pending'],
            'completed_count'  => $dashboardData['completed_count'],
            'pending_count'    => $dashboardData['pending_count'],
            'active_alerts'    => $activeAlerts,
            'csrf_token'       => Csrf::token(),
        ], [], 'backoffice');

        return null;
    }

    /**
     * GET /keeper/health-checks/create/{animalId}
     * Formulario de checklist interactivo para un animal específico
     *
     * @throws NotFoundException Si el animal no existe
     * @throws ValidationException Si ya existe un chequeo hoy
     */
    public function create(ServerRequestInterface $request, int $animalId): ?ResponseInterface
    {
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
            'titulo'     => 'Nuevo Chequeo de Salud',
            'animal'     => $animal,
            'csrf_token' => Csrf::token(),
        ], [], 'backoffice');

        return null;
    }

    /**
     * POST /keeper/health-checks
     * Procesar y guardar un chequeo de salud
     *
     * @throws ValidationException Si CSRF inválido o datos incorrectos
     */
    public function store(ServerRequestInterface $request): ResponseInterface
    {
        if (!Csrf::validate($request)) {
            throw ValidationException::withMessage('Token de seguridad inválido', 419);
        }

        $user = Session::user();
        if ($user === null) {
            throw ValidationException::withMessage('Usuario no autenticado', 401);
        }

        $keeperId = (int) $user['id'];

        $body = (array) $request->getParsedBody();

        $animalId  = isset($body['animal_id']) ? (int) $body['animal_id'] : 0;
        $checkData = [
            'weight_kg'        => !empty($body['weight_kg']) ? (float) $body['weight_kg'] : null,
            'temperature_c'    => !empty($body['temperature_c']) ? (float) $body['temperature_c'] : null,
            'appetite'         => $body['appetite'] ?? 'normal',
            'energy_level'     => $body['energy_level'] ?? 'normal',
            'coat_condition'   => $body['coat_condition'] ?? 'good',
            'eyes_clear'       => isset($body['eyes_clear']) && $body['eyes_clear'] === '1',
            'breathing_normal' => isset($body['breathing_normal']) && $body['breathing_normal'] === '1',
            'mobility_normal'  => isset($body['mobility_normal']) && $body['mobility_normal'] === '1',
            'notes'            => \trim($body['notes'] ?? ''),
        ];

        if ($animalId <= 0) {
            throw ValidationException::withMessage('ID de animal inválido', 400);
        }

        $result = $this->healthCheckService->createHealthCheck($animalId, $keeperId, $checkData);

        if (!$result->isOk()) {
            throw ValidationException::withMessage($result->getMessage(), 400);
        }

        $alerts = $result->data['alerts'] ?? [];

        if (!empty($alerts)) {
            Flash::warning('Chequeo registrado con ' . \count($alerts) . ' alerta(s) detectada(s)');
        } else {
            Flash::success('Chequeo de salud registrado exitosamente');
        }

        return $this->response->redirect('/keeper/health-checks');
    }

    /**
     * GET /keeper/health-checks/{checkId}
     * Visualizar un chequeo histórico específico con todos los detalles
     *
     * @throws NotFoundException Si el chequeo no existe
     */
    public function show(ServerRequestInterface $request, int $checkId): ?ResponseInterface
    {
        $check = $this->healthCheckService->getCheckById($checkId);

        if ($check === null) {
            throw new NotFoundException('Chequeo no encontrado');
        }

        View::render('backoffice/keeper/health-checks/show', [
            'titulo'     => 'Detalle de Chequeo',
            'check'      => $check,
            'csrf_token' => Csrf::token(),
        ], [], 'backoffice');

        return null;
    }

    /**
     * GET /keeper/health-checks/history/{animalId}
     * Timeline de chequeos de un animal (últimos 30 por defecto)
     *
     * @throws NotFoundException Si el animal no existe
     */
    public function history(ServerRequestInterface $request, int $animalId): ?ResponseInterface
    {
        $queryParams = $request->getQueryParams();
        $limit       = isset($queryParams['limit']) ? (int) $queryParams['limit'] : 30;
        $limit       = \max(1, \min($limit, 100));

        $history = $this->healthCheckService->getAnimalHistory($animalId, $limit);
        $animal  = $this->animalRepo->findById($animalId);

        if ($animal === null) {
            throw new NotFoundException('Animal no encontrado');
        }

        View::render('backoffice/keeper/health-checks/history', [
            'titulo'     => 'Historial de Chequeos',
            'animal'     => $animal,
            'history'    => $history,
            'limit'      => $limit,
            'csrf_token' => Csrf::token(),
        ], [], 'backoffice');

        return null;
    }
}
