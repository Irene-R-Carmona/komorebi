<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Core\Http\ResponseFactory;
use App\Http\Controllers\Api\AbstractApiController;
use App\Models\AuditLog;
use App\Services\Contracts\ReservationServiceInterface;
use JsonException;
use Psr\Http\Message\ResponseInterface;

/**
 * API REST: Gestión de reservas (Admin)
 *
 * Rutas:
 * - POST /api/v1/admin/reservations/{id}/confirm → confirm()
 * - POST /api/v1/admin/reservations/{id}/cancel  → cancel()
 */
final class ReservationApiController extends AbstractApiController
{
    public function __construct(
        ResponseFactory $response,
        private readonly ReservationServiceInterface $reservationService,
    ) {
        parent::__construct($response);
    }

    /**
     * POST /api/v1/admin/reservations/{id}/confirm → 200
     *
     * @throws JsonException
     */
    public function confirm(int $id): ResponseInterface
    {
        $result = $this->reservationService->adminConfirm($id);

        if (!$result->ok) {
            return $this->unprocessable($result->error ?? 'Error al confirmar reserva');
        }

        AuditLog::log('confirm_reservation', 'reservation', $id, null, ['confirmed_at' => \date('Y-m-d H:i:s')]);

        return $this->success(['message' => 'Reserva confirmada correctamente']);
    }

    /**
     * POST /api/v1/admin/reservations/{id}/cancel → 200
     *
     * @throws JsonException
     */
    public function cancel(int $id): ResponseInterface
    {
        $result = $this->reservationService->adminCancel($id);

        if (!$result->ok) {
            return $this->unprocessable($result->error ?? 'Error al cancelar reserva');
        }

        AuditLog::log('cancel_reservation', 'reservation', $id, null, ['cancelled_at' => \date('Y-m-d H:i:s')]);

        return $this->success(['message' => 'Reserva cancelada correctamente']);
    }
}
