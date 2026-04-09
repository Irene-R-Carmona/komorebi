<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\View;
use App\Models\Reservation;
use App\Models\User;

/**
 * Controlador de Gerente - Gestión de Catálogo y Personal
 *
 * Responsabilidades:
 * - Dashboard del manager
 * - Gestión de personal (staff)
 * - Reportes de ventas y operaciones
 */
final class ManagerController
{
    private User $userModel;

    private Reservation $reservationModel;

    public function __construct()
    {
        $this->userModel = new User();
        $this->reservationModel = new Reservation();
    }

    /**
     * GET /manager/dashboard
     * Dashboard del manager
     */
    public function index(): void
    {
        $stats = [
            'staff_count' => $this->userModel->countByRole('staff'),
            'active_reservations' => $this->reservationModel->countActive(),
            'total_sales' => $this->getMonthlyRevenue(),
        ];

        View::render('backoffice/manager/dashboard', [
            'titulo' => 'Dashboard - Manager',
            'stats' => $stats,
            'extraJs' => ['admin/admin-common.js'],
        ], ['admin/admin-common.css'], 'backoffice');
    }

    /**
     * GET /manager/personal
     * Gestión de personal (staff)
     */
    public function staff(): void
    {
        $staff = $this->userModel->findByRole('staff');

        View::render('backoffice/manager/personal', [
            'titulo' => 'Gestión de Personal',
            'staff' => $staff,
            'extraJs' => ['admin/admin-common.js'],
        ], ['admin/admin-common.css'], 'backoffice');
    }

    /**
     * GET /manager/reportes
     * Reportes de operaciones
     */
    public function reports(): void
    {
        $monthlyData = $this->getMonthlyStats();

        View::render('backoffice/manager/reportes', [
            'titulo' => 'Reportes de Operaciones',
            'monthlyData' => $monthlyData,
            'extraJs' => ['admin/admin-common.js'],
        ], ['admin/admin-common.css'], 'backoffice');
    }

    /**
     * Obtiene ingresos mensuales
     */
    private function getMonthlyRevenue(): float
    {
        // Implementar lógica para obtener ingresos
        return 0.0;
    }

    /**
     * Obtiene estadísticas mensuales
     */
    private function getMonthlyStats(): array
    {
        // Implementar lógica para obtener estadísticas
        return [];
    }
}
