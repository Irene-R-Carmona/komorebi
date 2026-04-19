<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manager;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Http\ResponseFactory;
use App\Core\Session;
use App\Core\View;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Contracts\StaffShiftServiceInterface;
use App\Support\TimeHelper;
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

    private ResponseFactory $response;

    private StaffShiftServiceInterface $shiftService;

    public function __construct(
        ?UserRepositoryInterface $userRepo = null,
        ?ResponseFactory $response = null,
        ?StaffShiftServiceInterface $shiftService = null,
    ) {
        $this->userRepo     = $userRepo ?? Container::make(UserRepositoryInterface::class);
        $this->response     = $response ?? new ResponseFactory();
        $this->shiftService = $shiftService ?? Container::make(StaffShiftServiceInterface::class);
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
            'titulo' => 'Gestión de Staff',
            'staff' => $staff,
            'shifts' => $shifts,
            'cafe_id' => $cafeId,
            'csrf_token' => Csrf::token(),
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
        $shiftHistory = $historyResult->ok ? $historyResult->data : [];

        View::render('manager/staff/show', [
            'titulo' => 'Detalle de Staff',
            'staff' => $staffMember,
            'shift_history' => $shiftHistory,
            'csrf_token' => Csrf::token(),
        ], ['manager/staff.css'], 'backoffice');

        return null;
    }

    /**
     * POST /manager/staff/assign-shift
     *
     * Asignar turno a un staff member
     */
    public function assignShift(ServerRequestInterface $request): ResponseInterface
    {
        $user = Session::user();
        $cafeId = $user['cafe_id'] ?? null;

        if (!$cafeId) {
            return $this->response->json([
                'success' => false,
                'error' => 'No tienes un café asignado',
            ], 403);
        }

        $body = $request->getParsedBody();
        $userId = (int) ($body['user_id'] ?? 0);
        $shiftDate = $body['shift_date'] ?? '';
        $shiftStart = $body['shift_start'] ?? '';
        $shiftEnd = $body['shift_end'] ?? '';
        $notes = $body['notes'] ?? null;

        // Validaciones
        if ($userId <= 0) {
            return $this->response->json([
                'success' => false,
                'error' => 'Staff member no válido',
            ], 400);
        }

        if (empty($shiftDate) || !\preg_match('/^\d{4}-\d{2}-\d{2}$/', $shiftDate)) {
            return $this->response->json([
                'success' => false,
                'error' => 'Fecha de turno inválida (formato: YYYY-MM-DD)',
            ], 400);
        }

        if (empty($shiftStart) || !TimeHelper::isValid($shiftStart)) {
            return $this->response->json([
                'success' => false,
                'error' => 'Hora de inicio inválida (formato: HH:MM)',
            ], 400);
        }

        if (empty($shiftEnd) || !TimeHelper::isValid($shiftEnd)) {
            return $this->response->json([
                'success' => false,
                'error' => 'Hora de fin inválida (formato: HH:MM)',
            ], 400);
        }

        if (TimeHelper::compare($shiftStart, $shiftEnd) >= 0) {
            return $this->response->json([
                'success' => false,
                'error' => 'La hora de inicio debe ser menor que la hora de fin',
            ], 400);
        }

        if (!$this->userRepo->existsInCafe($userId, $cafeId)) {
            return $this->response->json([
                'success' => false,
                'error' => 'El staff member no pertenece a tu café',
            ], 403);
        }

        // Asignar turno via service (incluye verificación de solapamiento)
        $result = $this->shiftService->assignShift(
            $userId,
            $cafeId,
            $shiftDate,
            TimeHelper::normalize($shiftStart),
            TimeHelper::normalize($shiftEnd),
            $notes,
            (int) $user['id'],
        );

        if (!$result->ok) {
            $status = $result->code === 'shift_overlap' ? 400 : 500;

            return $this->response->json([
                'success' => false,
                'error' => $result->error ?? 'Error al asignar turno',
            ], $status);
        }

        return $this->response->json([
            'success' => true,
            'message' => 'Turno asignado correctamente',
            'shift_id' => $result->data['shift_id'],
        ]);
    }

    /**
     * POST /manager/staff/edit-permissions
     *
     * Modificar permisos específicos de un staff member (placeholder - RBAC avanzado)
     */
    public function editPermissions(ServerRequestInterface $request): ResponseInterface
    {
        $user = Session::user();
        $cafeId = $user['cafe_id'] ?? null;

        if (!$cafeId) {
            return $this->response->json([
                'success' => false,
                'error' => 'No tienes un café asignado',
            ], 403);
        }

        // Placeholder: en implementación completa se modificarían permisos en user_permissions
        return $this->response->json([
            'success' => false,
            'error' => 'Funcionalidad en desarrollo (RBAC avanzado)',
        ], 501);
    }

    /**
     * GET /manager/staff/performance/{id}
     *
     * Métricas de performance de un staff member
     */
    public function viewPerformance(int $userId): ResponseInterface
    {
        $user = Session::user();
        $cafeId = $user['cafe_id'] ?? null;

        if (!$cafeId) {
            return $this->response->json([
                'success' => false,
                'error' => 'No tienes un café asignado',
            ], 403);
        }

        $staffMember = $this->userRepo->getStaffBasicById($userId, $cafeId);

        if ($staffMember === null) {
            return $this->response->json([
                'success' => false,
                'error' => 'Staff member no encontrado o no pertenece a tu café',
            ], 404);
        }

        // Obtener métricas via service
        $metricsResult = $this->shiftService->getPerformanceMetrics($userId, $cafeId);

        if (!$metricsResult->ok) {
            return $this->response->json([
                'success' => false,
                'error' => $metricsResult->error ?? 'Error al obtener métricas',
            ], 500);
        }

        return $this->response->json([
            'success' => true,
            'staff' => $staffMember,
            'metrics' => $metricsResult->data,
        ]);
    }

}
