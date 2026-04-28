<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\View;
use App\Http\Transformers\ProductTransformer;
use App\Repositories\Contracts\MenuCategoryRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Random\RandomException;

/**
 * Controlador de Gestión de Productos
 *
 * Responsabilidad única: CRUD completo de productos del menú
 */
final class MenuController
{
    private ProductTransformer $productTransformer;
    private ProductRepositoryInterface $productRepo;
    private MenuCategoryRepositoryInterface $categoryRepo;

    public function __construct(?ProductTransformer $productTransformer = null, ?ProductRepositoryInterface $productRepo = null, ?MenuCategoryRepositoryInterface $categoryRepo = null)
    {
        $this->productTransformer = $productTransformer ?? new ProductTransformer();
        $this->productRepo = $productRepo ?? Container::make(ProductRepositoryInterface::class);
        $this->categoryRepo = $categoryRepo ?? Container::make(MenuCategoryRepositoryInterface::class);
    }

    /**
     * GET /admin/productos
     * Lista de productos con categorías
     *
     * @throws RandomException
     */
    public function index(ServerRequestInterface $request): ?ResponseInterface
    {
        $productsData = $this->productRepo->findAllAdmin();
        $categories = \array_map(fn($dto) => $dto->toViewArray(), $this->categoryRepo->findAll());

        // Mapeo de categorías de café para UI
        $cafeTypeLabels = [
            'lounge' => 'Lounge',
            'playroom' => 'Playroom',
            'farm' => 'Farm',
            'zen' => 'Zen',
        ];

        // Cargar alérgenos y formatear disponibilidad por café para cada producto
        foreach ($productsData['data'] as &$product) {
            $product['allergens_list'] = $this->productRepo->getAllergens((int) $product['id']);

            // Formatear target_cafe_types
            if (!empty($product['target_cafe_types']) && \is_array($product['target_cafe_types'])) {
                $product['cafe_types_display'] = \array_map(
                    fn($type) => $cafeTypeLabels[$type] ?? $type,
                    $product['target_cafe_types']
                );
            } else {
                $product['cafe_types_display'] = ['Todos los cafés'];
            }
        }
        unset($product);

        // Aplicar transformer (excluye campos de cocina) y re-inyectar campos de display seguros
        $rawProducts = $productsData['data'] ?? [];
        $products = $this->productTransformer->collection($rawProducts);
        foreach ($products as $key => $product) {
            $products[$key]['allergens_list'] = $rawProducts[$key]['allergens_list'] ?? [];
            $products[$key]['cafe_types_display'] = $rawProducts[$key]['cafe_types_display'] ?? ['Todos los cafés'];
        }

        View::render('admin/products/index', [
            'titulo' => 'Gestión de Productos',
            'products' => $products,
            'categories' => $categories,
            'total' => $productsData['total'] ?? 0,
            'csrf_token' => Csrf::token(),
            'extraJs' => ['admin/admin-products.js'],
        ], ['admin/admin-products.css'], 'backoffice');

        return null;
    }
}
