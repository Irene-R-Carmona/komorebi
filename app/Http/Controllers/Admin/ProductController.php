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
use App\Repositories\Contracts\AllergenRepositoryInterface;
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
    private AllergenRepositoryInterface $allergenRepo;
    private ProductRepository $productRepo;
    private ResponseFactory $response;
    private ProductTransformer $productTransformer;

    private const CSRF_INVALID = 'Token de seguridad inválido';

    public function __construct(
        ?ProductServiceInterface $productService = null,
        ?AllergenRepositoryInterface $allergenRepo = null,
        ?ProductRepository $productRepo = null,
        ?ResponseFactory $response = null,
        ?ProductTransformer $productTransformer = null,
    ) {
        $this->productService = $productService ?? Container::make(ProductServiceInterface::class);
        $this->allergenRepo = $allergenRepo ?? Container::make(AllergenRepositoryInterface::class);
        $this->productRepo = $productRepo ?? Container::make(ProductRepository::class);
        $this->response = $response ?? new ResponseFactory();
        $this->productTransformer = $productTransformer ?? new ProductTransformer();
    }

    // ─────────────────────────────────────────────────────────────
    // Vistas principales
    // ─────────────────────────────────────────────────────────────

    /**
     * GET /admin/productos
     * Lista de productos con filtros y paginación server-side (HDA).
     */
    public function index(ServerRequestInterface $request): ?ResponseInterface
    {
        $q        = $request->getQueryParams();
        $page     = \max(1, (int) ($q['page']     ?? 1));
        $search   = \trim((string) ($q['search']   ?? ''));
        $category = (int) ($q['category']          ?? 0);
        $status   = \trim((string) ($q['status']   ?? ''));

        $filters = [];
        if ($search !== '') {
            $filters['search'] = $search;
        }
        if ($category > 0) {
            $filters['category_id'] = $category;
        }
        if ($status !== '') {
            $filters['is_active'] = $status === '1' ? 1 : 0;
        }

        $result = $this->productService->getAllPaginated($page, 20, $filters);

        // Alérgenos: una sola query para todos (N+1 prevention)
        $allergenMap = \array_column(
            $this->productRepo->getAllWithAllergens(),
            'allergens_list',
            'id',
        );

        $products = $this->productTransformer->collection($result['data']);
        foreach ($products as $k => $p) {
            $products[$k]['allergens_list'] = $allergenMap[(int) $p['id']] ?? [];
        }

        $categories    = $this->productRepo->getCategories();
        $stats         = $this->productRepo->getAdminStats();
        $currentParams = \array_filter([
            'search'   => $search,
            'category' => $category > 0 ? (string) $category : '',
            'status'   => $status,
        ], static fn ($v) => $v !== '');

        View::render('admin/products/index', [
            'titulo'        => 'Gestión de Productos | Komorebi Admin',
            'products'      => $products,
            'categories'    => $categories,
            'stats'         => $stats,
            'meta'          => [
                'page'          => $result['page'],
                'has_next_page' => $result['page'] < $result['totalPages'],
            ],
            'currentParams' => $currentParams,
            'extraJs'       => ['admin/admin-products.js'],
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
        $allergens = $this->allergenRepo->findAll(true);

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
        $product = $this->productRepo->findById($id)?->toViewArray();

        if (!$product) {
            throw NotFoundException::product($id);
        }

        // Obtener categorías
        $categories = $this->productRepo->getCategories();

        // Obtener alérgenos
        $allergens = $this->allergenRepo->findAll(true);

        // Obtener alérgenos asignados al producto
        $product_allergens = $this->productRepo->getAllergens($id);

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
            $product['allergens_list'] = $this->productRepo->getAllergens((int) $product['id']);
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
        $productDto = $this->productRepo->findById($id);

        if (!$productDto) {
            throw NotFoundException::product($id);
        }

        $product = $productDto->toViewArray();
        // Agregar alérgenos
        $product['allergens_list'] = $this->productRepo->getAllergens($id);

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
        $allergens = $this->allergenRepo->findAll(true);

        return $this->response->json(['ok' => true, 'data' => ['allergens' => $allergens]]);
    }
}
