<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Env;
use App\Core\Http\ResponseFactory;
use App\Core\Logger;
use App\Core\Result;
use App\Core\View;
use App\Exceptions\ValidationException;
use App\Http\Transformers\CafeTransformer;
use App\Services\Contracts\CafeServiceInterface;
use InvalidArgumentException;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
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
    private CafeServiceInterface $cafeService;
    private ResponseFactory $response;
    private CafeTransformer $cafeTransformer;

    private const CSRF_INVALID = 'Token de seguridad inválido';

    public function __construct(?CafeServiceInterface $cafeService = null, ?ResponseFactory $response = null, ?CafeTransformer $cafeTransformer = null)
    {
        $this->cafeService = $cafeService ?? Container::make(CafeServiceInterface::class);
        $this->response = $response ?? new ResponseFactory();
        $this->cafeTransformer = $cafeTransformer ?? new CafeTransformer();
    }

    /**
     * GET /admin/cafes
     * Lista de todos los cafés
     *
     * @throws RandomException
     */
    public function index(ServerRequestInterface $request): ?ResponseInterface
    {
        $cafes = $this->cafeTransformer->collection($this->cafeService->getAll());

        View::render('admin/cafes/index', [
            'titulo' => 'Gestión de Cafés',
            'cafes' => $cafes,
            'csrf_token' => Csrf::token(),
            'extraJs' => ['admin/admin-cafes.js'],
        ], ['admin/admin-cafes.css'], 'backoffice');

        return null;
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
            throw ValidationException::withMessage(self::CSRF_INVALID, 419);
        }

        try {
            $cafeId = $this->cafeService->create($_POST); // NOSONAR

            return $this->response->json(['ok' => true, 'data' => [
                'message' => 'Café creado correctamente',
                'cafe_id' => $cafeId,
            ]], 201);
        } catch (InvalidArgumentException $e) {
            return $this->response->problem(Result::fail($e->getMessage(), 'validation'), 422); // NOSONAR
        } catch (Throwable $e) { // NOSONAR
            if (Env::get('APP_ENV') === 'local') {
                Logger::error('[CafeManagementController::create] Error: ' . $e->getMessage(), ['exception' => $e->getMessage()]);
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
            throw ValidationException::withMessage(self::CSRF_INVALID, 419);
        }

        try {
            $this->cafeService->update($cafeId, $_POST); // NOSONAR

            return $this->response->json(['ok' => true, 'data' => ['message' => 'Café actualizado correctamente']]);
        } catch (InvalidArgumentException $e) {
            return $this->response->problem(Result::fail($e->getMessage(), 'validation'), 422); // NOSONAR
        } catch (Throwable $e) { // NOSONAR
            if (Env::get('APP_ENV') === 'local') {
                Logger::error('[CafeManagementController::update] Error: ' . $e->getMessage(), ['exception' => $e->getMessage()]);
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
            throw ValidationException::withMessage(self::CSRF_INVALID, 419);
        }

        try {
            $this->cafeService->toggleActive($cafeId);

            return $this->response->json(['ok' => true, 'data' => ['message' => 'Estado del café actualizado']]);
        } catch (InvalidArgumentException $e) {
            return $this->response->problem(Result::fail($e->getMessage(), 'not_found'), 404); // NOSONAR
        } catch (Throwable $e) { // NOSONAR
            if (Env::get('APP_ENV') === 'local') {
                Logger::error('[CafeManagementController::toggleStatus] Error: ' . $e->getMessage(), ['exception' => $e->getMessage()]);
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
            throw ValidationException::withMessage(self::CSRF_INVALID, 419);
        }

        try {
            $this->cafeService->delete($cafeId);

            return $this->response->json(['ok' => true, 'data' => ['message' => 'Café desactivado correctamente']]);
        } catch (InvalidArgumentException $e) {
            return $this->response->problem(Result::fail($e->getMessage(), 'not_found'), 404); // NOSONAR
        } catch (Throwable $e) { // NOSONAR
            if (Env::get('APP_ENV') === 'local') {
                Logger::error('[CafeManagementController::delete] Error: ' . $e->getMessage(), ['exception' => $e->getMessage()]);
            }

            return $this->response->problem(Result::fail('Error al eliminar el café', 'server_error'), 500);
        }
    }
}
