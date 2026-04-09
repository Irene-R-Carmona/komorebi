<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Env;
use App\Core\Logger;
use App\Core\Session;
use App\Core\View;
use App\Services\AdminService;
use Exception;
use Throwable;

/**
 * Controlador de Dashboard Administrativo
 *
 * Responsabilidad única: Mostrar el dashboard principal del admin
 * con estadísticas generales y actividad reciente.
 */
final class DashboardController
{
    private AdminService $adminService;

    public function __construct(?AdminService $adminService = null)
    {
        $this->adminService = $adminService ?? new AdminService();
    }

    /**
     * GET /admin/dashboard
     * Dashboard principal del administrador
     */
    public function index(): void
    {
        try {
            // Logging informativo solo en entorno local
            if (Env::get('APP_ENV') === 'local') {
                Logger::info('[DashboardController::index] Accessing admin dashboard');
                Logger::info('[DashboardController::index] User ID: ' . Session::userId());
                Logger::info('[DashboardController::index] User Roles: ' . \json_encode(Session::get('user_roles', [])));
            }

            // Obtener estadísticas y datos del dashboard desde el servicio
            $stats = $this->adminService->getSystemStatistics();

            if (Env::get('APP_ENV') === 'local') {
                Logger::debug('[DashboardController::index] Stats loaded: ' . \json_encode($stats));
            }

            $recentReservations = $this->adminService->getRecentReservations(10);

            if (Env::get('APP_ENV') === 'local') {
                Logger::debug('[DashboardController::index] Reservations loaded: ' . \count($recentReservations));
            }

            // Obtener actividad reciente REAL del sistema
            $recentActivity = $this->adminService->getRecentActivity(6);

            // Obtener estado del sistema VERIFICADO
            $systemStatus = $this->adminService->getSystemStatus();

            // Generar saludo según hora del día
            $hour = (int) \date('H');
            $greeting = match (true) {
                $hour >= 5 && $hour < 12 => 'Buenos días',
                $hour >= 12 && $hour < 19 => 'Buenas tardes',
                default => 'Buenas noches'
            };

            // Datos del gráfico (últimos 7 días de reservas)
            $chartData = $this->generateChartData();

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
        } catch (Throwable $e) {
            if (Env::get('APP_ENV') === 'local') {
                Logger::error('[DashboardController::index] FATAL ERROR: ' . $e->getMessage());
                Logger::error('[DashboardController::index] Stack trace: ' . $e->getTraceAsString());
            }

            // Mostrar página de error amigable
            \http_response_code(500);
            View::render('errors/500', [
                'titulo' => 'Error del Servidor',
                'message' => 'Error al cargar el dashboard. Por favor, contacta al administrador.',
                'details' => Env::get('APP_ENV') === 'local' ? $e->getMessage() : null,
            ]);
        }
    }

    /**
     * Genera datos del gráfico basados en reservas reales de los últimos 7 días
     *
     * @return array{labels: array, values: array}
     */
    private function generateChartData(): array
    {
        try {
            // Obtener conteo de reservas por día (últimos 7 días)
            $labels = [];
            $values = [];

            $daysEs = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];

            for ($i = 6; $i >= 0; $i--) {
                $date = \date('Y-m-d', \strtotime("-$i days"));
                $dayOfWeek = (int) \date('w', \strtotime($date));
                $labels[] = $daysEs[$dayOfWeek];

                // Contar reservas de ese día
                $stmt = $this->adminService->getDatabase()->prepare(
                    'SELECT COUNT(*) FROM reservations WHERE DATE(created_at) = :date'
                );
                $stmt->execute(['date' => $date]);
                $values[] = (int) $stmt->fetchColumn();
            }

            return ['labels' => $labels, 'values' => $values];
        } catch (Exception $e) {
            Logger::error('[DashboardController] Error generating chart data: ' . $e->getMessage());

            // Retornar datos vacíos en caso de error
            return [
                'labels' => ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'],
                'values' => [0, 0, 0, 0, 0, 0, 0],
            ];
        }
    }
}
