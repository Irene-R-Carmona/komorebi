<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Core\Http\ResponseFactory;
use App\Core\Logger;
use App\Core\Session;
use App\Exceptions\ValidationException;
use App\Http\Controllers\Api\AbstractApiController;
use App\Services\Contracts\ProductServiceInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * API REST: Gestión de productos del catálogo (Manager scope)
 *
 * Rutas (bajo /api/v1/manager):
 * - POST   /products           → create()
 * - PUT    /products/{id}      → update()
 * - PATCH  /products/{id}/toggle → toggleAvailability()
 * - DELETE /products/{id}      → delete()
 */
final class ProductApiController extends AbstractApiController
{
    public function __construct(
        ResponseFactory $response,
        private readonly ProductServiceInterface $productService,
    ) {
        parent::__construct($response);
    }

    /**
     * POST /api/v1/manager/products
     */
    public function create(ServerRequestInterface $request): ResponseInterface
    {
        $body = (array) ($request->getParsedBody() ?? []);
        unset($body['image_url']); // Solo admin puede modificar image_url

        try {
            $productId = $this->productService->create($body);

            Logger::info('[Api\V1\Manager\ProductApiController] Producto creado', [
                'manager_id' => Session::get('user_id'),
                'product_id' => $productId,
            ]);

            return $this->created([
                'message' => 'El producto se ha creado correctamente.',
                'product_id' => $productId,
            ]);
        } catch (ValidationException $e) {
            return $this->unprocessable($e->getMessage(), 'validation_failed');
        }
    }

    /**
     * PUT /api/v1/manager/products/{id}
     */
    public function update(ServerRequestInterface $request, int $id): ResponseInterface
    {
        $body = (array) ($request->getParsedBody() ?? []);
        unset($body['image_url']); // Solo admin puede modificar image_url

        try {
            $this->productService->update($id, $body);

            Logger::info('[Api\V1\Manager\ProductApiController] Producto actualizado', [
                'manager_id' => Session::get('user_id'),
                'product_id' => $id,
            ]);

            return $this->success(['message' => 'El producto se ha actualizado correctamente.']);
        } catch (ValidationException $e) {
            return $this->unprocessable($e->getMessage(), 'validation_failed');
        }
    }

    /**
     * PATCH /api/v1/manager/products/{id}/toggle
     */
    public function toggleAvailability(ServerRequestInterface $request, int $id): ResponseInterface
    {
        if ($this->productService->toggleActive($id)) {
            return $this->success(['message' => 'Estado de disponibilidad actualizado.']);
        }

        return $this->serverError('No se pudo actualizar el producto', 'toggle_failed');
    }

    /**
     * DELETE /api/v1/manager/products/{id}
     */
    public function delete(ServerRequestInterface $request, int $id): ResponseInterface
    {
        $this->productService->delete($id);

        Logger::info('[Api\V1\Manager\ProductApiController] Producto eliminado', [
            'manager_id' => Session::get('user_id'),
            'product_id' => $id,
        ]);

        return $this->success(['message' => 'El producto se ha eliminado correctamente.']);
    }
}
