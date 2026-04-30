<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Core\Http\ResponseFactory;
use App\Core\Pagination;
use App\Domain\AvatarOptions;
use App\Http\Controllers\Api\AbstractApiController;
use App\Http\Transformers\ReviewTransformer;
use App\Http\Transformers\UserTransformer;
use App\Services\Contracts\GamificationServiceInterface;
use App\Services\Contracts\ReservationServiceInterface;
use App\Services\Contracts\ReviewQueryServiceInterface;
use App\Services\Contracts\UserProfileServiceInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * API autenticada de perfil de usuario.
 *
 * Todos los endpoints requieren apiAuth middleware.
 */
final class UserController extends AbstractApiController
{
    public function __construct(
        ResponseFactory $response,
        private readonly UserProfileServiceInterface $profileService,
        private readonly ReservationServiceInterface $reservationService,
        private readonly GamificationServiceInterface $gamificationService,
        private readonly ReviewQueryServiceInterface $reviewQueryService,
        private readonly UserTransformer $userTransformer = new UserTransformer(),
        private readonly ReviewTransformer $reviewTransformer = new ReviewTransformer(),
    ) {
        parent::__construct($response);
    }

    /**
     * GET /api/v1/user/profile
     *
     * Devuelve el perfil del usuario autenticado.
     */
    public function profile(ServerRequestInterface $request): ResponseInterface
    {
        $userId = (int) $request->getAttribute('user_id');

        if ($userId <= 0) {
            return $this->unauthorized('Debes iniciar sesión');
        }

        $profile = $this->profileService->getProfile($userId);

        return $this->success($this->userTransformer->transform($profile));
    }

    /**
     * GET /api/v1/user/stats
     *
     * Devuelve estadísticas de gamificación del usuario.
     */
    public function stats(ServerRequestInterface $request): ResponseInterface
    {
        $userId = (int) $request->getAttribute('user_id');

        if ($userId <= 0) {
            return $this->unauthorized('Debes iniciar sesión');
        }

        $reservations = $this->reservationService->getByUser($userId);
        $count = \count($reservations);
        $level = $this->gamificationService->calculateUserLevel($count);

        return $this->success([
            'reservations_count' => $count,
            'level' => $level,
        ]);
    }

    /**
     * GET /api/v1/user/reviews
     *
     * Devuelve las reseñas del usuario con paginación.
     *
     * Query params: page (int), limit (int)
     */
    public function reviews(ServerRequestInterface $request): ResponseInterface
    {
        $userId = (int) $request->getAttribute('user_id');

        if ($userId <= 0) {
            return $this->unauthorized('Debes iniciar sesión');
        }

        $params = $request->getQueryParams();
        $page = \max(1, (int) ($params['page'] ?? 1));
        $limit = \max(1, (int) ($params['limit'] ?? Pagination::DEFAULT_LIMIT));
        $pagination = Pagination::fromRequest($page, $limit);

        $all = $this->reviewQueryService->listUserReviews($userId);
        $slice = \array_slice($all, $pagination->offset, $pagination->fetchLimit);
        $hasNext = \count($slice) > $pagination->limit;
        if ($hasNext) {
            \array_pop($slice);
        }

        return $this->success([
            'items' => $this->reviewTransformer->collection($slice),
            'meta' => $pagination->toMeta($hasNext),
        ]);
    }

    /**
     * GET /api/v1/user/avatar-options
     *
     * Lista de opciones de avatar preset disponibles.
     * No requiere autenticación adicional (ya cubierta por apiAuth).
     */
    public function avatarOptions(ServerRequestInterface $request): ResponseInterface
    {
        return $this->success(AvatarOptions::toList());
    }
}
