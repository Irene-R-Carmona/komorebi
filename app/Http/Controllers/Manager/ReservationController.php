<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manager;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Http\ResponseFactory;
use App\Core\Session;
use App\Core\View;
use App\Domain\DTO\PaginationParams;
use App\Domain\Reservation\ReservationStateMachine;
use App\Repositories\Contracts\ReservationRepositoryInterface;
use App\Services\Contracts\ReservationServiceInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Controlador de Reservas del Manager
 *
 * Responsabilidad: Listado, detalle y gestión de estado de reservas del café asignado.
 * Permisos: role = 'manager'
 */
final class ReservationController
{
    private ReservationRepositoryInterface $reservationRepo;
    private ReservationServiceInterface $reservationService;
    private ResponseFactory $response;

    public function __construct(
        ?ReservationRepositoryInterface $reservationRepo = null,
        ?ReservationServiceInterface $reservationService = null,
        ?ResponseFactory $response = null,
    ) {
        $this->reservationRepo = $reservationRepo ?? Container::make(ReservationRepositoryInterface::class);
        $this->reservationService = $reservationService ?? Container::make(ReservationServiceInterface::class);
        $this->response = $response ?? Container::make(ResponseFactory::class);
    }

    /**
     * GET /manager/reservations
     * Listado filtrable de reservas del café del manager
     */
    public function index(ServerRequestInterface $request): ?ResponseInterface
    {
        $user = Session::user();
        $cafeId = (int) ($user['cafe_id'] ?? 0);

        if (!$cafeId) {
            View::render('errors/403', [
                'message' => 'No tienes un café asignado. Contacta con el administrador.',
            ]);

            return null;
        }

        $params = PaginationParams::fromRequest($request);
        $query = $request->getQueryParams();
        $status = (isset($query['status']) && $query['status'] !== '') ? (string) $query['status'] : null;
        $date = (isset($query['date']) && $query['date'] !== '') ? (string) $query['date'] : null;

        $rawRows = $this->reservationRepo->findByCafeWithFilters($cafeId, $status, $date, $params->page);
        $hasNext = \count($rawRows) > 20;
        $reservations = $hasNext ? \array_slice($rawRows, 0, 20) : $rawRows;

        $meta = ['page' => $params->page, 'has_next_page' => $hasNext];
        $currentParams = $params->toQueryArray(['status' => $status ?? '', 'date' => $date ?? '']);

        View::render('manager/reservations/index', [
            'titulo' => 'Gestión de Reservas',
            'reservations' => $reservations,
            'filters' => ['status' => $status, 'date' => $date],
            'csrf_token' => Csrf::token(),
            'total' => \count($reservations),
            'meta' => $meta,
            'currentParams' => $currentParams,
        ], ['manager/manager-reservations.css'], 'backoffice');

        return null;
    }

    /**
     * GET /manager/reservations/{id}
     * Detalle de una reserva con formularios de gestión
     */
    public function show(ServerRequestInterface $request): ?ResponseInterface
    {
        $user = Session::user();
        $cafeId = (int) ($user['cafe_id'] ?? 0);
        $id = (int) ($request->getAttribute('id') ?? 0);

        if (!$id) {
            return $this->response->redirect('/manager/reservations');
        }

        $reservation = $this->reservationRepo->findByIdWithCafeDetails($id);

        if ($reservation === null) {
            Flash::error('Reserva no encontrada.');

            return $this->response->redirect('/manager/reservations');
        }

        if ((int) ($reservation['cafe_id'] ?? 0) !== $cafeId) {
            Flash::error('No tienes permiso para ver esta reserva.');

            return $this->response->redirect('/manager/reservations');
        }

        $currentStatus = (string) ($reservation['status'] ?? '');
        $validTransitions = \array_filter(
            ['confirmed', 'active', 'cancelled', 'no_show', 'completed'],
            fn (string $to) => ReservationStateMachine::isValidTransition($currentStatus, $to)
        );

        View::render('manager/reservations/show', [
            'titulo' => 'Detalle de Reserva #' . $id,
            'reservation' => $reservation,
            'valid_transitions' => \array_values($validTransitions),
            'csrf_token' => Csrf::token(),
        ], [], 'backoffice');

        return null;
    }

    /**
     * POST /manager/reservations/{id}/status
     * Cambiar estado de la reserva con justificación
     */
    public function updateStatus(ServerRequestInterface $request): ResponseInterface
    {
        $user = Session::user();
        $cafeId = (int) ($user['cafe_id'] ?? 0);
        $id = (int) ($request->getAttribute('id') ?? 0);

        if (!$id) {
            return $this->response->redirect('/manager/reservations');
        }

        $existing = $this->reservationRepo->findByIdWithCafeDetails($id);

        if ($existing === null || (int) ($existing['cafe_id'] ?? 0) !== $cafeId) {
            Flash::error('Reserva no encontrada o sin permiso.');

            return $this->response->redirect('/manager/reservations');
        }

        $body = (array) ($request->getParsedBody() ?? []);
        $newStatus = \trim((string) ($body['new_status'] ?? ''));
        $reason = \trim((string) ($body['reason'] ?? ''));

        if ($newStatus === '') {
            Flash::error('Debes seleccionar un estado destino.');

            return $this->response->redirect("/manager/reservations/{$id}");
        }

        $result = $this->reservationService->managerUpdateStatus($id, $newStatus, $reason);

        if (!$result->ok) {
            Flash::error($result->getMessage());

            return $this->response->redirect("/manager/reservations/{$id}");
        }

        Flash::success('Estado actualizado correctamente.');

        return $this->response->redirect("/manager/reservations/{$id}");
    }

    /**
     * POST /manager/reservations/{id}/refund
     * Registrar devolución de una reserva cancelada
     */
    public function processRefund(ServerRequestInterface $request): ResponseInterface
    {
        $user = Session::user();
        $cafeId = (int) ($user['cafe_id'] ?? 0);
        $id = (int) ($request->getAttribute('id') ?? 0);

        if (!$id) {
            return $this->response->redirect('/manager/reservations');
        }

        $existing = $this->reservationRepo->findByIdWithCafeDetails($id);

        if ($existing === null || (int) ($existing['cafe_id'] ?? 0) !== $cafeId) {
            Flash::error('Reserva no encontrada o sin permiso.');

            return $this->response->redirect('/manager/reservations');
        }

        $body = (array) ($request->getParsedBody() ?? []);
        $amountEuros = \trim((string) ($body['amount_euros'] ?? ''));
        $notes = \trim((string) ($body['notes'] ?? ''));

        if ($amountEuros === '' || !\is_numeric($amountEuros) || (float) $amountEuros < 0) {
            Flash::error('Importe de devolución inválido.');

            return $this->response->redirect("/manager/reservations/{$id}");
        }

        $amountCents = (int) \round((float) $amountEuros * 100);

        $result = $this->reservationService->managerRecordRefund($id, $amountCents, $notes);

        if (!$result->ok) {
            Flash::error($result->getMessage());

            return $this->response->redirect("/manager/reservations/{$id}");
        }

        Flash::success('Devolución registrada correctamente.');

        return $this->response->redirect("/manager/reservations/{$id}");
    }
}
