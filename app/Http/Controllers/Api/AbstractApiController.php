<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Core\Http\ResponseFactory;
use App\Core\Pagination;
use App\Core\Result;
use App\Core\ServiceErrorCode;
use App\Http\Transformers\TransformerInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Base para todos los controladores de la capa API.
 *
 * Centraliza helpers de respuesta JSON con el envelope estándar:
 *   { ok: true,  data: mixed }
 *   { ok: false, error: string, code: string }
 */
abstract class AbstractApiController
{
    public function __construct(
        protected readonly ResponseFactory $response
    ) {
    }

    /**
     * Respuesta de éxito.
     *
     * Añade `Vary: Accept` por defecto para optimizar cachés intermedias
     * ante futuras variantes de contenido (JSON, JSON-LD, etc.).
     *
     * @param array<string, mixed>|list<mixed>|null $data
     * @param array<string, string>                 $headers
     */
    protected function success(mixed $data = null, int $status = 200, array $headers = []): ResponseInterface
    {
        if (!isset($headers['Vary'])) {
            $headers['Vary'] = 'Accept';
        }

        return $this->response->json(['ok' => true, 'data' => $data], $status, $headers);
    }

    /**
     * 304 Not Modified — sin cuerpo, con ETag y Cache-Control.
     */
    protected function notModified(string $etag, string $cacheControl): ResponseInterface
    {
        return $this->response->createResponse(304)
            ->withHeader('ETag', $etag)
            ->withHeader('Cache-Control', $cacheControl)
            ->withHeader('Vary', 'Accept');
    }

    /**
     * Genera un ETag xxh32 estable a partir de los datos serializados.
     */
    protected function makeEtag(mixed $data): string
    {
        return '"' . \hash('xxh32', (string) \json_encode($data)) . '"';
    }

    /**
     * Transforma un único recurso y lo devuelve como respuesta de éxito.
     *
     * @param array<string, mixed> $data
     * @param array<string, string> $headers
     */
    protected function transform(array $data, TransformerInterface $transformer, int $status = 200, array $headers = []): ResponseInterface
    {
        return $this->success($transformer->transform($data), $status, $headers);
    }

    /**
     * Transforma una colección y la devuelve como respuesta de éxito.
     *
     * @param array<int, array<string, mixed>> $items
     * @param array<string, mixed>             $meta  Metadatos opcionales (paginación, totales…)
     * @param array<string, string>            $headers
     */
    protected function collection(array $items, TransformerInterface $transformer, array $meta = [], array $headers = []): ResponseInterface
    {
        $payload = ['items' => $transformer->collection($items), 'total' => \count($items)];
        if ($meta !== []) {
            $payload = \array_merge($payload, ['meta' => $meta]);
        }

        return $this->success($payload, 200, $headers);
    }

    /** 404 Not Found */
    protected function notFound(string $detail, string|ServiceErrorCode $code = ServiceErrorCode::NOT_FOUND): ResponseInterface
    {
        return $this->response->problem(Result::fail($detail, $code), 404);
    }

    /** 403 Forbidden */
    protected function forbidden(string $detail, string|ServiceErrorCode $code = ServiceErrorCode::FORBIDDEN): ResponseInterface
    {
        return $this->response->problem(Result::fail($detail, $code), 403);
    }

    /** 401 Unauthorized */
    protected function unauthorized(string $detail, string|ServiceErrorCode $code = ServiceErrorCode::UNAUTHORIZED): ResponseInterface
    {
        return $this->response->problem(Result::fail($detail, $code), 401);
    }

    /** 422 Unprocessable Entity */
    protected function unprocessable(string $detail, string|ServiceErrorCode $code = ServiceErrorCode::VALIDATION_ERROR): ResponseInterface
    {
        return $this->response->problem(Result::fail($detail, $code), 422);
    }

    /** 409 Conflict */
    protected function conflict(string $detail, string|ServiceErrorCode $code = ServiceErrorCode::CONFLICT): ResponseInterface
    {
        return $this->response->problem(Result::fail($detail, $code), 409);
    }

    /** 500 Internal Server Error */
    protected function serverError(string $detail = 'Error interno del servidor', string|ServiceErrorCode $code = ServiceErrorCode::SERVER_ERROR): ResponseInterface
    {
        return $this->response->problem(Result::fail($detail, $code), 500);
    }

    /** 201 Created */
    protected function created(mixed $data = null): ResponseInterface
    {
        return $this->response->json(['ok' => true, 'data' => $data], 201);
    }

    /** 204 No Content */
    protected function noContent(): ResponseInterface
    {
        return $this->response->html('', 204);
    }

    /** 400 Bad Request (RFC 9457) */
    protected function badRequest(string $detail, string|ServiceErrorCode $code = ServiceErrorCode::BUSINESS_RULE): ResponseInterface
    {
        return $this->response->problem(Result::fail($detail, $code), 400);
    }

    /**
     * Respuesta paginada con metadatos de cursor (+1 sentinel row).
     *
     * @param list<mixed>          $items          Resultados (slice sin el sentinel).
     * @param Pagination           $pagination     Value object de paginación.
     * @param array<string, mixed> $extraMeta      Metadatos adicionales opcionales.
     */
    protected function paginated(array $items, Pagination $pagination, array $extraMeta = []): ResponseInterface
    {
        $hasNext = $pagination->hasNextPage(\count($items));
        // Eliminar el sentinel row si existe
        if ($hasNext) {
            \array_pop($items);
        }

        $meta = \array_merge($pagination->toMeta($hasNext), $extraMeta);

        return $this->success(['items' => $items, 'meta' => $meta]);
    }
}
