<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Core\Http\ResponseFactory;
use App\Core\Session;
use App\Http\Controllers\Api\AbstractApiController;
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
     * POST /api/reservations/create
     *
     * Payload:
     * {
     *   "cafe_id": 1,
     *   "date": "2026-02-20",
     *   "time": "14:00",
     *   "guests": 2,
     *   "special_requests": "..."
     * }
     */
    public function create(ServerRequestInterface $request): ResponseInterface
    {
        $userId = Session::userId();
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
            'user_id' => $userId,
            'cafe_id' => (int) $body['cafe_id'],
            'pass_product_id' => (int) $body['pass_product_id'],
            'date' => (string) $body['date'],
            'time' => (string) $body['time'],
            'guests' => (int) $body['guests'],
            'comments' => (string) ($body['special_requests'] ?? ''),
        ];

        $result = $this->reservationService->create($data);

        if (!$result->ok) {
            return $this->unprocessable($result->getMessage(), 'reservation_error');
        }

        return $this->success([
            'reservation_id' => $result->data['id'] ?? null,
            'confirmation_code' => $result->data['confirmation_code'] ?? null,
        ], 201);
    }
}
