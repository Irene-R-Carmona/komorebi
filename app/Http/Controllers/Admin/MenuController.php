<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Http\ResponseFactory;
use App\Core\View;
use App\Exceptions\DatabaseException;
use App\Exceptions\ValidationException;
use App\Http\Transformers\ProductTransformer;
use App\Repositories\Contracts\MenuCategoryRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Services\Contracts\ProductServiceInterface;
use JsonException;
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
    private ProductServiceInterface $productService;
    private ProductRepositoryInterface $productRepo;
    private MenuCategoryRepositoryInterface $categoryRepo;
    private ResponseFactory $response;
    private ProductTransformer $productTransformer;

    private const CSRF_INVALID = 'Token de seguridad inválido';

    public function __construct(
        ?ProductServiceInterface $productService = null,
        ?ProductRepositoryInterface $productRepo = null,
        ?MenuCategoryRepositoryInterface $categoryRepo = null,
        ?ResponseFactory $response = null,
        ?ProductTransformer $productTransformer = null
    ) {
        $this->productService     = $productService ?? Container::make(ProductServiceInterface::class);
        $this->productRepo        = $productRepo ?? Container::make(ProductRepositoryInterface::class);
        $this->categoryRepo       = $categoryRepo ?? Container::make(MenuCategoryRepositoryInterface::class);
        $this->response           = $response ?? new ResponseFactory();
        $this->productTransformer = $productTransformer ?? new ProductTransformer();
    }

    /**
     * GET /admin/productos
     * Lista de productos con categorías
     *
     * @throws RandomException
     */
    public function index(): ?ResponseInterface
    {
        $productsData = $this->productRepo->findFiltered([], 1, 200);
        $categories   = $this->categoryRepo->findAll();

        // Mapeo de categorías de café para UI
        $cafeTypeLabels = [
            'lounge'   => 'Lounge',
            'playroom' => 'Playroom',
            'farm'     => 'Farm',
            'zen'      => 'Zen',
        ];

        // Cargar alérgenos y formatear disponibilidad por café para cada producto
        foreach ($productsData['data'] as &$product) {
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

    /**
     * POST /admin/productos/create
     * Crear nuevo producto
     *
     * @throws JsonException
     * @throws RandomException
     * @throws ValidationException
     * @throws DatabaseException
     */
    public function create(): ResponseInterface
    {
        if (!Csrf::validate()) {
            throw ValidationException::withMessage(self::CSRF_INVALID, 419);
        }

        try {
            $productId = $this->productService->create($_POST); // NOSONAR

            return $this->response->json(['ok' => true, 'data' => [
                'message' => 'El producto se ha creado correctamente.',
                'product_id' => $productId,
            ]]);
        } catch (ValidationException $e) {
            // Errores de validación desde el servicio → convertir a ValidationException
            throw ValidationException::withMessage($e->getMessage(), 422); // NOSONAR
        }
    }

    /**
     * POST /admin/productos/{productId}/edit
     * Actualizar producto existente
     *
     * @param integer $productId
     *
     * @throws DatabaseException
     * @throws JsonException
     * @throws RandomException
     * @throws ValidationException
     */
    public function update(int $productId): ResponseInterface
    {
        if (!Csrf::validate()) {
            throw ValidationException::withMessage(self::CSRF_INVALID, 419);
        }

        try {
            $this->productService->update($productId, $_POST); // NOSONAR

            return $this->response->json(['ok' => true, 'data' => [
                'message' => 'El producto se ha actualizado correctamente.',
            ]]);
        } catch (ValidationException $e) {
            throw ValidationException::withMessage($e->getMessage(), 422); // NOSONAR
        }
    }

    /**
     * POST /admin/productos/{productId}/toggle-available
     * Activar/desactivar disponibilidad de producto
     *
     * @param integer $productId
     *
     * @throws JsonException
     * @throws RandomException
     * @throws ValidationException
     */
    public function toggleAvailability(int $productId): ResponseInterface
    {
        if (!Csrf::validate()) {
            throw ValidationException::withMessage(self::CSRF_INVALID, 419);
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
     * POST /admin/productos/{productId}/delete
     * Eliminar producto (desactivar)
     *
     * @param integer $productId
     *
     * @throws DatabaseException
     * @throws JsonException
     * @throws RandomException
     * @throws ValidationException
     */
    public function delete(int $productId): ResponseInterface
    {
        if (!Csrf::validate()) {
            throw ValidationException::withMessage(self::CSRF_INVALID, 419);
        }

        $this->productService->delete($productId);

        return $this->response->json(['ok' => true, 'data' => [
            'message' => 'El producto se ha eliminado correctamente.',
        ]]);
    }
}
