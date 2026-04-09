<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Env;
use App\Core\Http\ResponseFactory;
use App\Core\Logger;
use App\Core\Result;
use App\Core\View;
use App\Exceptions\ValidationException;
use App\Models\Cafe;
use App\Services\CafeService;
use InvalidArgumentException;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Random\RandomException;
use Throwable;

/**
 * Controlador de Gestión de Cafés
 *
 * Responsabilidad única: CRUD completo de cafés del sistema
 *
 * Métodos:
 * - index() - Lista de cafés
 * - create() - Crear nuevo café
 * - update() - Actualizar café
 * - toggleStatus() - Activar/desactivar
 * - delete() - Eliminar café (soft delete)
 */
final class CafeController
{
    private CafeService $cafeService;
    private ResponseFactory $response;

    public function __construct(?CafeService $cafeService = null, ?ResponseFactory $response = null)
    {
        $this->cafeService = $cafeService ?? new CafeService();
        $this->response = $response ?? new ResponseFactory();
    }

    /**
     * GET /admin/cafes
     * Lista de todos los cafés
     *
     * @throws RandomException
     */
    public function index(): void
    {
        // Obtener todos los cafés desde el modelo
        $cafeModel = new Cafe();
        $cafes = $cafeModel->findAll();

        View::render('admin/cafes/index', [
            'titulo' => 'Gestión de Cafés',
            'cafes' => $cafes,
            'csrf_token' => Csrf::token(),
            'extraJs' => ['admin/admin-cafes.js'],
        ], ['admin/admin-cafes.css'], 'backoffice');
    }

    /**
     * POST /admin/cafes/create
     * Crear nuevo café
     *
     * @throws JsonException
     * @throws RandomException
     */
    public function create(): ResponseInterface
    {
        if (!Csrf::validate()) {
            throw ValidationException::withMessage('Token de seguridad inválido', 419);
        }

        try {
            $cafeId = $this->cafeService->create($_POST);
            return $this->response->json(['ok' => true, 'data' => [
                'message' => 'Café creado correctamente',
                'cafe_id' => $cafeId,
            ]], 201);
        } catch (InvalidArgumentException $e) {
            return $this->response->problem(Result::fail($e->getMessage(), 'validation'), 422);
        } catch (Throwable $e) {
            if (Env::get('APP_ENV') === 'local') {
                Logger::error('[CafeManagementController::create] Error: ' . $e->getMessage());
            }
            return $this->response->problem(Result::fail('Error al crear el café', 'server_error'), 500);
        }
    }

    /**
     * POST /admin/cafes/{cafeId}/edit
     * Actualizar café existente
     *
     * @param integer $cafeId
     *
     * @throws JsonException
     * @throws RandomException
     */
    public function update(int $cafeId): ResponseInterface
    {
        if (!Csrf::validate()) {
            throw ValidationException::withMessage('Token de seguridad inválido', 419);
        }

        try {
            $this->cafeService->update($cafeId, $_POST);
            return $this->response->json(['ok' => true, 'data' => ['message' => 'Café actualizado correctamente']]);
        } catch (InvalidArgumentException $e) {
            return $this->response->problem(Result::fail($e->getMessage(), 'validation'), 422);
        } catch (Throwable $e) {
            if (Env::get('APP_ENV') === 'local') {
                Logger::error('[CafeManagementController::update] Error: ' . $e->getMessage());
            }
            return $this->response->problem(Result::fail('Error al actualizar el café', 'server_error'), 500);
        }
    }

    /**
     * POST /admin/cafes/{cafeId}/toggle-active
     * Activar/Desactivar café
     *
     * @param integer $cafeId
     *
     * @throws JsonException
     * @throws RandomException
     */
    public function toggleStatus(int $cafeId): ResponseInterface
    {
        if (!Csrf::validate()) {
            throw ValidationException::withMessage('Token de seguridad inválido', 419);
        }

        try {
            $this->cafeService->toggleActive($cafeId);
            return $this->response->json(['ok' => true, 'data' => ['message' => 'Estado del café actualizado']]);
        } catch (InvalidArgumentException $e) {
            return $this->response->problem(Result::fail($e->getMessage(), 'not_found'), 404);
        } catch (Throwable $e) {
            if (Env::get('APP_ENV') === 'local') {
                Logger::error('[CafeManagementController::toggleStatus] Error: ' . $e->getMessage());
            }
            return $this->response->problem(Result::fail('Error al cambiar el estado', 'server_error'), 500);
        }
    }

    /**
     * POST /admin/cafes/{cafeId}/delete
     * Desactivar café (soft delete)
     *
     * @param integer $cafeId
     *
     * @throws JsonException
     * @throws RandomException
     */
    public function delete(int $cafeId): ResponseInterface
    {
        if (!Csrf::validate()) {
            throw ValidationException::withMessage('Token de seguridad inválido', 419);
        }

        try {
            $this->cafeService->delete($cafeId);
            return $this->response->json(['ok' => true, 'data' => ['message' => 'Café desactivado correctamente']]);
        } catch (InvalidArgumentException $e) {
            return $this->response->problem(Result::fail($e->getMessage(), 'not_found'), 404);
        } catch (Throwable $e) {
            if (Env::get('APP_ENV') === 'local') {
                Logger::error('[CafeManagementController::delete] Error: ' . $e->getMessage());
            }
            return $this->response->problem(Result::fail('Error al eliminar el café', 'server_error'), 500);
        }
    }
}
