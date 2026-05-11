<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Core\Http\ResponseFactory;
use App\Core\Pagination;
use App\Http\Controllers\Api\AbstractApiController;
use App\Http\Transformers\ReservationTransformer;
use App\Repositories\Contracts\ReservationRepositoryInterface;
use App\Services\Contracts\ReservationServiceInterface;
use App\Services\Contracts\TimeSlotServiceInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Reservation API Controller
 *
 * Endpoints JSON para gestión de reservas desde frontend (AJAX)
 */
final class ReservationController extends AbstractApiController
{
    public function __construct(
        ResponseFactory $response,
        private readonly ReservationServiceInterface $reservationService,
        private readonly TimeSlotServiceInterface $timeSlotService,
        private readonly ReservationRepositoryInterface $reservationRepo,
        private readonly ReservationTransformer $transformer,
    ) {
        parent::__construct($response);
    }

    /**
     * GET /api/reservations/available
     *
     * Query params:
     * - date: YYYY-MM-DD (requerido)
     * - cafe_id: int (opcional)
     * - guests: int (opcional, para filtrar por capacidad)
     */
    public function getAvailableSlots(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getQueryParams();
        $date = $params['date'] ?? '';
        $cafeId = isset($params['cafe_id']) ? (int) $params['cafe_id'] : null;
        $guests = isset($params['guests']) ? (int) $params['guests'] : null;

        if (empty($date) || !\preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $this->unprocessable('Fecha inválida (formato esperado: YYYY-MM-DD)');
        }

        $slots = $this->timeSlotService->getAvailableSlots($date, $cafeId, $guests);

        return $this->success([
            'date' => $date,
            'cafe_id' => $cafeId,
            'slots' => $slots,
        ]);
    }

    /**
     * POST /api/v1/reservations
     *
     * Payload:
     * {
     *   "cafe_id": 1,
     *   "date": "2026-02-20",
     *   "time": "14:00",
     *   "guests": 2,
     *   "pass_product_id": 3,
     *   "special_requests": "..."
     * }
     */
    public function create(ServerRequestInterface $request): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        if (!$userId) {
            return $this->unauthorized('Debes iniciar sesión para reservar');
        }

        $body = $request->getParsedBody() ?? [];
        $required = ['cafe_id', 'date', 'time', 'guests', 'pass_product_id'];

        foreach ($required as $field) {
            if (empty($body[$field])) {
                return $this->unprocessable("El campo {$field} es requerido");
            }
        }

        $data = [
            'user_id'         => (int) $userId,
            'cafe_id'         => (int) $body['cafe_id'],
            'pass_product_id' => (int) $body['pass_product_id'],
            'date'            => (string) $body['date'],
            'time'            => (string) $body['time'],
            'guests'          => (int) $body['guests'],
            'comments'        => (string) ($body['special_requests'] ?? ''),
            'pre_order'       => $body['pre_order'] ?? [],
        ];

        $result = $this->reservationService->create($data);

        if (!$result->ok) {
            return $this->unprocessable($result->error, $result->code ?? 'reservation_error');
        }

        $id = (int) $result->data;

        return $this->success([
            'reservation_id' => $id,
        ], 201, [
            'Location' => '/reservas/confirmacion/' . $id,
        ]);
    }

    /**
     * POST /api/v1/reservations/{id}/cancel
     */
    public function cancel(ServerRequestInterface $request): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        if (!$userId) {
            return $this->unauthorized('Debes iniciar sesión');
        }

        $id = (int) $request->getAttribute('id');
        if ($id <= 0) {
            return $this->notFound('Reserva no encontrada');
        }

        $result = $this->reservationService->cancel($id, (int) $userId);

        if (!$result->ok) {
            return $this->unprocessable($result->error);
        }

        return $this->success(['cancelled' => true]);
    }

    /**
     * GET /api/v1/reservations/{id}
     */
    public function show(ServerRequestInterface $request): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        if (!$userId) {
            return $this->unauthorized('Debes iniciar sesión');
        }

        $id = (int) $request->getAttribute('id');
        if ($id <= 0) {
            return $this->notFound('Reserva no encontrada');
        }

        $row = $this->reservationRepo->findByIdAndUser($id, (int) $userId);
        if ($row === null) {
            return $this->notFound('Reserva no encontrada');
        }

        return $this->transform($row, $this->transformer);
    }

    /**
     * GET /api/v1/user/reservations
     *
     * Query params: page (int), limit (int), status (string)
     */
    public function userReservations(ServerRequestInterface $request): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        if (!$userId) {
            return $this->unauthorized('Debes iniciar sesión');
        }

        $params = $request->getQueryParams();
        $page = \max(1, (int) ($params['page'] ?? 1));
        $limit = \max(1, (int) ($params['limit'] ?? Pagination::DEFAULT_LIMIT));
        $status = isset($params['status']) && $params['status'] !== '' ? (string) $params['status'] : null;

        $pagination = Pagination::fromRequest($page, $limit);

        $result = $this->reservationRepo->findByUser((int) $userId, $status, $pagination->fetchLimit, $pagination->offset);
        $items = $this->transformer->collection($result['data']);

        $hasNext = \count($result['data']) > $pagination->limit;
        if ($hasNext) {
            \array_pop($items);
        }

        return $this->success([
            'items' => $items,
            'meta' => $pagination->toMeta($hasNext),
        ]);
    }
}
