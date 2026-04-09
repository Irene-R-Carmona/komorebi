<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manager;

use App\Core\Csrf;
use App\Core\Session;
use App\Core\View;
use App\Repositories\ReservationRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Controlador de Reservas del Manager
 *
 * Responsabilidad: Listado y filtrado de reservas del café asignado.
 * Permisos: role = 'manager'
 */
final class ReservationController
{
    private ReservationRepository $reservationRepo;

    public function __construct(?ReservationRepository $reservationRepo = null)
    {
        $this->reservationRepo = $reservationRepo ?? new ReservationRepository();
    }

    /**
     * GET /manager/reservations
     * Listado filtrable de reservas del café del manager
     */
    public function index(ServerRequestInterface $request): ?ResponseInterface
    {
        $user   = Session::user();
        $cafeId = (int) ($user['cafe_id'] ?? 0);

        if (!$cafeId) {
            View::render('errors/403', [
                'message' => 'No tienes un café asignado. Contacta con el administrador.',
            ]);
            return null;
        }

        $params = $request->getQueryParams();
        $status = (isset($params['status']) && $params['status'] !== '') ? (string) $params['status'] : null;
        $date   = (isset($params['date'])   && $params['date']   !== '') ? (string) $params['date']   : null;

        $reservations = $this->reservationRepo->findByCafeWithFilters($cafeId, $status, $date);

        View::render('manager/reservations/index', [
            'titulo'       => 'Gestión de Reservas',
            'reservations' => $reservations,
            'filters'      => ['status' => $status, 'date' => $date],
            'csrf_token'   => Csrf::token(),
        ], [], 'backoffice');

        return null;
    }
}
