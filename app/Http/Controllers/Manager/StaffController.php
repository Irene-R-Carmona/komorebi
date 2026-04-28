<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manager;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\View;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\UserRepository;
use App\Services\Contracts\StaffShiftServiceInterface;
use App\Services\StaffShiftService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Staff Management Controller (Manager scope)
 *
 * Permite al manager gestionar el personal de su café asignado.
 * Scope: Solo puede gestionar staff de user.cafe_id
 */
final class StaffController
{
    private UserRepositoryInterface $userRepo;

    private StaffShiftServiceInterface $shiftService;

    public function __construct(
        ?UserRepositoryInterface $userRepo = null,
        ?StaffShiftServiceInterface $shiftService = null,
    ) {
        $this->userRepo = $userRepo ?? Container::make(UserRepository::class);
        $this->shiftService = $shiftService ?? Container::make(StaffShiftService::class);
    }

    /**
     * GET /manager/staff
     *
     * Listado de staff del café con filtros
     * Vista con tabs: Staff Activo, Turnos, Historial
     */
    public function index(ServerRequestInterface $request): ?ResponseInterface
    {
        $user = Session::user();
        $cafeId = $user['cafe_id'] ?? null;

        if (!$cafeId) {
            View::render('errors/403', [
                'message' => 'No tienes un café asignado.',
            ]);

            return null;
        }

        // Obtener staff del café (users con cafe_id y role staff)
        $staff = $this->userRepo->getStaffByCafe($cafeId);

        // Obtener turnos de la semana actual
        $weekResult = $this->shiftService->getWeekShifts($cafeId);
        $shifts = $weekResult->ok ? $weekResult->data : [];

        View::render('manager/staff/index', [
            'titulo'     => 'Gestión de Staff',
            'staff'      => $staff,
            'shifts'     => $shifts,
            'cafe_id'    => $cafeId,
            'csrf_token' => Csrf::token(),
            'extraJs'    => ['manager/manager-staff.js'],
        ], ['manager/staff.css'], 'backoffice');

        return null;
    }

    /**
     * GET /manager/staff/{id}
     *
     * Detalle de un staff member + historial de turnos
     */
    public function show(ServerRequestInterface $request, int $userId): ?ResponseInterface
    {
        $user = Session::user();
        $cafeId = $user['cafe_id'] ?? null;

        if (!$cafeId) {
            View::render('errors/403', [
                'message' => 'No tienes un café asignado.',
            ]);

            return null;
        }

        // Obtener datos del staff member
        $staffMember = $this->userRepo->getStaffById($userId, $cafeId);

        if (!$staffMember) {
            View::render('errors/404', [
                'message' => 'Staff member no encontrado o no pertenece a tu café.',
            ]);

            return null;
        }

        // Historial de turnos (últimos 30 días)
        $historyResult = $this->shiftService->getStaffHistory($userId, $cafeId);
        $shiftHistory  = $historyResult->ok ? $historyResult->data : [];

        // Métricas de performance (PHP-injected — no AJAX)
        $metricsResult = $this->shiftService->getPerformanceMetrics($userId, $cafeId);
        $metrics       = $metricsResult->ok ? ($metricsResult->data ?? []) : [];

        View::render('manager/staff/show', [
            'titulo'        => 'Detalle de Staff',
            'staff'         => $staffMember,
            'shift_history' => $shiftHistory,
            'metrics'       => $metrics,
            'csrf_token'    => Csrf::token(),
        ], ['manager/staff.css'], 'backoffice');

        return null;
    }
}
