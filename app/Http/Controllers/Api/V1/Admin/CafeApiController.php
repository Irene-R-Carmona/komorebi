<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Core\Http\ResponseFactory;
use App\Core\Result;
use App\Http\Controllers\Api\AbstractApiController;
use App\Services\Contracts\CafeServiceInterface;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Random\RandomException;

/**
 * API REST: Gestión de cafés (Admin)
 *
 * Rutas:
 * - POST   /api/v1/admin/cafes             → create()
 * - PUT    /api/v1/admin/cafes/{id}        → update()
 * - DELETE /api/v1/admin/cafes/{id}        → delete()
 * - PATCH  /api/v1/admin/cafes/{id}/status → toggleStatus()
 */
final class CafeApiController extends AbstractApiController
{
    public function __construct(
        ResponseFactory $response,
        private readonly CafeServiceInterface $cafeService,
    ) {
        parent::__construct($response);
    }

    /**
     * POST /api/v1/admin/cafes → 201
     *
     * @throws JsonException
     * @throws RandomException
     */
    public function create(ServerRequestInterface $request): ResponseInterface
    {
        $body = (array) ($request->getParsedBody() ?? []);
        $result = $this->cafeService->create($body);

        if ($result->ok) {
            return $this->created([
                'message' => 'Café creado correctamente',
                'cafe_id' => $result->data,
            ]);
        }

        return $this->handleFailResult($result);
    }

    /**
     * PUT /api/v1/admin/cafes/{id} → 200
     *
     * @throws JsonException
     * @throws RandomException
     */
    public function update(ServerRequestInterface $request, int $id): ResponseInterface
    {
        $body = (array) ($request->getParsedBody() ?? []);
        $result = $this->cafeService->update($id, $body);

        if ($result->ok) {
            return $this->success(['message' => 'Café actualizado correctamente']);
        }

        return $this->handleFailResult($result);
    }

    /**
     * PATCH /api/v1/admin/cafes/{id}/status → 200
     *
     * @throws JsonException
     * @throws RandomException
     */
    public function toggleStatus(int $id): ResponseInterface
    {
        $result = $this->cafeService->toggleActive($id);

        if ($result->ok) {
            return $this->success(['message' => 'Estado del café actualizado']);
        }

        return $this->handleFailResult($result);
    }

    /**
     * DELETE /api/v1/admin/cafes/{id} → 200
     *
     * @throws JsonException
     * @throws RandomException
     */
    public function delete(int $id): ResponseInterface
    {
        $result = $this->cafeService->delete($id);

        if ($result->ok) {
            return $this->success(['message' => 'Café eliminado correctamente']);
        }

        return $this->handleFailResult($result);
    }

    private function handleFailResult(Result $result): ResponseInterface
    {
        return match ($result->code) {
            'not_found' => $this->notFound($result->error ?? 'Café no encontrado'),
            'validation',
            'validation_error' => $this->unprocessable($result->error ?? 'Datos inválidos'),
            default => $this->serverError($result->error ?? 'Error del servidor'),
        };
    }
}
