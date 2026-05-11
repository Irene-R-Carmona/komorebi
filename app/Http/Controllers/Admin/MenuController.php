<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Http\ResponseFactory;
use App\Core\Pagination;
use App\Core\View;
use App\Http\Transformers\ProductTransformer;
use App\Repositories\Contracts\AllergenRepositoryInterface;
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
    private AllergenRepositoryInterface $allergenRepo;

    public function __construct(?ProductTransformer $productTransformer = null, ?ProductRepositoryInterface $productRepo = null, ?MenuCategoryRepositoryInterface $categoryRepo = null, ?AllergenRepositoryInterface $allergenRepo = null)
    {
        $this->productTransformer = $productTransformer ?? new ProductTransformer();
        $this->productRepo = $productRepo ?? Container::make(ProductRepositoryInterface::class);
        $this->categoryRepo = $categoryRepo ?? Container::make(MenuCategoryRepositoryInterface::class);
        $this->allergenRepo = $allergenRepo ?? Container::make(AllergenRepositoryInterface::class);
    }

    /**
     * GET /admin/productos
     * Lista de productos con categorías
     *
     * @throws RandomException
     */
    public function index(ServerRequestInterface $request): ?ResponseInterface
    {
        $q = $request->getQueryParams();
        $page = \max(1, (int) ($q['page'] ?? 1));
        $perPage = Pagination::DEFAULT_LIMIT;
        $search = \trim((string) ($q['search'] ?? ''));
        $categoryId = (int) ($q['category'] ?? 0);
        $status = \trim((string) ($q['status'] ?? ''));
        $allergen = \trim((string) ($q['allergen'] ?? ''));

        $filters = [];
        if ($search !== '') {
            $filters['search'] = $search;
        }
        if ($categoryId > 0) {
            $filters['category_id'] = $categoryId;
        }
        if ($status === '1' || $status === '0') {
            $filters['is_active'] = (int) $status;
        }
        if ($allergen !== '') {
            $filters['allergen_code'] = $allergen;
        }

        $productsData = $this->productRepo->findFiltered($filters, $page, $perPage);
        $categories = \array_map(fn ($dto) => $dto->toViewArray(), $this->categoryRepo->findAll());

        $totalAll = $this->productRepo->findFiltered([], 1, 1)['total'] ?? 0;
        $totalActive = $this->productRepo->findFiltered(['is_active' => 1], 1, 1)['total'] ?? 0;

        // Mapeo de categorías de café para UI
        $cafeTypeLabels = [
            'lounge' => 'Lounge',
            'playroom' => 'Playroom',
            'farm' => 'Farm',
            'zen' => 'Zen',
        ];

        // Cargar alérgenos y formatear disponibilidad por café para cada producto
        $rawData = $productsData['data'] ?? [];
        foreach ($rawData as &$product) {
            // Decodificar campos JSON (findFiltered no los decodifica)
            foreach (['attributes', 'target_cafe_types', 'target_animal_types'] as $field) {
                if (\is_string($product[$field] ?? null) && $product[$field] !== '') {
                    $decoded = \json_decode($product[$field], true);
                    $product[$field] = \is_array($decoded) ? $decoded : null;
                } elseif (!isset($product[$field])) {
                    $product[$field] = null;
                }
            }

            $product['allergens_list'] = $this->productRepo->getAllergens((int) $product['id']);

            // Formatear target_cafe_types
            if (!empty($product['target_cafe_types']) && \is_array($product['target_cafe_types'])) {
                $product['cafe_types_display'] = \array_map(
                    fn ($type) => $cafeTypeLabels[$type] ?? $type,
                    $product['target_cafe_types']
                );
            } else {
                $product['cafe_types_display'] = ['Todos los cafés'];
            }
        }
        unset($product);

        // Aplicar transformer (excluye campos de cocina) y re-inyectar campos de display seguros
        $products = $this->productTransformer->collection($rawData);
        foreach ($products as $key => $product) {
            $products[$key]['allergens_list'] = $rawData[$key]['allergens_list'] ?? [];
            $products[$key]['cafe_types_display'] = $rawData[$key]['cafe_types_display'] ?? ['Todos los cafés'];
        }

        $total = $productsData['total'] ?? 0;
        $totalPages = $productsData['totalPages'] ?? 1;
        $meta = ['page' => $page, 'has_next_page' => $page < $totalPages];

        $currentParams = \array_filter([
            'search' => $search,
            'category' => $categoryId > 0 ? (string) $categoryId : '',
            'status' => $status,
            'allergen' => $allergen,
        ], static fn ($v) => $v !== '');

        $stats = [
            'total_products' => $totalAll,
            'active_products' => $totalActive,
            'category_count' => \count($categories),
            'with_allergens' => 0,
        ];

        View::render('admin/products/index', [
            'titulo' => 'Gestión de Productos | Komorebi Admin',
            'products' => $products,
            'categories' => $categories,
            'total' => $total,
            'meta' => $meta,
            'currentParams' => $currentParams,
            'stats' => $stats,
            'csrf_token' => Csrf::token(),
            'extraJs' => ['admin/admin-products.js'],
        ], ['admin/admin-products.css'], 'backoffice');

        return null;
    }

    /**
     * GET /admin/menu/{id}/edit
     * Formulario de edición de producto
     *
     * @throws RandomException
     */
    public function edit(ServerRequestInterface $request): ?ResponseInterface
    {
        $id = (int) $request->getAttribute('id');
        $productDto = $id > 0 ? $this->productRepo->findById($id) : null;

        if ($productDto === null) {
            return new ResponseFactory()->redirect('/admin/menu');
        }

        $product = $productDto->toViewArray();
        $categories = \array_map(fn ($dto) => $dto->toViewArray(), $this->categoryRepo->findAll());
        $allergens = \array_map(fn ($dto) => $dto->toViewArray(), $this->allergenRepo->findAll(true));
        $productAllergens = $this->productRepo->getAllergens($id);

        View::render('admin/products/edit', [
            'titulo' => 'Editar Producto | Komorebi Admin',
            'product' => $product,
            'categories' => $categories,
            'allergens' => $allergens,
            'product_allergens' => $productAllergens,
            'csrf_token' => Csrf::token(),
        ], [], 'backoffice');

        return null;
    }

    /**
     * GET /admin/menu/create
     * Formulario de creación de producto
     *
     * @throws RandomException
     */
    public function create(ServerRequestInterface $request): ?ResponseInterface
    {
        $categories = \array_map(fn ($dto) => $dto->toViewArray(), $this->categoryRepo->findAll());
        $allergens = \array_map(fn ($dto) => $dto->toViewArray(), $this->allergenRepo->findAll(true));

        View::render('admin/products/create', [
            'titulo' => 'Nuevo Producto | Komorebi Admin',
            'categories' => $categories,
            'allergens' => $allergens,
            'csrf_token' => Csrf::token(),
        ], [], 'backoffice');

        return null;
    }
}
