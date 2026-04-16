<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Core\Http\ResponseFactory;
use App\Core\Session;
use App\Http\Controllers\Api\AbstractApiController;
use App\Services\Contracts\SupervisorAssignmentServiceInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Supervisor API Controller
 *
 * Gestiona las asignaciones de mesas (assignments) via API JSON.
 * Usado por el dashboard de supervisor (supervisor-dashboard.js)
 */
final class SupervisorController extends AbstractApiController
{
    public function __construct(
        ResponseFactory $response,
        private readonly SupervisorAssignmentServiceInterface $service,
    ) {
        parent::__construct($response);
    }

    /**
     * POST /api/v1/supervisor/assign
     *
     * Asigna una reserva a una mesa.
     *
     * Payload esperado:
     * {
     *   "reservation_id": 123,
     *   "table_code": "A1"
     * }
     */
    public function assign(ServerRequestInterface $request): ResponseInterface
    {
        if (!\in_array(Session::role(), ['admin', 'manager', 'supervisor'], true)) {
            return $this->forbidden('No tienes permisos para asignar mesas', 'forbidden');
        }

        $result = $this->service->createFromRequest();

        if (!$result->ok) {
            $status = $result->code === 'validation_error' ? 422 : 500;

            return $this->response->problem($result, $status);
        }

        return $this->success([
            'message' => 'Mesa asignada correctamente',
            'assignment' => $result->data,
        ]);
    }

    /**
     * GET /api/v1/supervisor/assignments
     *
     * Lista todas las asignaciones actuales.
     */
    public function list(ServerRequestInterface $request): ResponseInterface
    {
        $result = $this->service->listAssignments();

        if (!$result->ok) {
            return $this->response->problem($result, 500);
        }

        return $this->success(\array_values((array) $result->data));
    }
}
