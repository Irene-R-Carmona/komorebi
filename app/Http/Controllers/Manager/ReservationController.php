<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manager;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\View;
use App\Domain\DTO\PaginationParams;
use App\Repositories\Contracts\ReservationRepositoryInterface;
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
    private ReservationRepositoryInterface $reservationRepo;

    public function __construct(?ReservationRepositoryInterface $reservationRepo = null)
    {
        $this->reservationRepo = $reservationRepo ?? Container::make(ReservationRepositoryInterface::class);
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
}
