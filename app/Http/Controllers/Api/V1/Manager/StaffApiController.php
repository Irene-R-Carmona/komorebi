<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Core\Http\ResponseFactory;
use App\Core\Session;
use App\Http\Controllers\Api\AbstractApiController;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Contracts\StaffShiftServiceInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * API REST: Gestión de staff (Manager scope)
 *
 * Rutas (bajo /api/v1/manager):
 * - POST /staff/assign-shift      → assignShift()
 * - POST /staff/edit-permissions  → editPermissions()
 * - GET  /staff/performance/{id}  → viewPerformance()
 */
final class StaffApiController extends AbstractApiController
{
    public function __construct(
        ResponseFactory $response,
        private readonly UserRepositoryInterface $userRepo,
        private readonly StaffShiftServiceInterface $shiftService,
    ) {
        parent::__construct($response);
    }

    /**
     * POST /api/v1/manager/staff/assign-shift
     */
    public function assignShift(ServerRequestInterface $request): ResponseInterface
    {
        $user = Session::user();
        $cafeId = $user['cafe_id'] ?? null;

        if (!$cafeId) {
            return $this->forbidden('No tienes un café asignado', 'cafe_not_assigned');
        }

        $body = (array) ($request->getParsedBody() ?? []);
        $userId = (int) ($body['user_id'] ?? 0);
        $shiftDate = (string) ($body['shift_date'] ?? '');
        $shiftStart = (string) ($body['shift_start'] ?? '');
        $shiftEnd = (string) ($body['shift_end'] ?? '');
        $notes = isset($body['notes']) ? (string) $body['notes'] : null;

        if ($userId <= 0) {
            return $this->badRequest('Staff member no válido', 'user_id_invalid');
        }

        if ($shiftDate === '' || !\preg_match('/^\d{4}-\d{2}-\d{2}$/', $shiftDate)) {
            return $this->badRequest('Fecha de turno inválida (formato: YYYY-MM-DD)', 'shift_date_invalid');
        }

        if ($shiftStart === '' || !$this->isValidTime($shiftStart)) {
            return $this->badRequest('Hora de inicio inválida (formato: HH:MM)', 'shift_start_invalid');
        }

        if ($shiftEnd === '' || !$this->isValidTime($shiftEnd)) {
            return $this->badRequest('Hora de fin inválida (formato: HH:MM)', 'shift_end_invalid');
        }

        if (\strcmp($this->normalizeTime($shiftStart), $this->normalizeTime($shiftEnd)) >= 0) {
            return $this->badRequest('La hora de inicio debe ser menor que la hora de fin', 'time_order_invalid');
        }

        if (!$this->userRepo->existsInCafe($userId, $cafeId)) {
            return $this->forbidden('El staff member no pertenece a tu café', 'staff_not_in_cafe');
        }

        $result = $this->shiftService->assignShift(
            $userId,
            $cafeId,
            $shiftDate,
            $this->normalizeTime($shiftStart),
            $this->normalizeTime($shiftEnd),
            $notes,
            (int) $user['id'],
        );

        if (!$result->ok) {
            $status = $result->code === 'shift_overlap' ? 400 : 500;

            return $status === 400
                ? $this->badRequest((string) $result->error, (string) $result->code)
                : $this->serverError((string) $result->error, (string) $result->code);
        }

        return $this->success([
            'message' => 'Turno asignado correctamente',
            'shift_id' => $result->data['shift_id'],
        ]);
    }

    /**
     * POST /api/v1/manager/staff/edit-permissions
     *
     * Placeholder — RBAC avanzado pendiente de implementación.
     */
    public function editPermissions(ServerRequestInterface $request): ResponseInterface
    {
        $cafeId = Session::userCafeId();

        if (!$cafeId) {
            return $this->forbidden('No tienes un café asignado', 'cafe_not_assigned');
        }

        return $this->response->json([
            'ok' => false,
            'error' => 'Funcionalidad en desarrollo (RBAC avanzado)',
        ], 501);
    }

    /**
     * GET /api/v1/manager/staff/performance/{id}
     */
    public function viewPerformance(ServerRequestInterface $request, int $id): ResponseInterface
    {
        $user = Session::user();
        $cafeId = $user['cafe_id'] ?? null;

        if (!$cafeId) {
            return $this->forbidden('No tienes un café asignado', 'cafe_not_assigned');
        }

        $staffMember = $this->userRepo->getStaffBasicById($id, $cafeId);

        if ($staffMember === null) {
            return $this->notFound('Staff member no encontrado o no pertenece a tu café', 'staff_not_found');
        }

        $metricsResult = $this->shiftService->getPerformanceMetrics($id, $cafeId);

        if (!$metricsResult->ok) {
            return $this->serverError((string) $metricsResult->error, (string) $metricsResult->code);
        }

        return $this->success([
            'staff' => $staffMember,
            'metrics' => $metricsResult->data,
        ]);
    }

    private function isValidTime(string $time): bool
    {
        return (bool) \preg_match('/^([01]\d|2[0-3]):([0-5]\d)(:[0-5]\d)?$/', $time);
    }

    private function normalizeTime(string $time): string
    {
        if (\preg_match('/^(\d{2}):(\d{2})$/', $time)) {
            return $time . ':00';
        }

        return $time;
    }
}
