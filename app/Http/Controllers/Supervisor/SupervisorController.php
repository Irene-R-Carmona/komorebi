<?php

declare(strict_types=1);

namespace App\Http\Controllers\Supervisor;

use App\Core\Container;
use App\Core\Flash;
use App\Core\Http\ResponseFactory;
use App\Core\Logger;
use App\Core\Session;
use App\Core\View;
use App\Repositories\Contracts\ReservationItemRepositoryInterface;
use App\Repositories\ReservationRepository;
use App\Services\Contracts\SupervisorAssignmentServiceInterface;
use App\Services\KitchenService;
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
    private ReservationRepository $reservationRepo;
    private KitchenService $kitchenService;
    private ResponseFactory $response;

    /** @var array<string,string> */
    private const STATUS_LABELS = [
        'pending' => 'Pendiente',
        'confirmed' => 'Confirmada',
        'active' => 'Activa',
        'completed' => 'Completada',
        'cancelled' => 'Cancelada',
        'no_show' => 'No Show',
        'checked_in' => 'En local',
    ];

    public function __construct(
        private readonly SupervisorAssignmentServiceInterface $assignmentService,
        ?ReservationRepository $reservationRepo = null,
        ?KitchenService $kitchenService = null,
    ) {
        $this->reservationRepo = $reservationRepo ?? new ReservationRepository();
        $this->kitchenService = $kitchenService ?? new KitchenService(Container::make(ReservationItemRepositoryInterface::class));
        $this->response = new ResponseFactory();
    }

    /**
     * GET /supervisor/dashboard
     */
    public function index(ServerRequestInterface $request): ?ResponseInterface
    {
        $user = Session::user();
        $cafeId = (int) ($user['cafe_id'] ?? 0);

        $reservations = [];

        if ($cafeId > 0) {
            $raw = $this->reservationRepo->findByCafeAndDate($cafeId, \date('Y-m-d'));

            $reservations = \array_map(function (array $r): array {
                $status = (string) ($r['status'] ?? 'pending');

                return [
                    'id' => (int) $r['id'],
                    'customer_name' => 'Cliente #' . (int) $r['user_id'],
                    'time' => \substr((string) ($r['reservation_time'] ?? ''), 0, 5),
                    'guests' => (int) ($r['guest_count'] ?? 0),
                    'status' => $status,
                    'statusLabel' => self::STATUS_LABELS[$status] ?? \ucfirst($status),
                    'table_code' => $r['table_code'] ?? null,
                ];
            }, $raw);
        }

        // Mesas ocupadas = reservas con check-in activo ahora
        $activeTables = \array_values(
            \array_filter($reservations, fn (array $r): bool => $r['status'] === 'checked_in')
        );

        // Órdenes en curso desde el KDS
        $activeOrders = $cafeId > 0 ? $this->kitchenService->getAllPending($cafeId) : [];

        Logger::info('[SupervisorController] dashboard loaded', [
            'cafe_id' => $cafeId,
            'reservations' => \count($reservations),
            'active_tables' => \count($activeTables),
            'active_orders' => \count($activeOrders),
        ]);

        View::render('supervisor/dashboard', [
            'titulo' => 'Supervisor — Panel',
            'reservations' => $reservations,
            'activeTables' => $activeTables,
            'activeOrders' => $activeOrders,
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
        $result = $this->assignmentService->listAssignments();
        $assignments = $result->ok ? \array_values((array) $result->data) : [];

        if (!$result->ok) {
            Flash::error($result->error);
        }

        View::render('supervisor/assignments', [
            'titulo' => 'Gestión de Asignaciones',
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
        $body = (array) ($request->getParsedBody() ?? []);
        $result = $this->assignmentService->createFromArray($body);

        if (!$result->ok) {
            Logger::warning('[SupervisorController] Error al crear asignación', [
                'error' => $result->error,
            ]);
            Flash::error($result->error);
        } else {
            Flash::success('Asignación creada correctamente.');
        }

        return $this->response->redirect('/supervisor/assignments');
    }
}
