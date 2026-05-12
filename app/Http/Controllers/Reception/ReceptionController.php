<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reception;

use App\Core\Container;
use App\Core\Http\ResponseFactory;
use App\Core\Raw;
use App\Core\Session;
use App\Core\View;
use App\Exceptions\AuthorizationException;
use App\Repositories\Contracts\ReservationItemRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Services\ContextServiceInstance;
use App\Services\Contracts\ReceptionServiceInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Controlador de Recepción
 *
 * Gestiona la recepción de huéspedes: check-in, check-out y trackers.
 */
final class ReceptionController
{
    private ReceptionServiceInterface $service;

    private ?ContextServiceInstance $context = null;

    private ResponseFactory $response;

    private ProductRepositoryInterface $productRepo;

    private ReservationItemRepositoryInterface $itemRepo;

    public function __construct(
        ?ReceptionServiceInterface $service = null,
        ?ContextServiceInstance $context = null,
        ?ResponseFactory $response = null,
        ?ProductRepositoryInterface $productRepo = null,
        ?ReservationItemRepositoryInterface $itemRepo = null,
    ) {
        $this->service = $service ?? Container::make(ReceptionServiceInterface::class);
        $this->context = $context;
        $this->response = $response ?? Container::make(ResponseFactory::class);
        $this->productRepo = $productRepo ?? Container::make(ProductRepositoryInterface::class);
        $this->itemRepo = $itemRepo ?? Container::make(ReservationItemRepositoryInterface::class);
    }

    private function context(): ContextServiceInstance
    {
        return $this->context ??= \App\Core\Container::make(ContextServiceInstance::class);
    }

    /**
     * GET /ops/reception
     * Muestra el panel de recepción con reservas próximas y grupos activos.
     *
     * @throws AuthorizationException Si falta contexto de sede
     */
    public function index(ServerRequestInterface $request): ?ResponseInterface
    {
        $cafeId = $this->context()->getCafeId();

        if ($cafeId === null) {
            throw new AuthorizationException('Recepción requiere un contexto de sede');
        }

        // Obtener datos del servicio
        $reservasRaw = $this->service->getPendingArrivals($cafeId);
        $activeGroups = $this->service->getActiveGroups($cafeId);
        $freeTrackers = $this->service->getAvailableTrackers($cafeId);
        $capInfo = $this->service->getCapacityInfo($cafeId);
        $orderableItems = $this->productRepo->findOrderableItems($cafeId);

        // Procesar reservas para presentación
        $reservasUI = $this->processReservationsForDisplay($reservasRaw);

        // Calcular ocupación actual
        $ocupacion = \array_sum(\array_column($activeGroups, 'guest_count'));

        // Guardar nombre de sede en sesión para el layout
        $cafeName = $this->context()->getCafeName();
        Session::set('user_cafe_name', $cafeName);

        // Ítems listos por reserva (para panel de servir)
        $activeResIds = \array_column($activeGroups, 'id');
        $readyItemsByRes = !empty($activeResIds)
            ? $this->itemRepo->getReadyItemsByReservations($activeResIds)
            : [];

        // Renderizar vista
        View::render('reception/index', [
            'titulo' => 'Recepción - ' . $cafeName,
            'cafe_id' => $cafeId,
            'reservas' => $reservasUI,
            'active_groups' => $activeGroups,
            'free_trackers' => $freeTrackers,
            'ocupacion' => $ocupacion,
            'cap_max' => $capInfo['max'] ?? 0,
            'orderable_items' => $orderableItems,
            'orderable_items_json' => Raw::json($orderableItems),
            'ready_by_res_json' => Raw::json(\array_column($activeGroups, 'ready_item_count', 'id')),
            'ready_items_json' => Raw::json($readyItemsByRes),
        ], ['workspaces/reception.css'], 'reception');

        return null;
    }

    /**
     * GET /ops/reception/reservations
     * Redirige al panel principal de recepción.
     */
    public function todayReservations(ServerRequestInterface $request): ResponseInterface
    {
        return $this->response->redirect('/ops/reception');
    }

    // ─────────────────────────────────────────────────────────────
    // Acciones de check-in / check-out
    // ─────────────────────────────────────────────────────────────

    /**
     * POST /ops/reception/reservations/{id}/checkin
     */
    public function checkIn(ServerRequestInterface $request, int $id): ResponseInterface
    {
        if ($id <= 0) {
            return $this->response->json(['ok' => false, 'message' => 'ID de reserva inválido'], 400);
        }

        $body = (array) ($request->getParsedBody() ?? []);
        $trackerId = isset($body['tracker_id']) ? (int) $body['tracker_id'] : 0;

        if ($trackerId <= 0) {
            return $this->response->json(['ok' => false, 'message' => 'ID de tracker inválido'], 400);
        }

        $result = $this->service->processCheckin($id, $trackerId);

        if (!$result->ok) {
            return $this->response->json(['ok' => false, 'message' => $result->error], 422);
        }

        return $this->response->json(['ok' => true]);
    }

    /**
     * POST /ops/reception/reservations/{id}/checkout
     */
    public function checkOut(ServerRequestInterface $request, int $id): ResponseInterface
    {
        if ($id <= 0) {
            return $this->response->json(['ok' => false, 'message' => 'ID de reserva inválido'], 400);
        }

        $result = $this->service->processCheckout($id);

        if (!$result->ok) {
            return $this->response->json(['ok' => false, 'message' => $result->error], 422);
        }

        return $this->response->json(['ok' => true]);
    }

    // ─────────────────────────────────────────────────────────────
    // Helpers privados
    // ─────────────────────────────────────────────────────────────

    private function processReservationsForDisplay(array $reservasRaw): array
    {
        $reservasUI = [];
        $ahora = \time();

        foreach ($reservasRaw as $r) {
            $horaReserva = \strtotime($r['reservation_date'] . ' ' . $r['reservation_time']);
            $diffMinutos = (int) \round(($horaReserva - $ahora) / 60);

            if ($diffMinutos < -15) {
                $r['ui_state'] = 'late';
                $r['ui_label'] = 'RETRASO';
            } elseif ($diffMinutos <= 15) {
                $r['ui_state'] = 'now';
                $r['ui_label'] = 'AHORA';
            } else {
                $r['ui_state'] = 'future';
                $r['ui_label'] = 'LLEGADA';
            }

            $r['ui_time'] = \substr($r['reservation_time'], 0, 5);
            $reservasUI[] = $r;
        }

        return $reservasUI;
    }
}
