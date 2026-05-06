<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manager;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Http\ResponseFactory;
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

    private ResponseFactory $response;

    private StaffShiftServiceInterface $shiftService;

    public function __construct(
        ?UserRepositoryInterface $userRepo = null,
        ?ResponseFactory $response = null,
        ?StaffShiftServiceInterface $shiftService = null,
    ) {
        $this->userRepo = $userRepo ?? Container::make(UserRepository::class);
        $this->response = $response ?? Container::make(ResponseFactory::class);
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

        // Semana solicitada (offset en semanas desde la actual)
        $queryParams = $request->getQueryParams();
        $weekOffset = \max(-52, \min(52, (int) ($queryParams['week'] ?? 0)));

        // Obtener turnos de la semana
        $weekResult = $this->shiftService->getWeekShifts($cafeId, $weekOffset);
        $weekData = $weekResult->ok ? ($weekResult->data ?? []) : [];
        $shifts = $weekData['shifts'] ?? [];
        $weekFrom = (string) ($weekData['from'] ?? \date('Y-m-d'));
        $weekTo = (string) ($weekData['to'] ?? \date('Y-m-d'));

        // Etiqueta legible de la semana
        $fromTs = \strtotime($weekFrom);
        $toTs = \strtotime($weekTo);
        $weekLabel = \date('j', $fromTs) . ' – ' . \date('j M Y', $toTs);

        View::render('manager/staff/index', [
            'titulo' => 'Gestión de Staff',
            'staff' => $staff,
            'shifts' => $shifts,
            'cafe_id' => $cafeId,
            'csrf_token' => Csrf::token(),
            'weekOffset' => $weekOffset,
            'weekFrom' => $weekFrom,
            'weekTo' => $weekTo,
            'weekLabel' => $weekLabel,
            'extraJs' => ['manager/manager-staff.js'],
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

        // Métricas de performance (PHP-injected — no AJAX)
        $metricsResult = $this->shiftService->getPerformanceMetrics($userId, $cafeId);
        $metrics = $metricsResult->ok ? ($metricsResult->data ?? []) : [];

        View::render('manager/staff/show', [
            'titulo' => 'Detalle de Staff',
            'staff' => $staffMember,
            'shift_history' => $shiftHistory,
            'metrics' => $metrics,
            'csrf_token' => Csrf::token(),
        ], ['manager/staff.css'], 'backoffice');

        return null;
    }

    /**
     * POST /manager/staff/shifts
     *
     * Asignar turno a un staff member del café
     */
    public function assignShift(ServerRequestInterface $request): ResponseInterface
    {
        $user = Session::user();
        $cafeId = $user['cafe_id'] ?? null;

        if (!$cafeId) {
            return $this->response->json(['ok' => false, 'error' => 'No tienes un café asignado.'], 403);
        }

        $body = (array) ($request->getParsedBody() ?? []);
        $userId = isset($body['user_id']) ? (int) $body['user_id'] : 0;
        $date = \trim((string) ($body['shift_date'] ?? ''));
        $start = \trim((string) ($body['shift_start'] ?? ''));
        $end = \trim((string) ($body['shift_end'] ?? ''));
        $notes = isset($body['notes']) ? (string) $body['notes'] : null;

        if ($userId <= 0) {
            return $this->response->json(['ok' => false, 'error' => 'ID de usuario no válido.'], 400);
        }

        if (!\preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $this->response->json(['ok' => false, 'error' => 'Fecha inválida. Formato esperado: YYYY-MM-DD.'], 400);
        }

        if (!\preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $start)) {
            return $this->response->json(['ok' => false, 'error' => 'Hora de inicio inválida. Formato esperado: HH:MM.'], 400);
        }

        if (!\preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $end)) {
            return $this->response->json(['ok' => false, 'error' => 'Hora de fin inválida. Formato esperado: HH:MM.'], 400);
        }

        if ($start >= $end) {
            return $this->response->json(['ok' => false, 'error' => 'La hora de inicio debe ser menor que la hora de fin.'], 400);
        }

        $result = $this->shiftService->assignShift($userId, (int) $cafeId, $date, $start, $end, $notes, (int) ($user['id'] ?? 0));

        if (!$result->ok) {
            return $this->response->json(['ok' => false, 'error' => $result->error], 400);
        }

        return $this->response->json(['ok' => true]);
    }

    /**
     * GET /manager/staff/{id}/performance
     *
     * Métricas de rendimiento de un staff member
     */
    public function viewPerformance(int $userId): ResponseInterface
    {
        $user = Session::user();
        $cafeId = $user['cafe_id'] ?? null;

        if (!$cafeId) {
            return $this->response->json(['ok' => false, 'error' => 'No tienes un café asignado.'], 403);
        }

        $metricsResult = $this->shiftService->getPerformanceMetrics($userId, (int) $cafeId);

        if (!$metricsResult->ok) {
            return $this->response->json(['ok' => false, 'error' => $metricsResult->error], 422);
        }

        return $this->response->json(['ok' => true, 'data' => $metricsResult->data ?? []]);
    }

    /**
     * PUT /manager/staff/shifts/{id}
     *
     * Actualizar un turno del café
     */
    public function updateShift(ServerRequestInterface $request, int $id): ResponseInterface
    {
        $user = Session::user();
        $cafeId = $user['cafe_id'] ?? null;

        if (!$cafeId) {
            return $this->response->json(['ok' => false, 'error' => 'No tienes un café asignado.'], 403);
        }

        if ($id <= 0) {
            return $this->response->json(['ok' => false, 'error' => 'ID de turno no válido.'], 400);
        }

        $body = (array) ($request->getParsedBody() ?? []);

        $data = [];

        if (isset($body['shift_date'])) {
            $date = \trim((string) $body['shift_date']);

            if (!\preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                return $this->response->json(['ok' => false, 'error' => 'Fecha inválida. Formato esperado: YYYY-MM-DD.'], 400);
            }

            $data['shift_date'] = $date;
        }

        if (isset($body['shift_start'])) {
            $start = \trim((string) $body['shift_start']);

            if (!\preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $start)) {
                return $this->response->json(['ok' => false, 'error' => 'Hora de inicio inválida. Formato esperado: HH:MM.'], 400);
            }

            $data['shift_start'] = $start;
        }

        if (isset($body['shift_end'])) {
            $end = \trim((string) $body['shift_end']);

            if (!\preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $end)) {
                return $this->response->json(['ok' => false, 'error' => 'Hora de fin inválida. Formato esperado: HH:MM.'], 400);
            }

            $data['shift_end'] = $end;
        }

        if (\array_key_exists('notes', $body)) {
            $data['notes'] = $body['notes'] !== '' ? (string) $body['notes'] : null;
        }

        if ($data === []) {
            return $this->response->json(['ok' => false, 'error' => 'No se proporcionaron campos para actualizar.'], 400);
        }

        $result = $this->shiftService->updateShift($id, (int) $cafeId, $data);

        if (!$result->ok) {
            $status = $result->error === 'shift_not_found' ? 404 : 422;

            return $this->response->json(['ok' => false, 'error' => $result->error ?? 'Error desconocido.'], $status);
        }

        return $this->response->json(['ok' => true]);
    }

    /**
     * DELETE /manager/staff/shifts/{id}
     *
     * Eliminar un turno del café (soft-delete)
     */
    public function deleteShift(ServerRequestInterface $request, int $id): ResponseInterface
    {
        $user = Session::user();
        $cafeId = $user['cafe_id'] ?? null;

        if (!$cafeId) {
            return $this->response->json(['ok' => false, 'error' => 'No tienes un café asignado.'], 403);
        }

        if ($id <= 0) {
            return $this->response->json(['ok' => false, 'error' => 'ID de turno no válido.'], 400);
        }

        $result = $this->shiftService->deleteShift($id, (int) $cafeId);

        if (!$result->ok) {
            $status = $result->error === 'shift_not_found' ? 404 : 422;

            return $this->response->json(['ok' => false, 'error' => $result->error ?? 'Error desconocido.'], $status);
        }

        return $this->response->json(['ok' => true]);
    }
}
