<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Core\Http\ResponseFactory;
use App\Http\Controllers\Api\AbstractApiController;
use App\Services\Contracts\AvailabilityServiceInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * API pública de pases disponibles.
 *
 * Respuesta cacheable con ETag y Cache-Control público.
 */
final class PassController extends AbstractApiController
{
    public function __construct(
        ResponseFactory $response,
        private readonly AvailabilityServiceInterface $availability,
    ) {
        parent::__construct($response);
    }

    /**
     * GET /api/v1/passes
     *
     * Devuelve los pases disponibles para reserva.
     * Respuesta cacheable con ETag + Cache-Control público.
     */
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $passes = $this->availability->getAvailablePassesForReservation();
        $etag   = $this->makeEtag($passes);
        $cc     = 'public, max-age=300';

        if ($request->getHeaderLine('If-None-Match') === $etag) {
            return $this->notModified($etag, $cc);
        }

        return $this->success(
            ['items' => $passes, 'total' => \count($passes)],
            200,
            ['Cache-Control' => $cc, 'ETag' => $etag]
        );
    }
}
