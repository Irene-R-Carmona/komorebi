<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Core\Http\ResponseFactory;
use App\Http\Controllers\Api\AbstractApiController;
use App\Http\Transformers\CafeTransformer;
use App\Repositories\Contracts\CafeRepositoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * API pública de cafés.
 *
 * Respuestas cacheables públicas con ETag y Cache-Control.
 */
final class CafeController extends AbstractApiController
{
    public function __construct(
        ResponseFactory $response,
        private readonly CafeRepositoryInterface $cafeRepo,
        private readonly CafeTransformer $transformer = new CafeTransformer(),
    ) {
        parent::__construct($response);
    }

    /**
     * GET /api/v1/cafes
     *
     * Devuelve todos los cafés activos.
     * Respuesta cacheable con ETag + Cache-Control público.
     */
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $cafes = $this->cafeRepo->findActive();
        $etag = $this->makeEtag($cafes);
        $cc = 'public, max-age=3600, stale-while-revalidate=86400';

        if ($request->getHeaderLine('If-None-Match') === $etag) {
            return $this->notModified($etag, $cc);
        }

        return $this->collection($cafes, $this->transformer, [], [
            'Cache-Control' => $cc,
            'ETag' => $etag,
        ]);
    }

    /**
     * GET /api/v1/cafes/{slug}
     *
     * Devuelve un café por slug. 404 si no existe.
     */
    public function show(ServerRequestInterface $request): ResponseInterface
    {
        $slug = (string) ($request->getAttribute('slug') ?? '');

        if ($slug === '') {
            return $this->unprocessable('slug requerido', 'invalid_slug');
        }

        $cafe = $this->cafeRepo->findBySlug($slug);

        if ($cafe === null) {
            return $this->notFound('Café no encontrado');
        }

        return $this->transform($cafe, $this->transformer);
    }
}
