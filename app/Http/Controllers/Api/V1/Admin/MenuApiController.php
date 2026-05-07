<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Core\Http\ResponseFactory;
use App\Exceptions\ValidationException;
use App\Http\Controllers\Api\AbstractApiController;
use App\Services\Contracts\ProductServiceInterface;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Random\RandomException;

/**
 * API REST: Gestión de menú/productos (Admin)
 *
 * Rutas:
 * - POST   /api/v1/admin/menu             → create()
 * - PUT    /api/v1/admin/menu/{id}        → update()
 * - DELETE /api/v1/admin/menu/{id}        → delete()
 * - PATCH  /api/v1/admin/menu/{id}/toggle → toggleAvailability()
 */
final class MenuApiController extends AbstractApiController
{
    public function __construct(
        ResponseFactory $response,
        private readonly ProductServiceInterface $productService,
    ) {
        parent::__construct($response);
    }

    /**
     * POST /api/v1/admin/menu → 201
     *
     * @throws JsonException
     * @throws RandomException
     * @throws ValidationException
     */
    public function create(ServerRequestInterface $request): ResponseInterface
    {
        $body = (array) ($request->getParsedBody() ?? []);

        try {
            $productId = $this->productService->create($body);

            return $this->created([
                'message' => 'El producto se ha creado correctamente.',
                'product_id' => $productId,
            ]);
        } catch (ValidationException $e) {
            throw ValidationException::withMessage($e->getMessage(), 422);
        }
    }

    /**
     * PUT /api/v1/admin/menu/{id} → 200
     *
     * @throws JsonException
     * @throws RandomException
     * @throws ValidationException
     */
    public function update(ServerRequestInterface $request, int $id): ResponseInterface
    {
        $body = (array) ($request->getParsedBody() ?? []);

        try {
            $this->productService->update($id, $body);

            return $this->success(['message' => 'El producto se ha actualizado correctamente.']);
        } catch (ValidationException $e) {
            throw ValidationException::withMessage($e->getMessage(), 422);
        }
    }

    /**
     * PATCH /api/v1/admin/menu/{id}/toggle → 200
     *
     * @throws JsonException
     * @throws RandomException
     * @throws ValidationException
     */
    public function toggleAvailability(int $id): ResponseInterface
    {
        $success = $this->productService->toggleActive($id);

        if ($success) {
            return $this->success(['message' => 'Estado de disponibilidad actualizado.']);
        }

        return $this->serverError('No se pudo actualizar el producto');
    }

    /**
     * DELETE /api/v1/admin/menu/{id} → 200
     *
     * @throws JsonException
     * @throws RandomException
     * @throws ValidationException
     */
    public function delete(int $id): ResponseInterface
    {
        $this->productService->delete($id);

        return $this->success(['message' => 'El producto se ha eliminado correctamente.']);
    }
}
