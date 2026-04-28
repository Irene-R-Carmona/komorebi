<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Core\Http\ResponseFactory;
use App\Http\Controllers\Api\AbstractApiController;
use App\Http\Transformers\ReviewTransformer;
use App\Services\Contracts\ReviewQueryServiceInterface;
use App\Services\Contracts\ReviewServiceInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * API autenticada de reseñas.
 *
 * Todos los endpoints requieren apiAuth middleware.
 * Las mutaciones requieren CSRF.
 */
final class ReviewController extends AbstractApiController
{
    public function __construct(
        ResponseFactory $response,
        private readonly ReviewServiceInterface $reviewService,
        private readonly ReviewQueryServiceInterface $reviewQueryService,
        private readonly ReviewTransformer $transformer = new ReviewTransformer(),
    ) {
        parent::__construct($response);
    }

    /**
     * POST /api/v1/reviews
     *
     * Crea una nueva reseña. Responde 201 + Location.
     *
     * Payload: { cafe_id, rating, title, body }
     */
    public function create(ServerRequestInterface $request): ResponseInterface
    {
        $userId = (int) $request->getAttribute('user_id');

        if ($userId <= 0) {
            return $this->unauthorized('Debes iniciar sesión para reseñar');
        }

        $body   = (array) ($request->getParsedBody() ?? []);
        $cafeId = (int) ($body['cafe_id'] ?? 0);
        $rating = (int) ($body['rating'] ?? 0);
        $title  = \trim((string) ($body['title'] ?? ''));
        $text   = \trim((string) ($body['body'] ?? ''));

        if ($cafeId <= 0) {
            return $this->unprocessable('cafe_id requerido y debe ser positivo', 'invalid_cafe_id');
        }

        if ($rating < 1 || $rating > 5) {
            return $this->unprocessable('rating debe estar entre 1 y 5', 'invalid_rating');
        }

        if ($title === '') {
            return $this->unprocessable('title requerido', 'invalid_title');
        }

        $result = $this->reviewService->createReview($userId, $cafeId, $rating, $title, $text);

        if (!$result->ok) {
            return $this->unprocessable($result->error ?? 'Error al crear la reseña', 'review_error');
        }

        $id = (int) ($result->data['id'] ?? 0);

        return $this->success($result->data, 201, [
            'Location' => '/api/v1/reviews/' . $id,
        ]);
    }

    /**
     * PUT /api/v1/reviews/{id}
     *
     * Actualiza una reseña del usuario. Responde 200.
     *
     * Payload: { rating, title, body }
     */
    public function update(ServerRequestInterface $request): ResponseInterface
    {
        $userId = (int) $request->getAttribute('user_id');

        if ($userId <= 0) {
            return $this->unauthorized('Debes iniciar sesión');
        }

        $reviewId = (int) ($request->getAttribute('id') ?? 0);

        if ($reviewId <= 0) {
            return $this->notFound('Reseña no encontrada');
        }

        $body   = (array) ($request->getParsedBody() ?? []);
        $rating = (int) ($body['rating'] ?? 0);
        $title  = \trim((string) ($body['title'] ?? ''));
        $text   = \trim((string) ($body['body'] ?? ''));

        if ($rating < 1 || $rating > 5) {
            return $this->unprocessable('rating debe estar entre 1 y 5', 'invalid_rating');
        }

        if ($title === '') {
            return $this->unprocessable('title requerido', 'invalid_title');
        }

        $result = $this->reviewService->updateReview($reviewId, $userId, $rating, $title, $text);

        if (!$result->ok) {
            return $this->unprocessable($result->error ?? 'Error al actualizar la reseña', 'review_error');
        }

        $review = $this->reviewQueryService->getReview($reviewId);

        if ($review === null) {
            return $this->notFound('Reseña no encontrada tras actualizar');
        }

        return $this->transform($review->toViewArray(), $this->transformer);
    }

    /**
     * DELETE /api/v1/reviews/{id}
     *
     * Elimina una reseña del usuario. Responde 204.
     */
    public function delete(ServerRequestInterface $request): ResponseInterface
    {
        $userId = (int) $request->getAttribute('user_id');

        if ($userId <= 0) {
            return $this->unauthorized('Debes iniciar sesión');
        }

        $reviewId = (int) ($request->getAttribute('id') ?? 0);

        if ($reviewId <= 0) {
            return $this->notFound('Reseña no encontrada');
        }

        $result = $this->reviewService->deleteReview($reviewId, $userId);

        if (!$result->ok) {
            return $this->unprocessable($result->error ?? 'Error al eliminar la reseña', 'review_error');
        }

        return $this->noContent();
    }
}
