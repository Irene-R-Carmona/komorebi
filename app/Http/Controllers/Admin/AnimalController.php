<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Flash;
use App\Core\View;
use App\Core\Http\ResponseFactory;
use App\Services\AnimalCareService;
use App\Services\FileUploadService;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Random\RandomException;

/**
 * Controlador de Gestión de Animales (Admin)
 */
final class AnimalController
{
    private AnimalCareService $animalCareService;
    private FileUploadService $fileUploadService;
    private ResponseFactory $response;

    public function __construct()
    {
        $this->animalCareService = new AnimalCareService();
        $this->fileUploadService = new FileUploadService();
        $this->response = new ResponseFactory();
    }

    /**
     * GET /admin/animals
     * Lista de animales
     * @throws JsonException
     * @throws RandomException
     */
    public function index(): ?ResponseInterface
    {
        $animals = $this->animalCareService->getAllAnimals();

        View::render('backoffice/admin/animals/index', [
            'titulo' => 'Gestión de Animales',
            'animals' => $animals,
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
        View::render('backoffice/admin/animals/create', [
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
    public function store(): ResponseInterface
    {
        if (!Csrf::validate()) {
            Flash::error('Token de seguridad inválido');
            return $this->response->redirect('/admin/animals/create');
        }

        $data = [
            'name' => $_POST['name'] ?? '',
            'species' => $_POST['species'] ?? '',
            'breed' => $_POST['breed'] ?? null,
            'age_years' => isset($_POST['age_years']) ? (int) $_POST['age_years'] : null,
            'personality' => $_POST['personality'] ?? null,
            'cafe_id' => isset($_POST['cafe_id']) ? (int) $_POST['cafe_id'] : null,
        ];

        $result = $this->animalCareService->createAnimal($data);

        if ($result->isOk()) {
            Flash::success('Animal creado correctamente');
            return $this->response->redirect('/admin/animals');
        }

        Flash::error($result->getMessage() ?? 'Error al crear animal');
        return $this->response->redirect('/admin/animals/create');
    }

    /**
     * GET /admin/animals/{id}/edit
     * Formulario de edición
     * @throws JsonException
     * @throws RandomException
     */
    public function edit(): ?ResponseInterface
    {
        $id = (int) ($_GET['id'] ?? 0);
        $animal = null;
        $animal = $this->animalCareService->getAnimalById($id);

        if (!$animal) {
            Flash::error('Animal no encontrado');
            return $this->response->redirect('/admin/animals');
        }

        View::render('backoffice/admin/animals/edit', [
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
    public function update(): ResponseInterface
    {
        if (!Csrf::validate()) {
            Flash::error('Token de seguridad inválido');
            return $this->response->redirect('/admin/animals');
        }

        $id = (int) ($_POST['id'] ?? 0);
        $data = [
            'name' => $_POST['name'] ?? '',
            'species' => $_POST['species'] ?? '',
            'breed' => $_POST['breed'] ?? null,
            'age_years' => isset($_POST['age_years']) ? (int) $_POST['age_years'] : null,
            'personality' => $_POST['personality'] ?? null,
            'cafe_id' => isset($_POST['cafe_id']) ? (int) $_POST['cafe_id'] : null,
        ];

        $result = $this->animalCareService->updateAnimal($id, $data);

        if ($result->isOk()) {
            Flash::success('Animal actualizado correctamente');
        } else {
            Flash::error($result->getMessage() ?? 'Error al actualizar animal');
        }

        return $this->response->redirect('/admin/animals');
    }

    /**
     * POST /admin/animals/{id}/delete
     * Eliminar animal
     * @throws JsonException
     * @throws RandomException
     */
    public function delete(): ResponseInterface
    {
        if (!Csrf::validate()) {
            Flash::error('Token de seguridad inválido');
            return $this->response->redirect('/admin/animals');
        }

        $id = (int) ($_POST['id'] ?? 0);

        $result = $this->animalCareService->deleteAnimal($id);

        if ($result->isOk()) {
            Flash::success('Animal eliminado');
        } else {
            Flash::error($result->getMessage() ?? 'Error al eliminar animal');
        }

        return $this->response->redirect('/admin/animals');
    }
}
