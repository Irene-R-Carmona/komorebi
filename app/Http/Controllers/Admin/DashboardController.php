<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Container;
use App\Core\Env;
use App\Core\Logger;
use App\Core\Session;
use App\Core\View;
use App\Http\Transformers\ReservationTransformer;
use App\Services\Contracts\AdminActivityServiceInterface;
use App\Services\Contracts\AdminStatisticsServiceInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * Controlador de Dashboard Administrativo
 *
 * Responsabilidad única: Mostrar el dashboard principal del admin
 * con estadísticas generales y actividad reciente.
 */
final class DashboardController
{
    private AdminStatisticsServiceInterface $statisticsService;
    private AdminActivityServiceInterface $activityService;
    private ReservationTransformer $reservationTransformer;

    public function __construct(
        ?AdminStatisticsServiceInterface $statisticsService = null,
        ?AdminActivityServiceInterface $activityService = null,
        ?ReservationTransformer $reservationTransformer = null
    ) {
        $this->statisticsService = $statisticsService ?? Container::make(AdminStatisticsServiceInterface::class);
        $this->activityService = $activityService ?? Container::make(AdminActivityServiceInterface::class);
        $this->reservationTransformer = $reservationTransformer ?? new ReservationTransformer();
    }

    /**
     * GET /admin/dashboard
     * Dashboard principal del administrador
     */
    public function index(ServerRequestInterface $request): ?ResponseInterface
    {
        try {
            // Logging informativo solo en entorno local
            if (Env::get('APP_ENV') === 'local') {
                Logger::info('[DashboardController::index] Accessing admin dashboard');
                Logger::info('[DashboardController::index] User ID: ' . Session::userId());
                Logger::info('[DashboardController::index] User Roles: ' . \json_encode(Session::get('user_roles', [])));
            }

            // Obtener estadísticas y datos del dashboard desde el servicio
            $stats = $this->statisticsService->getSystemStatistics();

            if (Env::get('APP_ENV') === 'local') {
                Logger::debug('[DashboardController::index] Stats loaded: ' . \json_encode($stats));
            }

            $recentReservations = $this->reservationTransformer->collection(
                $this->activityService->getRecentReservations(10)
            );

            if (Env::get('APP_ENV') === 'local') {
                Logger::debug('[DashboardController::index] Reservations loaded: ' . \count($recentReservations));
            }

            // Obtener actividad reciente REAL del sistema
            $recentActivity = $this->activityService->getRecentActivity(6);

            // Obtener estado del sistema VERIFICADO
            $systemStatus = $this->activityService->getSystemStatus();

            // Generar saludo según hora del día
            $hour = (int) \date('H');
            $greeting = match (true) {
                $hour >= 5 && $hour < 12 => 'Buenos días',
                $hour >= 12 && $hour < 19 => 'Buenas tardes',
                default => 'Buenas noches'
            };

            // Datos del gráfico (últimos 7 días de reservas)
            $chartData = $this->activityService->getReservationsChartData();

            View::render('admin/home', [
                'titulo' => 'Dashboard - Admin',
                'greeting' => $greeting,
                'userName' => Session::get('user_name', 'Admin'),
                'stats' => $stats,
                'recent_reservations' => $recentReservations,
                'recent_activity' => $recentActivity,
                'chart_data' => $chartData,
                'system_status' => $systemStatus,
                'extraJs' => ['admin/admin-dashboard.js'],
            ], ['admin/admin-dashboard.css'], 'backoffice');
        } catch (Throwable $e) { // NOSONAR
            if (Env::get('APP_ENV') === 'local') {
                Logger::error('[DashboardController::index] FATAL ERROR: ' . $e->getMessage(), ['exception' => $e->getMessage()]);
                Logger::error('[DashboardController::index] Stack trace: ' . $e->getTraceAsString(), ['trace' => $e->getTraceAsString()]);
            }

            // Mostrar página de error amigable
            \http_response_code(500);
            View::render('errors/500', [
                'titulo' => 'Error del Servidor',
                'message' => 'Error al cargar el dashboard. Por favor, contacta al administrador.',
                'details' => Env::get('APP_ENV') === 'local' ? $e->getMessage() : null,
            ]);
        }

        return null;
    }
}
