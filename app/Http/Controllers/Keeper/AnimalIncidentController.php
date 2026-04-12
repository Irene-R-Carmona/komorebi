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
use App\Services\AnimalCareService;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Random\RandomException;

/**
 * Controlador de Incidentes de Animales — flujo web (listado, creación, resolución).
 */
final class AnimalIncidentController
{
    private AnimalCareService $service;
    private ResponseFactory $response;
    private AnimalRepository $animalRepository;

    public function __construct(
        ?AnimalCareService $service = null,
        ?ResponseFactory $response = null,
        ?AnimalRepository $animalRepository = null,
    ) {
        $db                     = Database::getConnection();
        $this->animalRepository = $animalRepository ?? new AnimalRepository($db);
        $this->service          = $service ?? new AnimalCareService($db, $this->animalRepository);
        $this->response         = $response ?? new ResponseFactory();
    }

    /**
     * GET /keeper/incidents
     * Listado de incidentes activos.
     */
    public function index(ServerRequestInterface $request): ?ResponseInterface
    {
        $incidents = $this->service->getActiveIncidents();

        View::render('backoffice/keeper/incidents/index', compact('incidents'), [], 'backoffice');

        return null;
    }

    /**
     * GET /keeper/incidents/create
     * Formulario para reportar un nuevo incidente.
     */
    public function create(ServerRequestInterface $request): ?ResponseInterface
    {
        $user    = Session::user();
        $cafeId  = (int) ($user['cafe_id'] ?? 0);
        $animals = $cafeId > 0 ? $this->animalRepository->findActiveByCafe($cafeId) : [];

        View::render('backoffice/keeper/incidents/create', compact('animals'), [], 'backoffice');

        return null;
    }

    /**
     * POST /keeper/incidents
     * Almacenar un nuevo incidente.
     *
     * @throws ValidationException
     * @throws JsonException
     * @throws RandomException
     */
    public function store(ServerRequestInterface $request): ResponseInterface
    {
        if (!Csrf::validate($request)) {
            throw ValidationException::withMessage('Token de seguridad inválido', 419);
        }

        $body = (array) $request->getParsedBody();
        $user = Session::user();

        $data = [
            'animal_id'           => isset($body['animal_id']) ? (int) $body['animal_id'] : 0,
            'severity'            => $body['severity'] ?? '',
            'description'         => isset($body['description']) ? \trim((string) $body['description']) : '',
            'reported_by_user_id' => $user ? (int) $user['id'] : null,
        ];

        $result = $this->service->createIncident($data);

        if ($result->ok) {
            Flash::success('Incidente reportado correctamente.');
        } else {
            Flash::error($result->getMessage());
        }

        return $this->response->redirect('/keeper/incidents');
    }

    /**
     * GET /keeper/incidents/{id}
     * Detalle de un incidente con formulario de resolución.
     *
     * @throws NotFoundException
     */
    public function show(ServerRequestInterface $request, int $id): ?ResponseInterface
    {
        $incident = $this->service->getIncidentById($id);

        if ($incident === null) {
            throw NotFoundException::forResource('Incidente', $id);
        }

        View::render('backoffice/keeper/incidents/show', compact('incident'), [], 'backoffice');

        return null;
    }

    /**
     * POST /keeper/incidents/{incidentId}/resolve
     * Marcar un incidente como resuelto.
     *
     * @throws ValidationException
     * @throws JsonException
     * @throws RandomException
     */
    public function resolve(ServerRequestInterface $request, int $incidentId): ResponseInterface
    {
        if (!Csrf::validate($request)) {
            throw ValidationException::withMessage('Token de seguridad inválido', 419);
        }

        $body       = (array) $request->getParsedBody();
        $resolution = isset($body['resolution']) ? \trim((string) $body['resolution']) : null;

        $user   = Session::user();
        $userId = $user ? (int) $user['id'] : null;

        $result = $this->service->resolveIncident($incidentId, $resolution, $userId);

        if ($result->ok) {
            Flash::success('Incidente resuelto correctamente.');
        } else {
            Flash::error($result->getMessage());
        }

        return $this->response->redirect('/keeper/incidents');
    }
}
