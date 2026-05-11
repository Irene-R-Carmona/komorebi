<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Http\ResponseFactory;
use App\Core\Pagination;
use App\Core\View;
use App\Http\Transformers\AnimalTransformer;
use App\Services\Contracts\AnimalCareServiceInterface;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Random\RandomException;

/**
 * Controlador de Gestión de Animales (Admin)
 */
final class AnimalController
{
    private AnimalCareServiceInterface $animalCareService;
    private ResponseFactory $response;
    private AnimalTransformer $animalTransformer;

    private const CSRF_INVALID = 'Token de seguridad inválido';
    private const ADMIN_ANIMALS_URL = '/admin/animals';

    public function __construct(
        ?AnimalCareServiceInterface $animalCareService = null,
        ?ResponseFactory $response = null,
        ?AnimalTransformer $animalTransformer = null,
    ) {
        $this->animalCareService = $animalCareService ?? Container::make(AnimalCareServiceInterface::class);
        $this->response = $response ?? new ResponseFactory();
        $this->animalTransformer = $animalTransformer ?? new AnimalTransformer();
    }

    /**
     * GET /admin/animals
     * Lista de animales
     * @throws JsonException
     * @throws RandomException
     */
    public function index(ServerRequestInterface $request): ?ResponseInterface
    {
        $params = $request->getQueryParams();
        $search = \trim((string) ($params['search'] ?? ''));
        $status = (string) ($params['status'] ?? '');
        $species = (string) ($params['species'] ?? '');
        $page = \max(1, (int) ($params['page'] ?? 1));
        $perPage = Pagination::PAGE_SIZE_LIST;

        $allAnimals = $this->animalTransformer->collection(
            $this->animalCareService->getAllAnimals()
        );

        $filtered = \array_values(\array_filter($allAnimals, static function (array $a) use ($search, $status, $species): bool {
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
        $animals = \array_slice($filtered, ($page - 1) * $perPage, $perPage);
        $hasNextPage = ($page * $perPage) < $total;
        $meta = ['page' => $page, 'has_next_page' => $hasNextPage];
        $currentParams = \array_filter(\compact('search', 'status', 'species'));

        View::render('backoffice/keeper/animals/index', [
            'titulo' => 'Gestión de Animales',
            'animals' => $animals,
            'total' => $total,
            'meta' => $meta,
            'currentParams' => $currentParams,
            'baseUrl' => '/admin/animals',
            'csrf_token' => Csrf::token(),
        ], [], 'backoffice');

        return null;
    }

    /**
     * GET /admin/animals/create
     * Formulario de creación
     * @throws JsonException
     * @throws RandomException
     */
    public function create(): ?ResponseInterface
    {
        View::render('backoffice/keeper/animals/create', [
            'titulo' => 'Nuevo Animal',
            'csrf_token' => Csrf::token(),
        ], [], 'backoffice');

        return null;
    }

    /**
     * POST /admin/animals
     * Crear animal
     * @throws JsonException
     * @throws RandomException
     */
    public function store(ServerRequestInterface $request): ResponseInterface
    {
        if (!Csrf::validate()) {
            Flash::error(self::CSRF_INVALID);

            return $this->response->redirect('/admin/animals/create');
        }

        $body = (array) $request->getParsedBody();
        $data = [
            'name' => $body['name'] ?? '',
            'species' => $body['species'] ?? '',
            'breed' => $body['breed'] ?? null,
            'age_years' => isset($body['age_years']) ? (int) $body['age_years'] : null,
            'personality' => $body['personality'] ?? null,
            'cafe_id' => isset($body['cafe_id']) ? (int) $body['cafe_id'] : null,
        ];

        $result = $this->animalCareService->createAnimal($data);

        if ($result->ok) {
            Flash::success('Animal creado correctamente');

            return $this->response->redirect(self::ADMIN_ANIMALS_URL);
        }

        Flash::error($result->error ?? 'Error al crear animal');

        return $this->response->redirect('/admin/animals/create');
    }

    /**
     * GET /admin/animals/{id}/edit
     * Formulario de edición
     * @throws JsonException
     * @throws RandomException
     */
    public function edit(ServerRequestInterface $request): ?ResponseInterface
    {
        $id = (int) $request->getAttribute('id');
        $animal = $this->animalCareService->getAnimalById($id);

        if (!$animal) {
            Flash::error('Animal no encontrado');

            return $this->response->redirect(self::ADMIN_ANIMALS_URL);
        }

        View::render('backoffice/keeper/animals/edit', [
            'titulo' => 'Editar Animal',
            'animal' => $animal,
            'csrf_token' => Csrf::token(),
        ], [], 'backoffice');

        return null;
    }

    /**
     * POST /admin/animals/{id}
     * Actualizar animal
     * @throws JsonException
     * @throws RandomException
     */
    public function update(ServerRequestInterface $request): ResponseInterface
    {
        if (!Csrf::validate()) {
            Flash::error(self::CSRF_INVALID);

            return $this->response->redirect(self::ADMIN_ANIMALS_URL);
        }

        $body = (array) $request->getParsedBody();
        $id = (int) ($body['id'] ?? 0);
        $data = [
            'name' => $body['name'] ?? '',
            'species' => $body['species'] ?? '',
            'breed' => $body['breed'] ?? null,
            'age_years' => isset($body['age_years']) ? (int) $body['age_years'] : null,
            'personality' => $body['personality'] ?? null,
            'cafe_id' => isset($body['cafe_id']) ? (int) $body['cafe_id'] : null,
        ];

        $result = $this->animalCareService->updateAnimal($id, $data);

        if ($result->ok) {
            Flash::success('Animal actualizado correctamente');
        } else {
            Flash::error($result->error ?? 'Error al actualizar animal');
        }

        return $this->response->redirect(self::ADMIN_ANIMALS_URL);
    }

    /**
     * POST /admin/animals/{id}/delete
     * Eliminar animal
     * @throws JsonException
     * @throws RandomException
     */
    public function delete(ServerRequestInterface $request): ResponseInterface
    {
        if (!Csrf::validate()) {
            Flash::error(self::CSRF_INVALID);

            return $this->response->redirect(self::ADMIN_ANIMALS_URL);
        }

        $body = (array) $request->getParsedBody();
        $id = (int) ($body['id'] ?? 0);

        $result = $this->animalCareService->deleteAnimal($id);

        if ($result->ok) {
            Flash::success('Animal eliminado');
        } else {
            Flash::error($result->error ?? 'Error al eliminar animal');
        }

        return $this->response->redirect(self::ADMIN_ANIMALS_URL);
    }
}
