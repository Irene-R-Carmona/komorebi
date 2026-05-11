<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Ops;

use App\Core\Http\ResponseFactory;
use App\Core\Session;
use App\Http\Controllers\Api\AbstractApiController;
use App\Services\Contracts\KitchenServiceInterface;
use App\Services\MercurePublisherService;
use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * API REST: Operaciones de cocina / KDS (Ops scope)
 *
 * Rutas (bajo /api/v1/ops/kitchen):
 * - GET  /orders          → activeOrders()
 * - POST /orders/{id}/complete → completeOrder()
 */
final class KitchenApiController extends AbstractApiController
{
    public function __construct(
        ResponseFactory $response,
        private readonly KitchenServiceInterface $service,
    ) {
        parent::__construct($response);
    }

    /**
     * GET /api/v1/ops/kitchen/orders
     *
     * Lista las órdenes activas pendientes de preparación para la sede asignada.
     */
    public function activeOrders(ServerRequestInterface $request): ResponseInterface
    {
        $cafeId = Session::userCafeId();

        if (!$cafeId) {
            return $this->forbidden('No tienes una sede asignada', 'cafe_not_assigned');
        }

        try {
            $orders = $this->service->getAllPending($cafeId);

            return $this->success(['orders' => $orders]);
        } catch (Exception $e) {
            return $this->serverError('Error al obtener órdenes: ' . $e->getMessage(), 'fetch_failed');
        }
    }

    /**
     * POST /api/v1/ops/kitchen/orders/{id}/complete
     *
     * Marca un ítem de pedido como listo/completado.
     */
    public function completeOrder(ServerRequestInterface $request, int $id): ResponseInterface
    {
        if ($id <= 0) {
            return $this->badRequest('Identificador de pedido inválido', 'order_id_invalid');
        }

        try {
            $ok = $this->service->markReady($id);

            if (!$ok) {
                return $this->unprocessable('No se pudo completar el pedido', 'complete_failed');
            }

            $cafeId = Session::userCafeId();
            if ($cafeId !== null) {
                MercurePublisherService::publish(
                    'reception/' . $cafeId . '/kitchen-ready',
                    ['order_id' => $id, 'cafe_id' => $cafeId]
                );
            }

            return $this->success(['message' => 'Pedido completado']);
        } catch (Exception $e) {
            return $this->serverError('Error al completar pedido: ' . $e->getMessage(), 'complete_error');
        }
    }
}
