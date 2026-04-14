<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manager;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Http\ResponseFactory;
use App\Core\Logger;
use App\Core\Raw;
use App\Core\Session;
use App\Core\View;
use App\Exceptions\ValidationException;
use App\Models\MenuCategory;
use App\Models\Product;
use App\Repositories\ProductRepository;
use App\Services\Contracts\ProductServiceInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Gestión de productos del catálogo (scope: café del manager)
 *
 * Permite al manager gestionar los productos de su café asignado.
 * Scope: Solo opera sobre productos del café especificado en user.cafe_id.
 */
final class ProductController
{
    private ProductServiceInterface $productService;
    private ResponseFactory $response;

    public function __construct(
        ?ProductServiceInterface $productService = null,
        ?ResponseFactory $response = null
    ) {
        $this->productService = $productService ?? Container::make(ProductServiceInterface::class);
        $this->response       = $response ?? new ResponseFactory();
    }

    /**
     * GET /manager/products
     * Lista de productos con categorías para el café del manager.
     */
    public function index(ServerRequestInterface $request): ?ResponseInterface
    {
        $user   = Session::user();
        $cafeId = $user['cafe_id'] ?? null;

        if (!$cafeId) {
            View::render('errors/403', ['message' => 'No tienes un café asignado.']);
            return null;
        }

        $productModel  = new Product();
        $categoryModel = new MenuCategory();

        $productsData = $productModel->findAllAdmin();
        $categories   = $categoryModel->findAll();

        $alpineConfig = Raw::json([
            'products'   => $productsData['data'] ?? [],
            'categories' => $categories,
            'cafeId'     => $cafeId,
            'csrfToken'  => Csrf::token(),
        ]);

        View::render('manager/products/index', [
            'titulo'       => 'Gestión de Productos',
            'alpineConfig' => $alpineConfig,
            'total'        => $productsData['total'] ?? 0,
        ], ['admin/admin-products.css'], 'backoffice');
        return null;
    }

    /**
     * POST /manager/products/create
     * Crear nuevo producto para el café del manager.
     */
    public function create(ServerRequestInterface $request): ResponseInterface
    {
        if (!Csrf::validate($request)) {
            throw ValidationException::withMessage('Token de seguridad inválido', 419);
        }

        $body = $request->getParsedBody();

        try {
            $productId = $this->productService->create($body);

            Logger::info('[Manager\ProductController] Producto creado', [
                'manager_id' => Session::get('user_id'),
                'product_id' => $productId,
            ]);

            return $this->response->json(['ok' => true, 'data' => [
                'message'    => 'El producto se ha creado correctamente.',
                'product_id' => $productId,
            ]]);
        } catch (ValidationException $e) {
            throw ValidationException::withMessage($e->getMessage(), 422);
        }
    }

    /**
     * POST /manager/products/{productId}/update
     * Actualizar producto existente.
     */
    public function update(ServerRequestInterface $request, int $productId): ResponseInterface
    {
        if (!Csrf::validate($request)) {
            throw ValidationException::withMessage('Token de seguridad inválido', 419);
        }

        $body = $request->getParsedBody();

        try {
            $this->productService->update($productId, $body);

            Logger::info('[Manager\ProductController] Producto actualizado', [
                'manager_id' => Session::get('user_id'),
                'product_id' => $productId,
            ]);

            return $this->response->json(['ok' => true, 'data' => [
                'message' => 'El producto se ha actualizado correctamente.',
            ]]);
        } catch (ValidationException $e) {
            throw ValidationException::withMessage($e->getMessage(), 422);
        }
    }

    /**
     * POST /manager/products/{productId}/toggle
     * Activar/desactivar disponibilidad de un producto.
     */
    public function toggleAvailability(ServerRequestInterface $request, int $productId): ResponseInterface
    {
        if (!Csrf::validate($request)) {
            throw ValidationException::withMessage('Token de seguridad inválido', 419);
        }

        $success = $this->productService->toggleActive($productId);

        if ($success) {
            return $this->response->json(['ok' => true, 'data' => [
                'message' => 'Estado de disponibilidad actualizado.',
            ]]);
        }

        throw ValidationException::withMessage('No se pudo actualizar el producto', 500);
    }

    /**
     * POST /manager/products/{productId}/delete
     * Eliminar producto.
     */
    public function delete(ServerRequestInterface $request, int $productId): ResponseInterface
    {
        if (!Csrf::validate($request)) {
            throw ValidationException::withMessage('Token de seguridad inválido', 419);
        }

        $this->productService->delete($productId);

        Logger::info('[Manager\ProductController] Producto eliminado', [
            'manager_id' => Session::get('user_id'),
            'product_id' => $productId,
        ]);

        return $this->response->json(['ok' => true, 'data' => [
            'message' => 'El producto se ha eliminado correctamente.',
        ]]);
    }
}
