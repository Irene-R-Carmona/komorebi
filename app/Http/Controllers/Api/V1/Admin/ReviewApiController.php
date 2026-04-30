<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Core\Http\ResponseFactory;
use App\Http\Controllers\Api\AbstractApiController;
use App\Services\Contracts\ReviewModerationServiceInterface;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * API REST: Moderación de reseñas (Admin)
 *
 * Rutas:
 * - POST   /api/v1/admin/reviews/{id}/approve → approve()
 * - POST   /api/v1/admin/reviews/{id}/reject  → reject()
 * - DELETE /api/v1/admin/reviews/{id}         → delete()
 */
final class ReviewApiController extends AbstractApiController
{
    public function __construct(
        ResponseFactory $response,
        private readonly ReviewModerationServiceInterface $moderationService,
    ) {
        parent::__construct($response);
    }

    /**
     * POST /api/v1/admin/reviews/{id}/approve → 200
     *
     * @throws JsonException
     */
    public function approve(int $id): ResponseInterface
    {
        $result = $this->moderationService->approveReview($id);

        if (!$result->ok) {
            return $this->unprocessable($result->error ?? 'Error al aprobar reseña');
        }

        return $this->success(['message' => 'Reseña aprobada correctamente']);
    }

    /**
     * POST /api/v1/admin/reviews/{id}/reject → 200
     *
     * @throws JsonException
     */
    public function reject(ServerRequestInterface $request, int $id): ResponseInterface
    {
        $body = (array) ($request->getParsedBody() ?? []);
        $reason = (string) ($body['reason'] ?? 'Contenido inapropiado');

        $result = $this->moderationService->rejectReview($id, $reason);

        if (!$result->ok) {
            return $this->unprocessable($result->error ?? 'Error al rechazar reseña');
        }

        return $this->success(['message' => 'Reseña rechazada']);
    }

    /**
     * DELETE /api/v1/admin/reviews/{id} → 204
     *
     * @throws JsonException
     */
    public function delete(int $id): ResponseInterface
    {
        $deleted = $this->moderationService->deleteReviewById($id);

        if (!$deleted) {
            return $this->notFound('Reseña no encontrada');
        }

        return $this->noContent();
    }
}
