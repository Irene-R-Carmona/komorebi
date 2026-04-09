<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manager;

use App\Core\Csrf;
use App\Core\Session;
use App\Core\View;
use App\Services\CafeService;
use App\Services\Manager\DashboardService;
use Random\RandomException;

/**
 * Dashboard del Gestor de Café (Manager)
 *
 * Responsabilidad: Panel de control para gestores de un café específico
 *
 * Permisos requeridos: role = 'manager'
 * Alcance: Solo datos del café asignado al manager
 */
final class DashboardController
{
    private CafeService $cafeService;

    private DashboardService $dashboardService;

    public function __construct(
        ?CafeService $cafeService = null,
        ?DashboardService $dashboardService = null
    ) {
        $this->cafeService = $cafeService ?? new CafeService();
        $this->dashboardService = $dashboardService ?? new DashboardService();
    }

    /**
     * GET /manager/dashboard
     * Panel principal del gestor con métricas en tiempo real
     *
     * @throws RandomException
     */
    public function index(): void
    {
        $user = Session::user();
        $cafeId = $user['cafe_id'] ?? null;

        if (!$cafeId) {
            View::render('errors/403', [
                'message' => 'No tienes un café asignado. Contacta con el administrador.',
            ]);

            return;
        }

        // Obtener datos del café asignado
        $cafe = $this->cafeService->getById($cafeId);

        // Obtener métricas en tiempo real
        $stats = $this->dashboardService->getDashboardMetrics($cafeId);

        // Datos para gráficos (Chart.js)
        $chartData = [
            'weekly_revenue' => $this->formatWeeklyRevenueForChart(
                $this->dashboardService->getWeeklyRevenue($cafeId)
            ),
            'top_animals' => $this->dashboardService->getTopAnimals($cafeId, 5),
            'reservation_status' => $this->dashboardService->getReservationStatusDistribution($cafeId),
        ];

        View::render('manager/dashboard', [
            'titulo' => 'Dashboard - Gestor',
            'cafe' => $cafe,
            'stats' => $stats,
            'chartData' => $chartData,
            'csrf_token' => Csrf::token(),
        ], [], 'backoffice');
    }

    /**
     * Formatea los datos de ingresos semanales para Chart.js
     */
    private function formatWeeklyRevenueForChart(array $weeklyData): array
    {
        $labels = [];
        $data = [];

        foreach ($weeklyData as $day) {
            $labels[] = \date('d/m', \strtotime($day['date']));
            $data[] = (float) $day['revenue'];
        }

        // Rellenar días faltantes con 0 (último 7 días completos)
        while (\count($labels) < 7) {
            array_unshift($labels, '');
            array_unshift($data, 0);
        }

        return [
            'labels' => $labels,
            'data' => $data,
        ];
    }
}
