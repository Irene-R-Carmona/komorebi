<?php

declare(strict_types=1);

namespace App\Http\Controllers\Kitchen;

use App\Core\Container;
use App\Core\Http\ResponseFactory;
use App\Core\Raw;
use App\Core\Result;
use App\Core\ServiceErrorCode;
use App\Core\Session;
use App\Core\View;
use App\Exceptions\ValidationException;
use App\Services\ContextServiceInstance;
use App\Services\Contracts\KitchenServiceInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Controlador de Cocina (KDS - Kitchen Display System)
 *
 * Gestiona la pantalla de cocina para visualizar y actualizar órdenes pendientes
 * agrupadas por estación. Acceso restringido a staff/manager/admin.
 */
final class KitchenController
{
    private KitchenServiceInterface $service;

    private ResponseFactory $response;

    private ?ContextServiceInstance $context;

    public function __construct(
        ?KitchenServiceInterface $service = null,
        ?ResponseFactory $response = null,
        ?ContextServiceInstance $context = null,
    ) {
        $this->service = $service ?? Container::make(KitchenServiceInterface::class);
        $this->response = $response ?? new ResponseFactory();
        $this->context = $context;
    }

    private function context(): ContextServiceInstance
    {
        return $this->context ??= \App\Core\Container::make(ContextServiceInstance::class);
    }

    /**
     * GET /ops/kitchen
     * Muestra el KDS con órdenes pendientes agrupadas por estación.
     *
     * @throws ValidationException Si no hay contexto de sede válido
     */
    public function index(ServerRequestInterface $request): ?ResponseInterface
    {
        $cafeId = $this->context()->getCafeId();

        // Fallback: admin sin contexto puede ver café 1
        if ($cafeId === null && Session::role() === 'admin') {
            $cafeId = 1;
        }

        if ($cafeId === null) {
            throw ValidationException::withMessage('KDS requiere un contexto de sede. Contacta a tu administrador.');
        }

        // Obtener órdenes pendientes
        $itemsRaw = $this->service->getAllPending($cafeId);
        $cafeName = $this->context()->getCafeName();

        // Procesar y agrupar por estación
        $stations = $this->processOrdersForDisplay($itemsRaw);

        // Estadísticas para el header KDS
        $stats = $this->service->getDailyStats($cafeId);
        $avgMin = (float) ($stats['avg_prep_time'] ?? 0);
        $avgPrepFormatted = $avgMin > 0
            ? \sprintf('%02d:%02d', (int) $avgMin, (int) (($avgMin - (int) $avgMin) * 60))
            : '--:--';

        // Renderizar vista de KDS
        View::render('kitchen/index', [
            'titulo' => "KDS - $cafeName",
            'cafe_id' => $cafeId,
            'stations' => $stations,
            'cafe_name' => $cafeName,
            'backlog_alert' => \count($itemsRaw) > 10,
            'total_tickets' => \count($itemsRaw),
            'avg_prep_time_formatted' => $avgPrepFormatted,
            'counts' => [
                'hot' => \count($stations['hot']),
                'bar' => \count($stations['bar']),
                'cold' => \count($stations['cold']),
            ],
        ], ['workspaces/kds.css'], 'kds');

        return null;
    }

    /**
     * GET /ops/kitchen/history
     * Muestra los ítems servidos hoy en este café.
     *
     * @throws ValidationException Si no hay contexto de sede válido
     */
    public function history(ServerRequestInterface $request): ?ResponseInterface
    {
        $cafeId = $this->context()->getCafeId();

        if ($cafeId === null && Session::role() === 'admin') {
            $cafeId = 1;
        }

        if ($cafeId === null) {
            throw ValidationException::withMessage('KDS requiere un contexto de sede. Contacta a tu administrador.');
        }

        $completed = $this->service->getCompletedToday($cafeId);
        $cafeName = $this->context()->getCafeName();

        View::render('kitchen/history', [
            'titulo' => "Historial de hoy - $cafeName",
            'completed' => $completed,
            'cafe_name' => $cafeName,
        ], ['workspaces/kds.css'], 'kds');

        return null;
    }

    /**
     * POST /ops/kitchen/start
     * Marca un ítem de comanda como en preparación: pending → kitchen (AJAX KDS).
     *
     * @throws ValidationException
     */
    public function start(ServerRequestInterface $request): ResponseInterface
    {
        $body = (array) $request->getParsedBody();
        $itemId = (int) ($body['item_id'] ?? 0);

        if ($itemId <= 0) {
            throw ValidationException::required('item_id');
        }

        $this->service->startPreparing($itemId);

        return $this->response->json(['ok' => true, 'status' => 'kitchen']);
    }

    /**
     * POST /ops/kitchen/ready
     * Marca un ítem de comanda como listo (AJAX KDS).
     *
     * @throws ValidationException
     */
    public function ready(ServerRequestInterface $request): ResponseInterface
    {
        $body = (array) $request->getParsedBody();
        $itemId = (int) ($body['item_id'] ?? 0);

        if ($itemId <= 0) {
            throw ValidationException::required('item_id');
        }

        $this->service->markReady($itemId);

        return $this->response->json(['ok' => true]);
    }

    /**
     * GET /ops/kitchen/orders
     * Redirige al panel KDS principal.
     */
    public function activeOrders(ServerRequestInterface $request): ResponseInterface
    {
        return $this->response->redirect('/ops/kitchen');
    }

    /**
     * POST /api/v1/ops/kitchen/orders/{id}/complete → 200
     */
    public function completeOrder(ServerRequestInterface $request): ResponseInterface
    {
        $id = (int) $request->getAttribute('id');

        $ok = $this->service->markReady($id);

        if (!$ok) {
            return $this->response->problem(Result::fail('No se pudo completar el pedido', ServiceErrorCode::BUSINESS_RULE), 422);
        }

        return $this->response->json(['ok' => true, 'data' => ['message' => 'Pedido completado']]);
    }

    // ─────────────────────────────────────────────────────────────
    // Helpers privados
    // ─────────────────────────────────────────────────────────────

    /**
     * Procesa y agrupa órdenes por estación para visualización.
     * Calcula tiempos de espera y asigna clases CSS de estado.
     *
     * @param array<array> $itemsRaw
     *
     * @return array{hot: array, cold: array, bar: array}
     */
    private function processOrdersForDisplay(array $itemsRaw): array
    {
        $stations = ['hot' => [], 'cold' => [], 'bar' => []];
        $now = \time();

        foreach ($itemsRaw as $item) {
            // Calcular tiempo de espera (created_ts viene como UNIX_TIMESTAMP desde MySQL — sin desfase de timezone)
            $seconds = $now - (int) ($item['created_ts'] ?? \strtotime($item['created_at']));

            // Formatear tiempo para UI
            $item['ui_time'] = \gmdate(($seconds > 3600 ? 'H:i:s' : 'i:s'), $seconds);

            // Asignar clase CSS según urgencia (basada en prep_time restante)
            // Solo aplica urgencia si la preparación ha empezado (status = 'kitchen')
            // Los items 'pending' muestran el timer pero sin coloración de urgencia
            $remaining = (($item['prep_time'] ?? 5) * 60) - $seconds;
            $item['ui_class'] = '';

            if ($item['status'] === 'kitchen') {
                if ($remaining <= 0) {
                    $item['ui_class'] = 'kds-card--late';
                } elseif ($remaining <= 120) {
                    $item['ui_class'] = 'kds-card--warn';
                }
            }

            // Codificar SOP (Standard Operating Procedure) para embeber en HTML
            // Raw() evita que View::render::escapeData() doble-escape el JSON ya codificado
            $item['json_sop'] = new Raw(\htmlspecialchars(
                \json_encode([
                    'title' => $item['product_name'] ?? '',
                    'steps' => $item['recipe_steps'] ?? '',
                    'ingred' => $item['ingredients_list'] ?? [],
                    'check' => $item['critical_check'] ?? '',
                    'allergens' => $item['allergens'] ?? [],
                ], JSON_UNESCAPED_UNICODE) ?: '{}',
                ENT_QUOTES,
                'UTF-8'
            ));

            // Agrupar por estación
            $station = $item['station'] ?? 'kitchen_hot';

            if ($station === 'kitchen_hot') {
                $stations['hot'][] = $item;
            } elseif ($station === 'bar') {
                $stations['bar'][] = $item;
            } else {
                $stations['cold'][] = $item;
            }
        }

        return $stations;
    }
}
