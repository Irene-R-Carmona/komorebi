<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manager;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Http\ResponseFactory;
use App\Core\Logger;
use App\Core\Session;
use App\Core\View;
use App\Exceptions\ValidationException;
use App\Repositories\Contracts\MenuCategoryRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Services\Contracts\ProductServiceInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Gestión de productos del catálogo (scope: café del manager)
 */
final class ProductController
{
    private const string CSRF_INVALID = 'Token de seguridad inválido';

    private ProductServiceInterface $productService;
    private ProductRepositoryInterface $productRepo;
    private MenuCategoryRepositoryInterface $categoryRepo;
    private ResponseFactory $response;

    public function __construct(
        ?ProductServiceInterface $productService = null,
        ?ProductRepositoryInterface $productRepo = null,
        ?MenuCategoryRepositoryInterface $categoryRepo = null,
        ?ResponseFactory $response = null
    ) {
        $this->productService = $productService ?? Container::make(ProductServiceInterface::class);
        $this->productRepo = $productRepo ?? Container::make(ProductRepositoryInterface::class);
        $this->categoryRepo = $categoryRepo ?? Container::make(MenuCategoryRepositoryInterface::class);
        $this->response = $response ?? new ResponseFactory();
    }

    /**
     * GET /manager/products
     */
    public function index(ServerRequestInterface $request): ?ResponseInterface
    {
        $user = Session::user();
        $cafeId = $user['cafe_id'] ?? null;

        if (!$cafeId) {
            if (!\headers_sent()) {
                @\http_response_code(403);
            }
            View::render('errors/403', ['message' => 'No tienes un café asignado.'], [], 'errors');

            return null;
        }

        $query = $request->getQueryParams();
        $search = \trim((string) ($query['search'] ?? ''));

        $filters = [];
        if ($search !== '') {
            $filters['search'] = $search;
        }

        $productsData = $this->productRepo->findFiltered($filters, 1, 200);
        $categories = \array_map(
            static fn($dto) => $dto->toViewArray(),
            $this->categoryRepo->findAll()
        );

        View::render('manager/products/index', [
            'titulo' => 'Gestión de Productos',
            'products' => $productsData['data'] ?? [],
            'categories' => $categories,
            'cafeId' => (int) $cafeId,
            'search' => $search,
            'total' => $productsData['total'] ?? 0,
            'extraJs' => ['manager/manager-products.js'],
        ], ['admin/admin-products.css'], 'backoffice');

        return null;
    }

    /**
     * POST /manager/products/create
     */
    public function create(ServerRequestInterface $request): ResponseInterface
    {
        if (!Csrf::validate($request)) {
            throw ValidationException::withMessage(self::CSRF_INVALID, 419);
        }

        try {
            $body = $request->getParsedBody();
            unset($body['image_url']); // Solo admin puede modificar image_url
            $productId = $this->productService->create($body);

            Logger::info('[Manager\ProductController] Producto creado', [
                'manager_id' => Session::get('user_id'),
                'product_id' => $productId,
            ]);

            return $this->response->json(['ok' => true, 'data' => [
                'message' => 'El producto se ha creado correctamente.',
                'product_id' => $productId,
            ]]);
        } catch (ValidationException $e) {
            throw ValidationException::withMessage($e->getMessage(), 422);
        }
    }

    /**
     * POST /manager/products/{productId}/update
     */
    public function update(ServerRequestInterface $request, int $productId): ResponseInterface
    {
        if (!Csrf::validate($request)) {
            throw ValidationException::withMessage(self::CSRF_INVALID, 419);
        }

        try {
            $body = $request->getParsedBody();
            unset($body['image_url']); // Solo admin puede modificar image_url
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
     */
    public function toggleAvailability(ServerRequestInterface $request, int $productId): ResponseInterface
    {
        if (!Csrf::validate($request)) {
            throw ValidationException::withMessage(self::CSRF_INVALID, 419);
        }

        if ($this->productService->toggleActive($productId)) {
            return $this->response->json(['ok' => true, 'data' => [
                'message' => 'Estado de disponibilidad actualizado.',
            ]]);
        }

        throw ValidationException::withMessage('No se pudo actualizar el producto', 500);
    }

    /**
     * POST /manager/products/{productId}/delete
     */
    public function delete(ServerRequestInterface $request, int $productId): ResponseInterface
    {
        if (!Csrf::validate($request)) {
            throw ValidationException::withMessage(self::CSRF_INVALID, 419);
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
