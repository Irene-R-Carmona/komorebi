<?php

declare(strict_types=1);

namespace App\Http\Controllers\Supervisor;

use App\Core\Container;
use App\Core\Http\ResponseFactory;
use App\Core\Logger;
use App\Core\Session;
use App\Core\View;
use App\Repositories\Contracts\ReservationItemRepositoryInterface;
use App\Repositories\ReservationRepository;
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
    private ReservationItemRepositoryInterface $itemRepo;
    private ResponseFactory $response;

    /** @var array<string,string> */
    private const STATUS_LABELS = [
        'pending' => 'Pendiente',
        'confirmed' => 'Confirmada',
        'active' => 'En Local',
        'completed' => 'Completada',
        'cancelled' => 'Cancelada',
        'no_show' => 'No Show',
    ];

    public function __construct(
        ?ReservationRepository $reservationRepo = null,
        ?KitchenService $kitchenService = null,
        ?ReservationItemRepositoryInterface $itemRepo = null,
    ) {
        $this->reservationRepo = $reservationRepo ?? new ReservationRepository();
        $this->kitchenService = $kitchenService ?? new KitchenService(Container::make(ReservationItemRepositoryInterface::class));
        $this->itemRepo = $itemRepo ?? Container::make(ReservationItemRepositoryInterface::class);
        $this->response = new ResponseFactory();
    }

    /**
     * GET /supervisor/dashboard
     */
    public function index(ServerRequestInterface $request): ?ResponseInterface
    {
        $user = Session::user();
        $cafeId = (int) ($user['cafe_id'] ?? 0);

        $data = $this->buildDashboardData($cafeId);

        Logger::info('[SupervisorController] dashboard loaded', [
            'cafe_id' => $cafeId,
            'reservations' => \count($data['reservations']),
            'active_tables' => \count($data['activeTables']),
            'pending_orders' => \count($data['pendingOrders']),
            'kitchen_orders' => \count($data['kitchenOrders']),
            'ready_orders' => \count($data['readyOrders']),
        ]);

        View::render('supervisor/dashboard', [
            'titulo' => 'Supervisor — Panel',
            'cafe_id' => $cafeId,
            'reservations' => $data['reservations'],
            'activeTables' => $data['activeTables'],
            'pendingOrders' => $data['pendingOrders'],
            'kitchenOrders' => $data['kitchenOrders'],
            'readyOrders' => $data['readyOrders'],
        ], [], 'supervisor');

        return null;
    }

    /**
     * GET /supervisor/dashboard/data
     *
     * Datos del dashboard en JSON para refresco vía AJAX/SSE.
     */
    public function dashboardData(ServerRequestInterface $request): ResponseInterface
    {
        $user = Session::user();
        $cafeId = (int) ($user['cafe_id'] ?? 0);

        return $this->response->json($this->buildDashboardData($cafeId));
    }

    // ─────────────────────────────────────────────────────────────
    // Helpers privados
    // ─────────────────────────────────────────────────────────────

    /**
     * @return array{
     *     reservations: list<array<string,mixed>>,
     *     activeTables: list<array<string,mixed>>,
     *     pendingOrders: list<array<string,mixed>>,
     *     kitchenOrders: list<array<string,mixed>>,
     *     readyOrders: list<array<string,mixed>>,
     * }
     */
    private function buildDashboardData(int $cafeId): array
    {
        $reservations = [];

        if ($cafeId > 0) {
            $raw = $this->reservationRepo->findByCafeAndDate($cafeId, \date('Y-m-d'));

            $reservations = \array_map(function (array $r): array {
                $status = (string) ($r['status'] ?? 'pending');

                $time = \substr((string) ($r['reservation_time'] ?? ''), 0, 5);

                return [
                    'id' => (int) $r['id'],
                    'customer_name' => $r['user_name'] ?? 'Cliente #' . (int) $r['user_id'],
                    'time' => $time,
                    'unix_time' => (int) (\strtotime(\date('Y-m-d') . ' ' . $time) ?: 0),
                    'guests' => (int) ($r['guest_count'] ?? 0),
                    'status' => $status,
                    'statusLabel' => self::STATUS_LABELS[$status] ?? \ucfirst($status),
                    'table_code' => $r['table_code'] ?? null,
                ];
            }, $raw);
        }

        // Mesas ocupadas = clientes físicamente en el local (status 'active' tras check-in)
        $activeTables = \array_values(
            \array_filter($reservations, fn (array $r): bool => $r['status'] === 'active')
        );

        $activeResIds = \array_column($activeTables, 'id');

        // Órdenes en curso desde el KDS (pending + kitchen)
        $allActive = $cafeId > 0 ? $this->kitchenService->getAllPending($cafeId) : [];
        $pendingOrders = \array_values(\array_filter($allActive, fn (array $o): bool => $o['status'] === 'pending'));
        $kitchenOrders = \array_values(\array_filter($allActive, fn (array $o): bool => $o['status'] === 'kitchen'));

        // Ítems listos para servir (agrupados por reserva, aplanamos a lista plana)
        $readyRaw = !empty($activeResIds) ? $this->itemRepo->getReadyItemsByReservations($activeResIds) : [];
        $readyOrders = [];

        foreach ($readyRaw as $reservationId => $items) {
            foreach ($items as $item) {
                $readyOrders[] = [
                    'id' => $item['id'],
                    'reservation_id' => $reservationId,
                    'product_name' => $item['product_name'],
                    'quantity' => $item['quantity'],
                    'status' => 'ready',
                ];
            }
        }

        return [
            'reservations' => $reservations,
            'activeTables' => $activeTables,
            'pendingOrders' => $pendingOrders,
            'kitchenOrders' => $kitchenOrders,
            'readyOrders' => $readyOrders,
        ];
    }
}
