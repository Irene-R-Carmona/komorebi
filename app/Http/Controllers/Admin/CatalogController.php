<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Http\ResponseFactory;
use App\Core\View;
use App\Exceptions\ValidationException;
use App\Models\MenuCategory;
use App\Models\Product;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Random\RandomException;

/**
 * Controlador de Catálogo - Gestión de Productos y Menú
 *
 * Responsabilidades:
 * - Listado de productos/menú
 * - Alternar disponibilidad de productos
 * - Gestión de categorías
 */
final class CatalogController
{
    private Product $productModel;
    private MenuCategory $categoryModel;
    private ResponseFactory $response;

    public function __construct()
    {
        $this->productModel = new Product();
        $this->categoryModel = new MenuCategory();
        $this->response = new ResponseFactory();
    }

    /**
     * GET /manager/productos
     * Listado de productos con filtros
     */
    public function index(): void
    {
        $categories = $this->categoryModel->findAll();
        $products = $this->productModel->findAllAdmin();

        View::render('backoffice/manager/productos', [
            'titulo' => 'Catálogo de Productos',
            'products' => $products,
            'categories' => $categories,
            'extraJs' => ['admin/admin-products.js'],
        ], ['admin/admin-products.css'], 'backoffice');
    }

    /**
     * POST /manager/productos/toggle
     * Alterna la disponibilidad de un producto
     *
     * @throws JsonException
     * @throws RandomException
     */
    public function toggle(): ResponseInterface
    {
        if (!Csrf::validate()) {
            throw ValidationException::withMessage('Token de seguridad inválido', 419);
        }

        $productId = \filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);

        if (!$productId) {
            throw ValidationException::withMessage('ID de producto inválido', 400);
        }

        $product = $this->productModel->findById($productId);

        if (!$product) {
            throw ValidationException::withMessage('Producto no encontrado', 404);
        }

        if ($this->productModel->toggleAvailability($productId)) {
            return $this->response->json(['ok' => true, 'data' => [
                'message' => 'Producto actualizado correctamente',
                'is_active' => !$product['is_active'],
            ]]);
        }

        throw ValidationException::withMessage('Error al actualizar el producto', 500);
    }
}
