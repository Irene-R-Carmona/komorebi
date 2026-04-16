<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manager;

use App\Core\Http\ResponseFactory;
use App\Core\Session;
use App\Core\View;
use App\Services\Manager\DashboardService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Controlador de Reportes del Manager
 *
 * Responsabilidad: Informes y métricas del café asignado al manager.
 * Permisos: role = 'manager'
 * Seguridad: Todas las consultas están filtradas por cafe_id de sesión.
 */
final class ReportController
{
    private ResponseFactory $response;

    public function __construct(
        private DashboardService $dashboardService,
        ?ResponseFactory $response = null,
    ) {
        $this->response = $response ?? new ResponseFactory();
    }

    /**
     * GET /manager/reports
     * Dashboard de reportes y estadísticas del café
     */
    public function index(ServerRequestInterface $request): ?ResponseInterface
    {
        $user = Session::user();
        $cafeId = $user['cafe_id'] ?? null;

        if (!$cafeId) {
            View::render('errors/403', [
                'message' => 'No tienes un café asignado. Contacta con el administrador.',
            ]);

            return null;
        }

        $params = $request->getQueryParams();
        $from = $params['from'] ?? \date('Y-m-d', \strtotime('-30 days'));
        $to = $params['to'] ?? \date('Y-m-d');

        $metrics = $this->dashboardService->getDashboardMetrics($cafeId);
        $statusDistribution = $this->dashboardService->getReservationStatusDistribution($cafeId);
        $reservations = $this->dashboardService->getReservationReport($cafeId, $from, $to);

        View::render('manager/reports/index', [
            'titulo' => 'Reportes y Analíticas',
            'reservationsToday' => $metrics['reservations_today'] ?? 0,
            'revenueToday' => $metrics['revenue_today'] ?? 0.0,
            'monthlyReservations' => $metrics['monthly_reservations'] ?? 0,
            'avgRating' => $metrics['avg_rating'] ?? 0.0,
            'pendingReservations' => $metrics['pending_reservations'] ?? 0,
            'statusDistribution' => $statusDistribution,
            'reservations' => $reservations,
            'from' => $from,
            'to' => $to,
        ], [], 'backoffice');

        return null;
    }

    /**
     * GET /manager/reports/export
     * Exporta las reservas del período en CSV (solo el café del manager)
     */
    public function exportReportes(ServerRequestInterface $request): ResponseInterface
    {
        $user = Session::user();
        $cafeId = $user['cafe_id'] ?? null;

        if (!$cafeId) {
            return $this->response->json(['error' => 'Sin café asignado'], 403);
        }

        $params = $request->getQueryParams();
        $from = $params['from'] ?? null;
        $to = $params['to'] ?? null;

        $rows = $this->dashboardService->getReservationReport($cafeId, $from, $to, null);

        $csv = "Fecha,Estado,Personas,Importe,Pago\n";
        foreach ($rows as $row) {
            $csv .= \implode(',', [
                '"' . \str_replace('"', '""', (string) ($row['fecha'] ?? '')) . '"',
                '"' . \str_replace('"', '""', (string) ($row['estado'] ?? '')) . '"',
                (int) ($row['personas'] ?? 0),
                \number_format((float) ($row['importe'] ?? 0), 2, '.', ''),
                '"' . \str_replace('"', '""', (string) ($row['pago'] ?? '')) . '"',
            ]) . "\n";
        }

        return $this->response->html($csv, 200)
            ->withHeader('Content-Type', 'text/csv; charset=utf-8')
            ->withHeader('Content-Disposition', 'attachment; filename="reporte-manager-' . \date('Y-m-d') . '.csv"');
    }
}
