<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Core\Http\ResponseFactory;
use App\Core\Result;
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
    ) {}

    /**
     * Respuesta de éxito.
     *
     * @param array<string, mixed>|list<mixed>|null $data
     * @param array<string, string>                 $headers
     */
    protected function success(mixed $data = null, int $status = 200, array $headers = []): ResponseInterface
    {
        return $this->response->json(['ok' => true, 'data' => $data], $status, $headers);
    }

    /**
     * Respuesta de error.
     *
     * @param array<string, mixed>|null $extra Campos adicionales en el payload de error
     * @param array<string, string>     $headers
     */
    protected function error(
        string $message,
        string $code = 'error',
        int $status = 400,
        ?array $extra = null,
        array $headers = [],
    ): ResponseInterface {
        $payload = ['ok' => false, 'error' => $message, 'code' => $code];
        if ($extra !== null) {
            $payload = \array_merge($payload, $extra);
        }
        return $this->response->json($payload, $status, $headers);
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
    protected function notFound(string $detail, string $code = 'not_found'): ResponseInterface
    {
        return $this->response->problem(Result::fail($detail, $code), 404);
    }

    /** 403 Forbidden */
    protected function forbidden(string $detail, string $code = 'forbidden'): ResponseInterface
    {
        return $this->response->problem(Result::fail($detail, $code), 403);
    }

    /** 401 Unauthorized */
    protected function unauthorized(string $detail, string $code = 'unauthorized'): ResponseInterface
    {
        return $this->response->problem(Result::fail($detail, $code), 401);
    }

    /** 422 Unprocessable Entity */
    protected function unprocessable(string $detail, string $code = 'validation_error'): ResponseInterface
    {
        return $this->response->problem(Result::fail($detail, $code), 422);
    }

    /** 409 Conflict */
    protected function conflict(string $detail, string $code = 'conflict'): ResponseInterface
    {
        return $this->response->problem(Result::fail($detail, $code), 409);
    }

    /** 500 Internal Server Error */
    protected function serverError(string $detail = 'Error interno del servidor', string $code = 'server_error'): ResponseInterface
    {
        return $this->response->problem(Result::fail($detail, $code), 500);
    }
}
