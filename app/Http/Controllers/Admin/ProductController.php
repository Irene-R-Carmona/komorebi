<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Http\ResponseFactory;
use App\Core\Session;
use App\Core\View;
use App\Exceptions\DatabaseException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Http\Transformers\ProductTransformer;
use App\Models\Allergen;
use App\Models\Product;
use App\Repositories\ProductRepository;
use App\Services\Contracts\ProductServiceInterface;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Random\RandomException;

/**
 * Controlador de Productos (Backoffice)
 *
 * Gestiona el CRUD completo de productos del menú con alérgenos.
 * Requiere permisos: product.view, product.create, product.edit, product.delete
 */
final class ProductController
{
    private ProductServiceInterface $productService;
    private Product $productModel;
    private Allergen $allergenModel;
    private ProductRepository $productRepo;
    private ResponseFactory $response;
    private ProductTransformer $productTransformer;

    private const CSRF_INVALID = 'Token de seguridad inválido';

    public function __construct()
    {
        $this->productService = Container::make(ProductServiceInterface::class);
        $this->productModel = new Product();
        $this->allergenModel = new Allergen();
        $this->productRepo = new ProductRepository();
        $this->response = new ResponseFactory();
        $this->productTransformer = new ProductTransformer();
    }

    // ─────────────────────────────────────────────────────────────
    // Vistas principales
    // ─────────────────────────────────────────────────────────────

    /**
     * GET /admin/productos
     * Lista de productos con filtros y paginación
     */
    public function index(ServerRequestInterface $request): ?ResponseInterface
    {
        $queryParams = $request->getQueryParams();

        // Obtener parámetros de paginación
        $page = \filter_var($queryParams['page'] ?? null, FILTER_VALIDATE_INT) ?: 1;
        $perPage = \filter_var($queryParams['per_page'] ?? null, FILTER_VALIDATE_INT) ?: 20;

        // Obtener filtros
        $filters = [];

        if ($categoryId = \filter_var($queryParams['category'] ?? null, FILTER_VALIDATE_INT)) {
            $filters['category_id'] = $categoryId;
        }

        if (isset($queryParams['is_active'])) {
            $filters['is_active'] = $queryParams['is_active'] === '1' ? 1 : 0;
        }

        if ($search = \filter_var($queryParams['search'] ?? null, FILTER_SANITIZE_SPECIAL_CHARS)) {
            $filters['search'] = $search;
        }

        if ($type = \filter_var($queryParams['type'] ?? null, FILTER_SANITIZE_SPECIAL_CHARS)) {
            $filters['product_type'] = $type;
        }

        // Obtener productos paginados con filtros
        $result = $this->productService->getAllPaginated($page, $perPage, $filters);

        // Cargar alérgenos en una única consulta (evita N+1)
        $allProductsWithAllergens = $this->productRepo->getAllWithAllergens();
        $allergenMap = array_column($allProductsWithAllergens, 'allergens_list', 'id');
        foreach ($result['data'] as &$product) {
            $product['allergens_list'] = $allergenMap[(int) $product['id']] ?? [];
        }
        unset($product);

        // Aplicar transformer (excluye campos de cocina) y re-inyectar alérgenos (campo de display seguro)
        $products = $this->productTransformer->collection($result['data']);
        foreach ($products as $key => $product) {
            $products[$key]['allergens_list'] = $allergenMap[(int) $product['id']] ?? [];
        }

        // Obtener categorías para filtros
        $categories = $this->productRepo->getCategories();

        View::render('admin/products/index', [
            'titulo' => 'Gestión de Productos | Komorebi Admin',
            'products' => $products,
            'categories' => $categories,
            'pagination' => [
                'current_page' => $result['page'],
                'per_page' => $result['perPage'],
                'total' => $result['total'],
                'total_pages' => $result['totalPages'],
                'has_prev' => $result['page'] > 1,
                'has_next' => $result['page'] < $result['totalPages'],
            ],
            'filters' => $filters,
        ], [], 'backoffice');
        return null;
    }

    /**
     * GET /admin/productos/crear
     * Formulario de creación
     * @throws RandomException
     */
    public function create(ServerRequestInterface $request): ?ResponseInterface
    {
        // Obtener categorías
        $categories = $this->productRepo->getCategories();

        // Obtener alérgenos
        $allergens = $this->allergenModel->getAll(true);

        View::render('admin/products/create', [
            'titulo' => 'Nuevo Producto | Komorebi Admin',
            'categories' => $categories,
            'allergens' => $allergens,
            'csrf_token' => Csrf::token(),
        ], [], 'backoffice');
        return null;
    }

    /**
     * POST /admin/productos/crear
     * Procesar creación
     *
     * ExceptionHandler maneja automáticamente:
     * - ValidationException → 422 JSON con errores
     * - DatabaseException → 500 JSON (sin exponer detalles)
     *
     * @throws JsonException
     * @throws RandomException
     * @throws DatabaseException
     * @throws ValidationException
     */
    public function store(): ResponseInterface
    {
        if (!Csrf::validate()) {
            throw ValidationException::withMessage(self::CSRF_INVALID, 419);
        }

        // Extraer IDs de alérgenos
        $allergenIds = $_POST['allergens'] ?? [];

        // Crear producto (lanza ValidationException o DatabaseException si falla)
        $productId = $this->productService->create($_POST); // NOSONAR

        // Sincronizar alérgenos (lanza DatabaseException si falla)
        if (!empty($allergenIds)) {
            $this->productService->syncAllergens($productId, $allergenIds);
        }

        Session::set('flash_success', 'Producto creado correctamente');
        return $this->response->json(['ok' => true, 'data' => ['redirect' => '/admin/productos']]);
    }

    /**
     * GET /admin/productos/{id}/editar
     * Formulario de edición
     *
     * @throws RandomException
     * @throws NotFoundException Si el producto no existe (manejado por ExceptionHandler)
     */
    public function edit(ServerRequestInterface $request, int $id): ?ResponseInterface
    {
        $product = $this->productModel->findById($id);

        if (!$product) {
            throw NotFoundException::product($id);
        }

        // Obtener categorías
        $categories = $this->productRepo->getCategories();

        // Obtener alérgenos
        $allergens = $this->allergenModel->getAll(true);

        // Obtener alérgenos asignados al producto
        $product_allergens = $this->productModel->getAllergensNormalized($id);

        View::render('admin/products/edit', [
            'titulo' => 'Editar Producto | Komorebi Admin',
            'product' => $product,
            'categories' => $categories,
            'allergens' => $allergens,
            'product_allergens' => $product_allergens,
            'csrf_token' => Csrf::token(),
        ], [], 'backoffice');
        return null;
    }

    /**
     * POST /admin/productos/{id}/actualizar
     * Procesar actualización
     *
     * ExceptionHandler maneja automáticamente:
     * - ValidationException → 422 JSON con errores
     * - DatabaseException → 500 JSON
     *
     * @param integer $id
     * @throws DatabaseException
     * @throws JsonException
     * @throws RandomException
     * @throws ValidationException
     */
    public function update(int $id): ResponseInterface
    {
        if (!Csrf::validate()) {
            throw ValidationException::withMessage(self::CSRF_INVALID, 419);
        }

        // Extraer IDs de alérgenos
        $allergenIds = $_POST['allergens'] ?? [];

        // Actualizar producto (lanza ValidationException o DatabaseException si falla)
        $this->productService->update($id, $_POST); // NOSONAR

        // Sincronizar alérgenos (lanza DatabaseException si falla)
        $this->productService->syncAllergens($id, $allergenIds);

        Session::set('flash_success', 'Producto actualizado correctamente');
        return $this->response->json(['ok' => true, 'data' => ['redirect' => '/admin/productos']]);
    }

    /**
     * POST /admin/productos/{id}/eliminar
     * Eliminar producto (soft delete)
     *
     * ExceptionHandler maneja automáticamente DatabaseException
     *
     * @param integer $id
     * @throws DatabaseException
     * @throws JsonException
     * @throws RandomException
     */
    public function delete(int $id): ResponseInterface
    {
        if (!Csrf::validate()) {
            throw ValidationException::withMessage(self::CSRF_INVALID, 419);
        }

        $this->productService->delete($id);
        return $this->response->json(['ok' => true, 'data' => ['message' => 'Producto eliminado correctamente']]);
    }

    /**
     * POST /admin/productos/{id}/toggle
     * Alternar disponibilidad
     *
     * ExceptionHandler maneja automáticamente DatabaseException
     *
     * @param integer $id
     * @throws JsonException
     * @throws RandomException
     */
    public function toggleAvailability(int $id): ResponseInterface
    {
        if (!Csrf::validate()) {
            throw ValidationException::withMessage(self::CSRF_INVALID, 419);
        }

        $this->productService->toggleActive($id);
        return $this->response->json(['ok' => true, 'data' => ['message' => 'Estado actualizado']]);
    }

    // ─────────────────────────────────────────────────────────────
    // API Endpoints
    // ─────────────────────────────────────────────────────────────

    /**
     * GET /api/admin/productos
     * Lista de productos en formato JSON
     *
     * ExceptionHandler maneja automáticamente cualquier excepción
     *
     * @throws JsonException
     */
    public function apiList(): ResponseInterface
    {
        $categoryId = \filter_input(INPUT_GET, 'category', FILTER_VALIDATE_INT) ?: null;
        $search = \filter_input(INPUT_GET, 'search', FILTER_SANITIZE_SPECIAL_CHARS) ?: null;

        if ($search) {
            $products = $this->productService->search($search);
        } elseif ($categoryId) {
            $products = $this->productService->getByCategory($categoryId);
        } else {
            $products = $this->productService->getAll();
        }

        // Cargar alérgenos
        foreach ($products as &$product) {
            $product['allergens_list'] = $this->productModel->getAllergensNormalized((int) $product['id']);
        }
        unset($product); // Liberar referencia

        return $this->response->json(['ok' => true, 'data' => $products]);
    }

    /**
     * GET /api/admin/productos/{id}
     * Detalle de producto en formato JSON
     *
     * @throws JsonException
     * @throws NotFoundException Si el producto no existe
     */
    public function apiShow(int $id): ResponseInterface
    {
        $product = $this->productModel->findById($id);

        if (!$product) {
            throw NotFoundException::product($id);
        }

        // Agregar alérgenos
        $product['allergens_list'] = $this->productModel->getAllergensNormalized($id);

        return $this->response->json(['ok' => true, 'data' => $product]);
    }

    /**
     * GET /api/admin/alergenos
     * Lista de alérgenos disponibles
     *
     * ExceptionHandler maneja automáticamente cualquier excepción
     *
     * @throws JsonException
     */
    public function apiAllergens(): ResponseInterface
    {
        $allergens = $this->allergenModel->getAll(true);
        return $this->response->json(['ok' => true, 'data' => ['allergens' => $allergens]]);
    }
}
