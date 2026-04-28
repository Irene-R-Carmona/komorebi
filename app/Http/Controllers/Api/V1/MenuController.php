<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Core\Http\ResponseFactory;
use App\Http\Controllers\Api\AbstractApiController;
use App\Http\Transformers\AllergenTransformer;
use App\Services\Contracts\MenuServiceInterface;
use App\Services\Contracts\RecentlyViewedServiceInterface;
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
        private readonly MenuServiceInterface $menuService,
        private readonly RecentlyViewedServiceInterface $recentlyViewedService,
        private readonly AllergenTransformer $allergenTransformer = new AllergenTransformer(),
    ) {
        parent::__construct($response);
    }

    /**
     * GET /api/menu/alergenos
     */
    public function allergens(ServerRequestInterface $request): ResponseInterface
    {
        $allergens  = $this->menuService->getAllergens();
        $etag       = $this->makeEtag($allergens);
        $cc         = 'public, max-age=3600, stale-while-revalidate=86400';

        if ($request->getHeaderLine('If-None-Match') === $etag) {
            return $this->notModified($etag, $cc);
        }

        return $this->collection($allergens, $this->allergenTransformer, [], [
            'Cache-Control' => $cc,
            'ETag'          => $etag,
        ]);
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
     * GET /api/v1/menu/products/{id}
     * Registra visualización del producto y retorna confirmación.
     */
    public function getProduct(ServerRequestInterface $request): ResponseInterface
    {
        $id = (int) ($request->getAttribute('id') ?? 0);

        if ($id <= 0) {
            return $this->unprocessable('id requerido y debe ser positivo', 'invalid_id');
        }

        $this->recentlyViewedService->add($id);

        $data = ['product_id' => $id];
        $etag = $this->makeEtag($data);
        $cc   = 'public, max-age=300';

        if ($request->getHeaderLine('If-None-Match') === $etag) {
            return $this->notModified($etag, $cc);
        }

        return $this->success($data, 200, [
            'Cache-Control' => $cc,
            'ETag'          => $etag,
        ]);
    }
}
