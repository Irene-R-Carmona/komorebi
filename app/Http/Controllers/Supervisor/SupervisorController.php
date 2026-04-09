<?php

declare(strict_types=1);

namespace App\Http\Controllers\Supervisor;

use App\Core\Flash;
use App\Core\Http\ResponseFactory;
use App\Core\Logger;
use App\Core\Session;
use App\Core\View;
use App\Repositories\ReservationRepository;
use App\Services\SupervisorAssignmentService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Supervisor Controller
 *
 * Provee la vista del dashboard del encargado (supervisor).
 * La autenticación y autorización son responsabilidad exclusiva del
 * pipeline PSR-15 (middleware en routes.php).
 */
final class SupervisorController
{
    private ResponseFactory $response;

    /** @var array<string,string> */
    private const STATUS_LABELS = [
        'pending'   => 'Pendiente',
        'confirmed' => 'Confirmada',
        'active'    => 'Activa',
        'completed' => 'Completada',
        'cancelled' => 'Cancelada',
        'no_show'   => 'No Show',
    ];

    public function __construct(
        private readonly SupervisorAssignmentService $assignmentService,
        private readonly ?ReservationRepository $reservationRepo = null,
    ) {
        $this->response = new ResponseFactory();
    }

    /**
     * GET /supervisor/dashboard
     */
    public function index(ServerRequestInterface $request): ?ResponseInterface
    {
        $user   = Session::user();
        $cafeId = (int) ($user['cafe_id'] ?? 0);

        $reservations = [];

        if ($cafeId > 0) {
            $repo = $this->reservationRepo ?? new ReservationRepository();
            $raw  = $repo->findByCafeAndDate($cafeId, date('Y-m-d'));

            $reservations = array_map(function (array $r): array {
                $status = (string) ($r['status'] ?? 'pending');
                return [
                    'id'           => (int) $r['id'],
                    'customer_name' => 'Cliente #' . (int) $r['user_id'],
                    'time'         => substr((string) ($r['reservation_time'] ?? ''), 0, 5),
                    'guests'       => (int) ($r['guest_count'] ?? 0),
                    'status'       => $status,
                    'statusLabel'  => self::STATUS_LABELS[$status] ?? ucfirst($status),
                ];
            }, $raw);
        }

        $tables = [
            ['id' => 1, 'code' => 'A1', 'capacity' => 4, 'status' => 'free'],
            ['id' => 2, 'code' => 'A2', 'capacity' => 2, 'status' => 'occupied'],
            ['id' => 3, 'code' => 'B1', 'capacity' => 6, 'status' => 'free'],
            ['id' => 4, 'code' => 'B2', 'capacity' => 2, 'status' => 'occupied'],
        ];

        $orders = [
            ['id' => 501, 'table' => 'A2', 'itemsSummary' => '1x Latte, 2x Scone', 'status' => 'preparing'],
            ['id' => 502, 'table' => 'B2', 'itemsSummary' => '2x Ramen', 'status' => 'pending'],
        ];

        View::render('supervisor/dashboard', [
            'titulo' => 'Supervisor — Panel',
            'reservations' => $reservations,
            'tables' => $tables,
            'orders' => $orders,
        ], [], 'backoffice');

        return null;
    }

    /**
     * GET /supervisor/assignments
     *
     * Vista de gestión de asignaciones de mesas.
     */
    public function assignments(ServerRequestInterface $request): ?ResponseInterface
    {
        $result      = $this->assignmentService->listAssignments();
        $assignments = $result->ok ? array_values((array) $result->data) : [];

        if (!$result->ok) {
            Flash::error($result->getMessage());
        }

        View::render('supervisor/assignments', [
            'titulo'      => 'Gestión de Asignaciones',
            'assignments' => $assignments,
        ], [], 'backoffice');

        return null;
    }

    /**
     * POST /supervisor/assignments
     *
     * Crea una nueva asignación (formulario HTML tradicional).
     * CSRF validado por el middleware del pipeline PSR-15.
     */
    public function createAssignment(ServerRequestInterface $request): ResponseInterface
    {
        $body   = (array) ($request->getParsedBody() ?? []);
        $result = $this->assignmentService->createFromArray($body);

        if (!$result->ok) {
            Logger::warning('[SupervisorController] Error al crear asignación', [
                'error' => $result->getMessage(),
            ]);
            Flash::error($result->getMessage());
        } else {
            Flash::success('Asignación creada correctamente.');
        }

        return $this->response->redirect('/supervisor/assignments');
    }
}
