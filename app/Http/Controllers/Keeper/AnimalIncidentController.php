<?php

declare(strict_types=1);

namespace App\Http\Controllers\Keeper;

use App\Core\Csrf;
use App\Core\Database;
use App\Core\Flash;
use App\Core\Http\ResponseFactory;
use App\Core\Session;
use App\Core\View;
use App\Domain\Mappers\AnimalMapper;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Repositories\AnimalRepository;
use App\Repositories\Contracts\AnimalRepositoryInterface;
use App\Services\AnimalCareService;
use App\Services\Contracts\AnimalCareServiceInterface;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Random\RandomException;

/**
 * Controlador de Incidentes de Animales — flujo web (listado, creación, resolución).
 */
final class AnimalIncidentController
{
    private AnimalCareServiceInterface $service;
    private ResponseFactory $response;
    private AnimalRepositoryInterface $animalRepository;

    public function __construct(
        ?AnimalCareServiceInterface $service = null,
        ?ResponseFactory $response = null,
        ?AnimalRepositoryInterface $animalRepository = null,
    ) {
        if ($service === null || $animalRepository === null) {
            $db = Database::getConnection();
            $this->animalRepository = $animalRepository ?? new AnimalRepository(new AnimalMapper(), $db);
            $this->service = $service ?? new AnimalCareService($this->animalRepository);
        } else {
            $this->animalRepository = $animalRepository;
            $this->service = $service;
        }
        $this->response = $response ?? new ResponseFactory();
    }

    /**
     * GET /keeper/incidents
     * Listado de incidentes activos.
     */
    public function index(ServerRequestInterface $request): ?ResponseInterface
    {
        $params   = $request->getQueryParams();
        $search   = \trim((string) ($params['search'] ?? ''));
        $severity = (string) ($params['severity'] ?? '');

        $cafeId = (int) (Session::user()['cafe_id'] ?? 0);
        $all = $this->service->getActiveIncidents($cafeId);

        $incidents = ($search !== '' || $severity !== '')
            ? \array_values(\array_filter($all, static function (array $inc) use ($search, $severity): bool {
                if ($search !== '' && \stripos($inc['animal_name'] . ' ' . ($inc['description'] ?? ''), $search) === false) {
                    return false;
                }
                if ($severity !== '' && ($inc['severity'] ?? '') !== $severity) {
                    return false;
                }
                return true;
            }))
            : $all;

        $currentParams = \array_filter(\compact('search', 'severity'));

        View::render('backoffice/keeper/incidents/index', \compact('incidents', 'currentParams'), [], 'backoffice');

        return null;
    }

    /**
     * GET /keeper/incidents/create
     * Formulario para reportar un nuevo incidente.
     */
    public function create(ServerRequestInterface $request): ?ResponseInterface
    {
        $user = Session::user();
        $cafeId = (int) ($user['cafe_id'] ?? 0);
        $animals = $cafeId > 0 ? $this->animalRepository->findActiveByCafe($cafeId) : [];

        View::render('backoffice/keeper/incidents/create', \compact('animals'), [], 'backoffice');

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
            'animal_id' => isset($body['animal_id']) ? (int) $body['animal_id'] : 0,
            'severity' => $body['severity'] ?? '',
            'description' => isset($body['description']) ? \trim((string) $body['description']) : '',
            'incident_type' => $body['incident_type'] ?? null,
            'reported_by_user_id' => $user ? (int) $user['id'] : null,
        ];

        $cafeId = (int) ($user['cafe_id'] ?? 0);
        $animal = $data['animal_id'] > 0 ? $this->animalRepository->findById($data['animal_id']) : null;
        if ($animal === null || $animal->cafe_id !== $cafeId) {
            throw ValidationException::withMessage('Animal no válido', 403);
        }

        $result = $this->service->createIncident($data);

        if ($result->ok) {
            Flash::success('Incidente reportado correctamente.');
        } else {
            Flash::error($result->error);
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

        View::render('backoffice/keeper/incidents/show', \compact('incident'), [], 'backoffice');

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

        $body = (array) $request->getParsedBody();
        $resolution = isset($body['resolution']) ? \trim((string) $body['resolution']) : null;

        $user = Session::user();
        $userId = $user ? (int) $user['id'] : null;

        $result = $this->service->resolveIncident($incidentId, $resolution, $userId);

        if ($result->ok) {
            Flash::success('Incidente resuelto correctamente.');
        } else {
            Flash::error($result->error);
        }

        return $this->response->redirect('/keeper/incidents');
    }

    /**
     * GET /keeper/incidents/{id}/edit
     * Formulario de corrección de un incidente existente.
     *
     * @throws NotFoundException
     */
    public function edit(ServerRequestInterface $request, int $id): ?ResponseInterface
    {
        $incident = $this->service->getIncidentById($id);

        if ($incident === null) {
            throw NotFoundException::forResource('Incidente', $id);
        }

        View::render('backoffice/keeper/incidents/edit', \compact('incident'), [], 'backoffice');

        return null;
    }

    /**
     * POST /keeper/incidents/{id}
     * Guardar la corrección de datos de un incidente.
     *
     * @throws ValidationException
     */
    public function update(ServerRequestInterface $request, int $id): ResponseInterface
    {
        if (!Csrf::validate($request)) {
            throw ValidationException::withMessage('Token de seguridad inválido', 419);
        }

        $body = (array) $request->getParsedBody();

        $data = [];

        if (isset($body['severity']) && $body['severity'] !== '') {
            $data['severity'] = $body['severity'];
        }

        if (isset($body['description']) && $body['description'] !== '') {
            $data['description'] = \trim($body['description']);
        }

        $result = $this->service->updateIncident($id, $data);

        if ($result->ok) {
            Flash::success('Incidente actualizado correctamente.');
        } else {
            Flash::error($result->error ?? 'Error al actualizar el incidente');
        }

        return $this->response->redirect('/keeper/incidents/' . $id);
    }
}
