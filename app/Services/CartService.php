<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Container;
use App\Core\Result;
use App\Core\Session;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\ReservationItemRepositoryInterface;
use App\Services\Contracts\CartServiceInterface;
use Override;

/**
 * Servicio de Carrito de Compras
 *
 * Gestiona el carrito en sesión para pedidos durante la visita.
 * Solo permite productos tipo 'item' (no pases).
 */
final class CartService implements CartServiceInterface
{
    private const string SESSION_KEY = 'cart';
    private const int MAX_QTY_PER_ITEM = 99;
    private const int MAX_UNIQUE_ITEMS = 50;

    private ProductRepositoryInterface $productRepo;
    private ReservationItemRepositoryInterface $itemRepo;

    public function __construct(
        ?ProductRepositoryInterface $productRepo = null,
        ?ReservationItemRepositoryInterface $itemRepo = null,
    ) {
        $this->productRepo = $productRepo ?? Container::make(ProductRepositoryInterface::class);
        $this->itemRepo = $itemRepo ?? Container::make(ReservationItemRepositoryInterface::class);
    }

    // ─────────────────────────────────────────────────────────────
    // Lectura
    // ─────────────────────────────────────────────────────────────

    /**
     * Obtiene el carrito actual.
     *
     * @return array{items: array<int, int>, totalQty: int, totalPrice: float}
     */
    #[Override]
    public function get(): array
    {
        $cart = Session::get(self::SESSION_KEY);

        if (!\is_array($cart) || !isset($cart['items'])) {
            return $this->emptyCart();
        }

        // Asegurar tipos correctos
        return [
            'items' => \is_array($cart['items']) ? $cart['items'] : [],
            'totalQty' => (int) ($cart['totalQty'] ?? 0),
            'totalPrice' => (float) ($cart['totalPrice'] ?? 0.0),
        ];
    }

    /**
     * Obtiene el carrito con detalles de productos.
     *
     * @return array{items: array, totalQty: int, totalPrice: float}
     */
    #[Override]
    public function getWithDetails(): array
    {
        $cart = $this->get();

        if (empty($cart['items'])) {
            return [
                'items' => [],
                'totalQty' => 0,
                'totalPrice' => 0.0,
            ];
        }

        $productIds = \array_keys($cart['items']);
        $products = $this->productRepo->findByIds($productIds);

        $detailedItems = [];
        foreach ($cart['items'] as $productId => $qty) {
            $product = $products[$productId] ?? null;

            $isAvailable = $product['is_active'] ?? false;
            if ($product && $isAvailable) {
                $detailedItems[] = [
                    'product_id' => $productId,
                    'name' => $product['name'],
                    'japanese_name' => $product['japanese_name'],
                    'price' => (int) $product['price'],
                    'quantity' => (int) $qty,
                    'subtotal' => (int) $product['price'] * (int) $qty,
                    'image_url' => $product['image_url'],
                    'station' => $product['station'],
                ];
            }
        }

        return [
            'items' => $detailedItems,
            'totalQty' => $cart['totalQty'],
            'totalPrice' => $cart['totalPrice'],
        ];
    }

    /**
     * Verifica si el carrito está vacío.
     */
    #[Override]
    public function isEmpty(): bool
    {
        $cart = $this->get();

        return empty($cart['items']);
    }

    /**
     * Obtiene la cantidad de un producto específico.
     */
    #[Override]
    public function getQuantity(int $productId): int
    {
        $cart = $this->get();

        return (int) ($cart['items'][$productId] ?? 0);
    }

    // ─────────────────────────────────────────────────────────────
    // Modificación
    // ─────────────────────────────────────────────────────────────

    /**
     * Añade un producto al carrito.
     */
    #[Override]
    public function add(int $productId, int $quantity = 1): Result
    {
        return $this->updateItem($productId, $quantity);
    }

    /**
     * Establece la cantidad exacta de un producto.
     */
    #[Override]
    public function setQuantity(int $productId, int $quantity): Result
    {
        $cart = $this->get();
        $currentQty = (int) ($cart['items'][$productId] ?? 0);
        $change = $quantity - $currentQty;

        return $this->updateItem($productId, $change);
    }

    /**
     * Elimina un producto del carrito.
     */
    #[Override]
    public function remove(int $productId): Result
    {
        $cart = $this->get();

        if (isset($cart['items'][$productId])) {
            unset($cart['items'][$productId]);
            Session::set(self::SESSION_KEY, $cart);
            $this->recalculate();
        }

        return Result::ok($this->get());
    }

    /**
     * Actualiza la cantidad de un producto.
     *
     * @param integer $change Cambio en cantidad (+/-)
     */
    #[Override]
    public function updateItem(int $productId, int $change): Result
    {
        if ($productId <= 0 || $change === 0) {
            return Result::ok($this->get());
        }

        // Validar que el producto existe y es un item disponible
        $product = $this->productRepo->findById($productId);

        $cart = $this->get();

        $isAvailable = $product !== null && $product->is_active;
        // Combinar validaciones para limitar retornos
        if (
            !$product || !$isAvailable || $product->product_type !== 'item' ||
            (!isset($cart['items'][$productId]) && \count($cart['items']) >= self::MAX_UNIQUE_ITEMS)
        ) {
            return Result::ok($this->get());
        }

        $currentQty = (int) ($cart['items'][$productId] ?? 0);
        $newQty = $currentQty + $change;

        if ($newQty <= 0) {
            unset($cart['items'][$productId]);
        } else {
            $cart['items'][$productId] = \min(self::MAX_QTY_PER_ITEM, $newQty);
        }

        Session::set(self::SESSION_KEY, $cart);
        $this->recalculate();

        return Result::ok($this->get());
    }

    /**
     * Vacía el carrito.
     */
    #[Override]
    public function clear(): void
    {
        Session::remove(self::SESSION_KEY);
    }

    // ─────────────────────────────────────────────────────────────
    // Conversión a Reserva
    // ─────────────────────────────────────────────────────────────

    /**
     * Obtiene los items del carrito formateados para crear reservation_items.
     *
     * @return array<int, array{product_id: int, quantity: int, unit_price: float}>
     */
    #[Override]
    public function getItemsForReservation(): array
    {
        $cart = $this->getWithDetails();
        $items = [];

        foreach ($cart['items'] as $item) {
            $items[] = [
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'unit_price' => (float) $item['price'],
            ];
        }

        return $items;
    }

    /**
     * Transfiere el carrito a una reserva y lo vacía.
     */
    #[Override]
    public function transferToReservation(int $reservationId): bool
    {
        $items = $this->getItemsForReservation();

        if (empty($items)) {
            return true;
        }

        foreach ($items as $item) {
            $this->itemRepo->add(
                $reservationId,
                $item['product_id'],
                $item['quantity'],
                $item['unit_price']
            );
        }

        $this->clear();

        return true;
    }

    // ─────────────────────────────────────────────────────────────
    // Helpers privados
    // ─────────────────────────────────────────────────────────────

    /**
     * Recalcula totales del carrito.
     */
    private function recalculate(): void
    {
        $cart = $this->get();

        if (empty($cart['items'])) {
            Session::set(self::SESSION_KEY, $this->emptyCart());

            return;
        }

        $productIds = \array_keys($cart['items']);
        $products = $this->productRepo->findByIds($productIds);

        $totalQty = 0;
        $totalPrice = 0.0;
        $validItems = [];

        foreach ($cart['items'] as $id => $qty) {
            $id = (int) $id;
            $qty = (int) $qty;

            // Validar producto existe, está disponible y es item
            $product = $products[$id] ?? null;

            $isAvailable = $product['is_active'] ?? false;
            if (!$product || !$isAvailable || $product['product_type'] !== 'item') {
                continue;
            }

            if ($qty <= 0) {
                continue;
            }

            $validItems[$id] = $qty;
            $totalQty += $qty;
            $totalPrice += $qty * (float) $product['price'];
        }

        Session::set(self::SESSION_KEY, [
            'items' => $validItems,
            'totalQty' => $totalQty,
            'totalPrice' => $totalPrice,
        ]);
    }

    /**
     * Retorna estructura de carrito vacío.
     */
    private function emptyCart(): array
    {
        return [
            'items' => [],
            'totalQty' => 0,
            'totalPrice' => 0.0,
        ];
    }
}
