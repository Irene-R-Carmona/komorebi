<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Core\Http\ResponseFactory;
use App\Http\Controllers\Api\AbstractApiController;
use App\Http\Transformers\AllergenTransformer;
use App\Services\MenuService;
use App\Services\RecentlyViewedService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * API de menú: alérgenos, productos y registro de visualizaciones.
 *
 * Extrae los métodos JSON que antes vivían en Public\MenuController.
 */
final class MenuController extends AbstractApiController
{
    public function __construct(
        ResponseFactory $response,
        private readonly MenuService $menuService,
        private readonly RecentlyViewedService $recentlyViewedService,
        private readonly AllergenTransformer $allergenTransformer = new AllergenTransformer(),
    ) {
        parent::__construct($response);
    }

    /**
     * GET /api/menu/alergenos
     */
    public function allergens(ServerRequestInterface $request): ResponseInterface
    {
        $allergens = $this->menuService->getAllergens();
        return $this->collection($allergens, $this->allergenTransformer);
    }

    /**
     * GET /api/menu/productos
     *
     * Products come pre-curated from the service query;
     * we return them as-is (complex nested structure with embedded allergens).
     */
    public function products(ServerRequestInterface $request): ResponseInterface
    {
        $products = $this->menuService->getProductsByCategory();
        return $this->success($products);
    }

    /**
     * POST /api/menu/view-product
     * Body: {"product_id": 123}
     */
    public function viewProduct(ServerRequestInterface $request): ResponseInterface
    {
        $input = \json_decode((string) $request->getBody(), true) ?? [];

        if (empty($input['product_id'])) {
            return $this->unprocessable('product_id requerido', 'missing_product_id');
        }

        $productId = (int) $input['product_id'];
        $this->recentlyViewedService->add($productId);

        return $this->success(['message' => 'Producto registrado']);
    }
}
