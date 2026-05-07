<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Core\Http\ResponseFactory;
use App\Http\Controllers\Api\AbstractApiController;
use App\Repositories\Contracts\FavoriteRepositoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * FavoriteController (API)
 *
 * Endpoints:
 * - PUT    /api/v1/favorites/{id}
 * - DELETE /api/v1/favorites/{id}
 * - GET    /api/v1/favorites
 */
final class FavoriteController extends AbstractApiController
{
    public function __construct(
        ResponseFactory $response,
        private readonly FavoriteRepositoryInterface $favoriteRepo,
    ) {
        parent::__construct($response);
    }

    /**
     * PUT /api/v1/favorites/{id}
     * Añade el café {id} a favoritos del usuario autenticado.
     */
    public function add(ServerRequestInterface $request): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        if ($userId === null) {
            return $this->unauthorized('Debes iniciar sesión');
        }

        $cafeId = (int) ($request->getAttribute('id') ?? 0);
        if ($cafeId <= 0) {
            return $this->unprocessable('id inválido', 'invalid_id');
        }

        $this->favoriteRepo->add((int) $userId, $cafeId);

        return $this->success(['status' => 'added']);
    }

    /**
     * DELETE /api/v1/favorites/{id}
     * Elimina el café {id} de favoritos del usuario autenticado.
     */
    public function remove(ServerRequestInterface $request): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        if ($userId === null) {
            return $this->unauthorized('Debes iniciar sesión');
        }

        $cafeId = (int) ($request->getAttribute('id') ?? 0);
        if ($cafeId <= 0) {
            return $this->unprocessable('id inválido', 'invalid_id');
        }

        $this->favoriteRepo->remove((int) $userId, $cafeId);

        return $this->success(['status' => 'removed']);
    }

    /**
     * GET /api/favorites
     */
    public function list(ServerRequestInterface $request): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        if ($userId === null) {
            return $this->unauthorized('Debes iniciar sesión');
        }

        $favorites = $this->favoriteRepo->getByUser((int) $userId);

        return $this->success(['items' => $favorites, 'total' => \count($favorites)]);
    }
}
