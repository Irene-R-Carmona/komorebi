<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Core\Http\ResponseFactory;
use App\Core\Session;
use App\Http\Controllers\Api\AbstractApiController;
use App\Services\Contracts\DashboardServiceInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * API Manager Controller (v1)
 *
 * Endpoints de API para el backoffice del Manager.
 * Requiere autenticación y rol 'manager'.
 */
final class ManagerController extends AbstractApiController
{
    public function __construct(
        ResponseFactory $response,
        private readonly DashboardServiceInterface $dashboardService,
    ) {
        parent::__construct($response);
    }

    /**
     * GET /api/v1/manager/dashboard/stats
     *
     * Devuelve métricas en tiempo real del dashboard
     * Para uso con polling Alpine.js (cada 30s)
     */
    public function stats(ServerRequestInterface $request): ResponseInterface
    {
        $cafeId = Session::userCafeId();

        if (!$cafeId) {
            return $this->forbidden('No tienes un café asignado', 'cafe_not_assigned');
        }

        $metrics = $this->dashboardService->getDashboardMetrics($cafeId);

        return $this->success(\array_merge($metrics, ['timestamp' => \time()]));
    }

    /**
     * GET /api/v1/manager/dashboard/weekly-revenue
     *
     * Devuelve ingresos de los últimos 7 días (para gráfico)
     */
    public function weeklyRevenue(ServerRequestInterface $request): ResponseInterface
    {
        $cafeId = Session::userCafeId();

        if (!$cafeId) {
            return $this->forbidden('No tienes un café asignado', 'cafe_not_assigned');
        }

        $weeklyData = $this->dashboardService->getWeeklyRevenue($cafeId);

        $labels = [];
        $data = [];
        foreach ($weeklyData as $day) {
            $labels[] = \date('d/m', \strtotime($day['date']));
            $data[] = (float) $day['revenue'];
        }

        return $this->success([
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Ingresos (¥)',
                    'data' => $data,
                    'borderColor' => 'rgb(75, 192, 192)',
                    'backgroundColor' => 'rgba(75, 192, 192, 0.2)',
                ],
            ],
        ]);
    }

    /**
     * GET /api/v1/manager/dashboard/top-animals
     *
     * Devuelve los 5 animales más populares
     */
    public function topAnimals(ServerRequestInterface $request): ResponseInterface
    {
        $cafeId = Session::userCafeId();

        if (!$cafeId) {
            return $this->forbidden('No tienes un café asignado', 'cafe_not_assigned');
        }

        return $this->success($this->dashboardService->getTopAnimals($cafeId, 5));
    }

    /**
     * GET /api/v1/manager/dashboard/reservation-status
     *
     * Distribución de estados de reservas (últimos 30 días)
     */
    public function reservationStatus(ServerRequestInterface $request): ResponseInterface
    {
        $cafeId = Session::userCafeId();

        if (!$cafeId) {
            return $this->forbidden('No tienes un café asignado', 'cafe_not_assigned');
        }

        $distribution = $this->dashboardService->getReservationStatusDistribution($cafeId);

        $backgroundColor = [
            'pending' => '#FFA726',
            'confirmed' => '#66BB6A',
            'active' => '#42A5F5',
            'completed' => '#26C6DA',
            'cancelled' => '#EF5350',
            'no_show' => '#8D6E63',
        ];

        $labels = [];
        $data = [];
        foreach ($distribution as $item) {
            $labels[] = \ucfirst($item['status']);
            $data[] = (int) $item['count'];
        }

        return $this->success([
            'labels' => $labels,
            'datasets' => [
                [
                    'data' => $data,
                    'backgroundColor' => \array_values($backgroundColor),
                ],
            ],
        ]);
    }
}
