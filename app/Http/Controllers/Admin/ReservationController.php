<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Http\ResponseFactory;
use App\Core\View;
use App\Exceptions\BusinessRuleException;
use App\Http\Transformers\ReservationTransformer;
use App\Models\AuditLog;
use App\Models\Reservation;
use App\Services\Contracts\AdminActivityServiceInterface;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Random\RandomException;

/**
 * Controlador de Gestión de Reservas (Admin)
 *
 * Responsabilidad única: Gestión administrativa de reservas
 *
 * Métodos:
 * - index() - Lista de reservas
 * - cancel() - Cancelar reserva desde backoffice
 */
final class ReservationController
{
    private AdminActivityServiceInterface $activityService;
    private ResponseFactory $response;
    private ReservationTransformer $reservationTransformer;

    private const ADMIN_RESERVATIONS_URL = '/admin/reservations';

    public function __construct(?AdminActivityServiceInterface $activityService = null, ?ResponseFactory $response = null, ?ReservationTransformer $reservationTransformer = null)
    {
        $this->activityService = $activityService ?? Container::make(AdminActivityServiceInterface::class);
        $this->response = $response ?? new ResponseFactory();
        $this->reservationTransformer = $reservationTransformer ?? new ReservationTransformer();
    }

    /**
     * GET /admin/reservas
     * Lista de todas las reservas
     *
     * @throws RandomException
     */
    public function index(): ?ResponseInterface
    {
        // Obtener reservas desde el servicio
        $reservations = $this->reservationTransformer->collection(
            $this->activityService->getReservationsWithDetails(100)
        );

        View::render('admin/reservations/index', [
            'titulo' => 'Gestión de Reservas',
            'reservations' => $reservations,
            'csrf_token' => Csrf::token(),
            'extraJs' => ['admin/admin-reservations.js'],
        ], ['admin/admin-reservations.css'], 'backoffice');

        return null;
    }

    /**
     * POST /admin/reservas/{reservationId}/cancel
     * Cancelar reserva desde backoffice
     *
     * @param integer|array $reservationId
     *
     * @throws BusinessRuleException
     * @throws RandomException
     * @throws JsonException
     */
    public function cancel(array|int $reservationId): ResponseInterface
    {
        if (!Csrf::validate()) {
            Flash::error('Token de seguridad inválido');

            return $this->response->redirect(self::ADMIN_RESERVATIONS_URL);
        }

        // Normalizar parámetro que puede venir como int o como array desde el Router
        if (\is_array($reservationId)) {
            $id = (int) ($reservationId['reservationId'] ?? $reservationId['reservation_id'] ?? $reservationId['id'] ?? ($reservationId[0] ?? 0));
        } else {
            $id = $reservationId;
        }

        if ($id <= 0) {
            Flash::error('Identificador de reserva inválido');

            return $this->response->redirect(self::ADMIN_RESERVATIONS_URL);
        }

        $reservationModel = new Reservation();

        $result = $reservationModel->cancel($id);

        if (!$result->ok) {
            throw BusinessRuleException::withMessage($result->error, 'cancel_failed');
        }

        // Registrar acción en audit log
        AuditLog::log('cancel_reservation', 'reservation', $id, null, ['cancelled_at' => \date('Y-m-d H:i:s')]);

        Flash::success('Reserva cancelada correctamente');

        return $this->response->redirect(self::ADMIN_RESERVATIONS_URL);
    }

    /**
     * POST /admin/reservas/{reservationId}/confirm
     * Confirmar reserva pendiente desde backoffice
     *
     * @param integer|array $reservationId
     *
     * @throws BusinessRuleException
     * @throws RandomException
     * @throws JsonException
     */
    public function confirm(array|int $reservationId): ResponseInterface
    {
        if (!Csrf::validate()) {
            Flash::error('Token de seguridad inválido');

            return $this->response->redirect(self::ADMIN_RESERVATIONS_URL);
        }

        // Normalizar parámetro
        if (\is_array($reservationId)) {
            $id = (int) ($reservationId['reservationId'] ?? $reservationId['reservation_id'] ?? $reservationId['id'] ?? ($reservationId[0] ?? 0));
        } else {
            $id = $reservationId;
        }

        if ($id <= 0) {
            Flash::error('Identificador de reserva inválido');

            return $this->response->redirect(self::ADMIN_RESERVATIONS_URL);
        }

        $reservationModel = new Reservation();

        $result = $reservationModel->confirm($id);

        if (!$result->ok) {
            throw BusinessRuleException::withMessage($result->error, 'confirm_failed');
        }

        // Registrar acción en audit log
        AuditLog::log('confirm_reservation', 'reservation', $id, null, ['confirmed_at' => \date('Y-m-d H:i:s')]);

        Flash::success('Reserva confirmada correctamente');

        return $this->response->redirect(self::ADMIN_RESERVATIONS_URL);
    }
}
