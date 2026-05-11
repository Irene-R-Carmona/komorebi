<?php

declare(strict_types=1);

namespace App\Http\Controllers\Keeper;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Http\ResponseFactory;
use App\Core\Session;
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
        $this->animalCareService = $animalCareService ?? Container::make(AnimalCareServiceInterface::class);
        $this->healthCheckService = $healthCheckService ?? Container::make(HealthCheckServiceInterface::class);
        $this->animalRepository = $animalRepository ?? Container::make(AnimalRepositoryInterface::class);
        $this->response = $response ?? new ResponseFactory();
    }

    /**
     * GET /keeper/dashboard
     * Dashboard de bienestar animal con logs recientes
     */
    public function dashboard(ServerRequestInterface $request): ?ResponseInterface
    {
        $cafeId = (int) (Session::user()['cafe_id'] ?? 0);
        $data = $this->animalCareService->getDashboardData($cafeId);
        $healthCheckData = $this->healthCheckService->getTodayDashboard($cafeId);
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
        $cafeId = (int) (Session::user()['cafe_id'] ?? 0);
        $params = $request->getQueryParams();
        $search = \trim((string) ($params['search'] ?? ''));
        $status = (string) ($params['status'] ?? '');
        $species = (string) ($params['species'] ?? '');
        $page = \max(1, (int) ($params['page'] ?? 1));
        $pageSize = 20;

        $all = $this->animalCareService->getAnimalsWithCafeInfo($cafeId);

        $filtered = \array_values(\array_filter($all, static function (array $a) use ($search, $status, $species): bool {
            if ($search !== '' && \stripos($a['name'] . ' ' . ($a['cafe_name'] ?? ''), $search) === false) {
                return false;
            }
            if ($status !== '' && ($a['current_status'] ?? '') !== $status) {
                return false;
            }
            if ($species !== '' && ($a['species_type'] ?? '') !== $species) {
                return false;
            }

            return true;
        }));

        $total = \count($filtered);
        $offset = ($page - 1) * $pageSize;
        $pageItems = \array_slice($filtered, $offset, $pageSize + 1);
        $hasNext = \count($pageItems) > $pageSize;
        $animals = \array_slice($pageItems, 0, $pageSize);
        $meta = ['page' => $page, 'has_next_page' => $hasNext];

        $currentParams = \array_filter(\compact('search', 'status', 'species'));

        View::render('backoffice/keeper/animals/index', [
            'animals' => $animals,
            'meta' => $meta,
            'total' => $total,
            'currentParams' => $currentParams,
            'baseUrl' => '/keeper/animals',
        ], [], 'backoffice');

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

        View::render('backoffice/keeper/animals/show', ['animal' => $animal->toViewArray()], [], 'backoffice');

        return null;
    }
}
