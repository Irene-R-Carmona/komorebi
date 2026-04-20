<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Core\Http\ResponseFactory;
use App\Core\Session;
use App\Http\Controllers\Api\AbstractApiController;
use App\Repositories\Contracts\FavoriteRepositoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * FavoriteController (API)
 *
 * Endpoints:
 * - POST /api/favorites/toggle
 * - GET  /api/favorites
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
     * POST /api/favorites/toggle
     * Body: {cafe_id: int}
     */
    public function toggle(ServerRequestInterface $request): ResponseInterface
    {
        $userId = Session::userId();
        if ($userId === null) {
            return $this->unauthorized('Debes iniciar sesión');
        }

        $body = $request->getParsedBody() ?? [];
        $cafeId = $body['cafe_id'] ?? null;

        if (!\is_numeric($cafeId)) {
            return $this->unprocessable('cafe_id inválido');
        }

        $added = $this->favoriteRepo->toggle($userId, (int) $cafeId);

        return $this->success(['status' => $added ? 'added' : 'removed']);
    }

    /**
     * GET /api/favorites
     */
    public function list(ServerRequestInterface $request): ResponseInterface
    {
        $userId = Session::userId();
        if ($userId === null) {
            return $this->unauthorized('Debes iniciar sesión');
        }

        $favorites = $this->favoriteRepo->getByUser($userId);

        return $this->success(['items' => $favorites, 'total' => \count($favorites)]);
    }
}
