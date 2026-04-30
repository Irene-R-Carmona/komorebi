<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Container;
use App\Core\View;
use App\Services\Contracts\AdminActivityServiceInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Controlador de Gestión de Reservas (Admin)
 *
 * Responsabilidad única: Vista SSR de lista de reservas
 */
final class ReservationController
{
    private AdminActivityServiceInterface $activityService;

    public function __construct(
        ?AdminActivityServiceInterface $activityService = null,
    ) {
        $this->activityService = $activityService ?? Container::make(AdminActivityServiceInterface::class);
    }

    /**
     * GET /admin/reservas
     * Lista de reservas con filtros server-side (HDA).
     */
    public function index(ServerRequestInterface $request): ?ResponseInterface
    {
        $q = $request->getQueryParams();
        $search = \trim((string) ($q['search'] ?? ''));
        $status = \trim((string) ($q['status'] ?? ''));
        $cafe = \trim((string) ($q['cafe'] ?? ''));
        $dateFrom = \trim((string) ($q['date_from'] ?? ''));
        $dateTo = \trim((string) ($q['date_to'] ?? ''));
        $page = \max(1, (int) ($q['page'] ?? 1));

        $all = $this->activityService->getReservationsWithDetails(500);
        $all = $all->ok ? $all->data : [];

        $stats = [
            'total' => \count($all),
            'confirmed' => \count(\array_filter($all, static fn ($r) => ($r['status'] ?? '') === 'confirmed')),
            'pending' => \count(\array_filter($all, static fn ($r) => ($r['status'] ?? '') === 'pending')),
            'cancelled' => \count(\array_filter($all, static fn ($r) => ($r['status'] ?? '') === 'cancelled')),
        ];

        $cafeNames = \array_values(\array_unique(\array_filter(\array_column($all, 'cafe_name'))));
        \sort($cafeNames);

        $filtered = \array_values(\array_filter(
            $all,
            fn (array $r) => $this->matchesFilters($r, $search, $status, $cafe, $dateFrom, $dateTo),
        ));

        $perPage = 20;
        $reservations = \array_slice($filtered, ($page - 1) * $perPage, $perPage);

        $currentParams = \array_filter([
            'search' => $search,
            'status' => $status,
            'cafe' => $cafe,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ], static fn ($v) => $v !== '');

        View::render('admin/reservations/index', [
            'titulo' => 'Gestión de Reservas | Komorebi Admin',
            'reservations' => $reservations,
            'stats' => $stats,
            'cafeNames' => $cafeNames,
            'meta' => ['page' => $page, 'has_next_page' => ($page * $perPage) < \count($filtered)],
            'currentParams' => $currentParams,
            'extraJs' => ['admin/admin-reservations.js'],
        ], ['admin/admin-reservations.css'], 'backoffice');

        return null;
    }

    private function matchesFilters(array $r, string $search, string $status, string $cafe, string $dateFrom, string $dateTo): bool
    {
        $statusOk = $status === '' || ($r['status'] ?? '') === $status;
        $cafeOk = $cafe === '' || ($r['cafe_name'] ?? '') === $cafe;
        $dateFromOk = $dateFrom === '' || ($r['reservation_date'] ?? '') >= $dateFrom;
        $dateToOk = $dateTo === '' || ($r['reservation_date'] ?? '') <= $dateTo;
        $searchOk = $search === '' || \str_contains(
            \strtolower(
                ($r['customer_name'] ?? '') . ' ' .
                    ($r['customer_email'] ?? '') . ' ' .
                    ($r['cafe_name'] ?? '') . ' ' .
                    (string) ($r['id'] ?? '')
            ),
            \strtolower($search),
        );

        return $statusOk && $cafeOk && $dateFromOk && $dateToOk && $searchOk;
    }
}
