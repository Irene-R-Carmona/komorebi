<?php

declare(strict_types=1);

namespace App\Http\Controllers\Keeper;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Http\ResponseFactory;
use App\Core\View;
use App\Repositories\Contracts\AnimalRepositoryInterface;
use App\Services\Contracts\AnimalCareServiceInterface;
use App\Services\Contracts\HealthCheckServiceInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Controlador de Dashboard de Bienestar Animal — Vistas de lectura.
 *
 * Responsabilidades: dashboard general, listado y detalle de animales.
 */
final class AnimalDashboardController
{
    private AnimalCareServiceInterface $animalCareService;
    private HealthCheckServiceInterface $healthCheckService;
    private AnimalRepositoryInterface $animalRepository;
    private ResponseFactory $response;

    public function __construct(
        ?AnimalCareServiceInterface $animalCareService = null,
        ?HealthCheckServiceInterface $healthCheckService = null,
        ?AnimalRepositoryInterface $animalRepository = null,
        ?ResponseFactory $response = null,
    ) {
        $this->animalCareService  = $animalCareService ?? Container::make(AnimalCareServiceInterface::class);
        $this->healthCheckService = $healthCheckService ?? Container::make(HealthCheckServiceInterface::class);
        $this->animalRepository   = $animalRepository ?? Container::make(AnimalRepositoryInterface::class);
        $this->response           = $response ?? new ResponseFactory();
    }

    /**
     * GET /keeper/dashboard
     * Dashboard de bienestar animal con logs recientes
     */
    public function dashboard(ServerRequestInterface $request): ?ResponseInterface
    {
        $data = $this->animalCareService->getDashboardData();
        $healthCheckData = $this->healthCheckService->getTodayDashboard();
        $activeAlerts = $this->healthCheckService->getActiveAlerts(7);

        View::render('backoffice/keeper/dashboard', [
            'titulo' => 'Dashboard - Bienestar Animal',
            'animals' => $data['animals'],
            'stats' => $data['stats'],
            'recent_logs' => $data['recent_logs'],
            'active_incidents' => $data['active_incidents'],
            'pending_checks_count' => $healthCheckData['pending_count'],
            'pending_animals' => $healthCheckData['pending'],
            'active_alerts' => $activeAlerts,
            'csrf_token' => Csrf::token(),
        ], [], 'backoffice');

        return null;
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
}
