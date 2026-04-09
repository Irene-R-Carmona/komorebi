<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reception;

use App\Core\Flash;
use App\Core\Http\ResponseFactory;
use App\Core\Middleware;
use App\Core\Session;
use App\Core\View;
use App\Exceptions\ValidationException;
use App\Services\ContextService;
use App\Services\ReceptionService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * Controlador de Recepción
 *
 * Gestiona la recepción de huéspedes: check-in, check-out y trackers.
 */
final class ReceptionController
{
    private ReceptionService $service;

    private ResponseFactory $response;

    public function __construct(
        ?ReceptionService $service = null,
        ?ResponseFactory $response = null,
    ) {
        Middleware::auth();
        $this->service = $service ?? new ReceptionService();
        $this->response = $response ?? new ResponseFactory();
    }

    /**
     * GET /ops/reception
     * Muestra el panel de recepción con reservas próximas y grupos activos.
     *
     * @throws ValidationException Si falta contexto de sede
     */
    public function index(): void
    {
        $cafeId = ContextService::getCafeId();

        if ($cafeId === null) {
            throw ValidationException::withMessage('Recepción requiere un contexto de sede');
        }

        // Obtener datos del servicio
        $reservasRaw = $this->service->getPendingArrivals($cafeId);
        $activeGroups = $this->service->getActiveGroups($cafeId);
        $freeTrackers = $this->service->getAvailableTrackers($cafeId);
        $capInfo = $this->service->getCapacityInfo($cafeId);

        // Procesar reservas para presentación
        $reservasUI = $this->processReservationsForDisplay($reservasRaw);

        // Calcular ocupación actual
        $ocupacion = \array_sum(\array_column($activeGroups, 'guests'));

        // Guardar nombre de sede en sesión para el layout
        Session::set('user_cafe_name', ContextService::getCafeName());

        // Renderizar vista
        View::render('reception/index', [
            'titulo' => 'Recepción - ' . ContextService::getCafeName(),
            'reservas' => $reservasUI,
            'active_groups' => $activeGroups,
            'free_trackers' => $freeTrackers,
            'ocupacion' => $ocupacion,
            'cap_max' => $capInfo['capacity_max'] ?? 0,
        ], ['workspaces/reception.css'], 'reception');
    }

    /**
     * GET /ops/reception/reservations
     * Lista las reservas del día actual para la sede activa.
     *
     * @throws ValidationException Si falta contexto de sede
     */
    public function todayReservations(ServerRequestInterface $request): ?ResponseInterface
    {
        $cafeId = ContextService::getCafeId();

        if ($cafeId === null) {
            throw ValidationException::withMessage('Recepción requiere un contexto de sede');
        }

        $reservations = $this->service->getPendingArrivals($cafeId);

        View::render('reception/index', [
            'titulo' => 'Reservas de hoy - ' . ContextService::getCafeName(),
            'reservations' => $reservations,
        ], [], 'reception');

        return null;
    }

    /**
     * POST /ops/reception/reservations/{id}/checkin
     * Realiza check-in de una reserva.
     *
     * @throws Throwable
     */
    public function checkIn(ServerRequestInterface $request, int $id): ResponseInterface
    {
        $body = $request->getParsedBody();
        $trackId = isset($body['tracker_id']) ? (int) $body['tracker_id'] : 0;

        if ($id <= 0 || $trackId <= 0) {
            Flash::error('Parámetros requeridos: id y tracker_id.');

            return $this->response->redirect('/ops/reception');
        }

        $result = $this->service->processCheckin($id, $trackId);

        if (!$result->ok) {
            Flash::error($result->getMessage());
            return $this->response->redirect('/ops/reception');
        }

        Flash::success('Check-in realizado.');

        return $this->response->redirect('/ops/reception');
    }

    /**
     * POST /ops/reception/reservations/{id}/checkout
     * Realiza check-out de una reserva.
     *
     * @throws Throwable
     */
    public function checkOut(ServerRequestInterface $request, int $id): ResponseInterface
    {
        if ($id <= 0) {
            Flash::error('Identificador de reserva inválido.');

            return $this->response->redirect('/ops/reception');
        }

        $result = $this->service->processCheckout($id);

        if (!$result->ok) {
            Flash::error($result->getMessage());
            return $this->response->redirect('/ops/reception');
        }

        Flash::success('Check-out realizado.');

        return $this->response->redirect('/ops/reception');
    }

    // ─────────────────────────────────────────────────────────────
    // Helpers privados
    // ─────────────────────────────────────────────────────────────

    private function processReservationsForDisplay(array $reservasRaw): array
    {
        $reservasUI = [];
        $ahora = \time();

        foreach ($reservasRaw as $r) {
            $horaReserva = \strtotime($r['reservation_date'] . ' ' . $r['reservation_time']);
            $diffMinutos = (int) \round(($horaReserva - $ahora) / 60);

            if ($diffMinutos < -15) {
                $r['ui_state'] = 'late';
                $r['ui_label'] = 'RETRASO';
            } elseif ($diffMinutos <= 15) {
                $r['ui_state'] = 'now';
                $r['ui_label'] = 'AHORA';
            } else {
                $r['ui_state'] = 'future';
                $r['ui_label'] = 'LLEGADA';
            }

            $r['ui_time'] = \substr($r['reservation_time'], 0, 5);
            $reservasUI[] = $r;
        }

        return $reservasUI;
    }
}
