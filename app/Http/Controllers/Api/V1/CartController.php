<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Core\Http\ResponseFactory;
use App\Http\Controllers\Api\AbstractApiController;
use App\Services\Contracts\CartServiceInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * CartController (API)
 *
 * Endpoints:
 * - GET  /api/cart
 * - GET  /api/cart/guest
 * - POST /api/cart/add
 * - POST /api/cart/remove
 * - POST /api/cart/update
 * - POST /api/cart/clear
 */
final class CartController extends AbstractApiController
{
    public function __construct(
        ResponseFactory $response,
        private readonly CartServiceInterface $service,
    ) {
        parent::__construct($response);
    }

    /**
     * GET /api/cart
     * Retorna el carrito con detalles de productos.
     */
    public function get(ServerRequestInterface $request): ResponseInterface
    {
        return $this->success($this->service->getWithDetails());
    }

    /**
     * GET /api/cart/guest — sin autenticación requerida
     */
    public function guest(ServerRequestInterface $request): ResponseInterface
    {
        return $this->success(['items' => (object) [], 'totalQty' => 0, 'totalPrice' => 0]);
    }

    /**
     * POST /api/cart/add
     * Body: {product_id: int, quantity?: int}
     */
    public function add(ServerRequestInterface $request): ResponseInterface
    {
        $body = $request->getParsedBody() ?? [];
        $productId = isset($body['product_id']) && \is_numeric($body['product_id'])
            ? (int) $body['product_id']
            : null;

        if ($productId === null) {
            return $this->unprocessable('product_id requerido y debe ser numérico');
        }

        $quantity = isset($body['quantity']) && \is_numeric($body['quantity'])
            ? (int) $body['quantity']
            : 1;

        return $this->success($this->service->add($productId, $quantity));
    }

    /**
     * POST /api/cart/remove
     * Body: {product_id: int}
     */
    public function remove(ServerRequestInterface $request): ResponseInterface
    {
        $body = $request->getParsedBody() ?? [];
        $productId = isset($body['product_id']) && \is_numeric($body['product_id'])
            ? (int) $body['product_id']
            : null;

        if ($productId === null) {
            return $this->unprocessable('product_id requerido y debe ser numérico');
        }

        return $this->success($this->service->remove($productId));
    }

    /**
     * POST /api/cart/update
     * Body: {product_id: int, change: int}
     */
    public function update(ServerRequestInterface $request): ResponseInterface
    {
        $body = $request->getParsedBody() ?? [];

        if (!isset($body['product_id']) || !\is_numeric($body['product_id'])) {
            return $this->unprocessable('product_id requerido y debe ser numérico');
        }

        $productId = (int) $body['product_id'];
        $change = (int) ($body['change'] ?? 0);

        if ($change < -10 || $change > 10) {
            return $this->unprocessable('change debe estar entre -10 y 10');
        }

        return $this->success($this->service->updateItem($productId, $change));
    }

    /**
     * POST /api/cart/clear
     */
    public function clear(ServerRequestInterface $request): ResponseInterface
    {
        $this->service->clear();

        return $this->success(['status' => 'cleared']);
    }
}
