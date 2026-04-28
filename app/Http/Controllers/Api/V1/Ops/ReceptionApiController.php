<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Ops;

use App\Core\Http\ResponseFactory;
use App\Core\Session;
use App\Http\Controllers\Api\AbstractApiController;
use App\Services\Contracts\ReceptionServiceInterface;
use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * API REST: Operaciones de recepción (Ops scope)
 *
 * Rutas (bajo /api/v1/ops/reception):
 * - GET  /reservations             → todayReservations()
 * - POST /reservations/{id}/checkin  → checkIn()
 * - POST /reservations/{id}/checkout → checkOut()
 */
final class ReceptionApiController extends AbstractApiController
{
    public function __construct(
        ResponseFactory $response,
        private readonly ReceptionServiceInterface $service,
    ) {
        parent::__construct($response);
    }

    /**
     * GET /api/v1/ops/reception/reservations
     *
     * Lista las reservas pendientes de llegada para la sede asignada.
     */
    public function todayReservations(ServerRequestInterface $request): ResponseInterface
    {
        $cafeId = Session::userCafeId();

        if (!$cafeId) {
            return $this->forbidden('No tienes una sede asignada', 'cafe_not_assigned');
        }

        try {
            $reservations = $this->service->getPendingArrivals($cafeId);

            return $this->success(['reservations' => $reservations]);
        } catch (Exception $e) {
            return $this->serverError('Error al obtener reservas: ' . $e->getMessage(), 'fetch_failed');
        }
    }

    /**
     * POST /api/v1/ops/reception/reservations/{id}/checkin
     *
     * Realiza el check-in de una reserva asignando un tracker.
     */
    public function checkIn(ServerRequestInterface $request, int $id): ResponseInterface
    {
        if ($id <= 0) {
            return $this->badRequest('Identificador de reserva inválido', 'reservation_id_invalid');
        }

        $body    = (array) ($request->getParsedBody() ?? []);
        $trackId = (int) ($body['tracker_id'] ?? 0);

        if ($trackId <= 0) {
            return $this->badRequest('Se requiere tracker_id válido', 'tracker_id_invalid');
        }

        try {
            $result = $this->service->processCheckin($id, $trackId);

            if (!$result->ok) {
                return $this->unprocessable($result->error ?? 'Error al realizar check-in', 'checkin_failed');
            }

            return $this->success(['message' => 'Check-in realizado']);
        } catch (Exception $e) {
            return $this->serverError('Error al procesar check-in: ' . $e->getMessage(), 'checkin_error');
        }
    }

    /**
     * POST /api/v1/ops/reception/reservations/{id}/checkout
     *
     * Realiza el check-out de una reserva activa.
     */
    public function checkOut(ServerRequestInterface $request, int $id): ResponseInterface
    {
        if ($id <= 0) {
            return $this->badRequest('Identificador de reserva inválido', 'reservation_id_invalid');
        }

        try {
            $result = $this->service->processCheckout($id);

            if (!$result->ok) {
                return $this->unprocessable($result->error ?? 'Error al realizar check-out', 'checkout_failed');
            }

            return $this->success(['message' => 'Check-out realizado']);
        } catch (Exception $e) {
            return $this->serverError('Error al procesar check-out: ' . $e->getMessage(), 'checkout_error');
        }
    }
}
