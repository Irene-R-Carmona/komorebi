<?php

declare(strict_types=1);

namespace App\Http\Controllers\Kitchen;

use App\Core\Container;
use App\Core\Flash;
use App\Core\Http\ResponseFactory;
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

        // Renderizar vista de KDS
        View::render('kitchen/index', [
            'titulo' => "KDS - $cafeName",
            'stations' => $stations,
            'cafe_name' => $cafeName,
            'backlog_alert' => \count($itemsRaw) > 10,
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
     * Lista de órdenes activas en el KDS.
     */
    public function activeOrders(ServerRequestInterface $request): ?ResponseInterface
    {
        $cafeId = $this->context()->getCafeId();

        if ($cafeId === null && Session::role() === 'admin') {
            $cafeId = 1;
        }

        if ($cafeId === null) {
            Flash::error('KDS requiere un contexto de sede. Contacta a tu administrador.');

            return $this->response->redirect('/ops/kitchen');
        }

        $orders = $this->service->getAllPending($cafeId);

        View::render('kitchen/index', ['orders' => $orders], ['workspaces/kds.css'], 'kds');

        return null;
    }

    /**
     * POST /ops/kitchen/orders/{id}/complete
     * Marca una comanda como completada.
     */
    public function completeOrder(ServerRequestInterface $request): ResponseInterface
    {
        $id = (int) $request->getAttribute('id');
        $body = (array) $request->getParsedBody();

        $ok = $this->service->markReady($id);

        if (!$ok) {
            Flash::error('No se pudo completar el pedido.');

            return $this->response->redirect('/ops/kitchen');
        }

        Flash::success('Pedido completado.');

        return $this->response->redirect('/ops/kitchen');
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
            // Calcular tiempo de espera
            $createdTime = \strtotime($item['created_at']);
            $seconds = $now - $createdTime;
            $mins = (int) \round($seconds / 60);

            // Formatear tiempo para UI
            $item['ui_time'] = \gmdate(($seconds > 3600 ? 'H:i:s' : 'i:s'), $seconds);

            // Asignar clase CSS según urgencia
            $item['ui_class'] = '';

            if ($mins > 15) {
                $item['ui_class'] = 'kds-card--late';
            } elseif ($mins > 10) {
                $item['ui_class'] = 'kds-card--warn';
            }

            // Codificar SOP (Standard Operating Procedure) para embeber en HTML
            $item['json_sop'] = \htmlspecialchars(\json_encode([
                'title' => $item['product_name'] ?? '',
                'steps' => $item['recipe_steps'] ?? '',
                'ingred' => $item['ingredients_list'] ?? [],
                'check' => $item['critical_check'] ?? '',
            ], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');

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
