<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Cache;
use App\Core\Http\ResponseFactory;
use Nyholm\Psr7\Factory\Psr17Factory;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Middleware de idempotencia para POST /api/v1/reservations.
 *
 * Flujo:
 *  - Header ausente      → pasa transparentemente.
 *  - UUID v4 inválido    → 422 Unprocessable Entity.
 *  - Cache hit           → devuelve respuesta almacenada (sin re-crear).
 *  - Cache miss          → ejecuta handler; almacena respuesta si status < 400.
 *
 * Clave Redis: idempotency:reservation:{uuid}  TTL 24 h.
 * Si Redis no está disponible, pasa transparentemente.
 */
final class IdempotencyMiddleware implements MiddlewareInterface
{
    private const int    TTL = 86_400;             // 24 h en segundos
    private const string KEY_PREFIX = 'idempotency:reservation:';

    /** Patrón UUID v4 canónico (case-insensitive). */
    private const string UUID_V4 = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

    public function __construct(
        private readonly ResponseFactory $response,
    ) {
    }

    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $key = \trim($request->getHeaderLine('Idempotency-Key'));

        // Sin header: pasar transparentemente
        if ($key === '') {
            return $handler->handle($request);
        }

        // Formato inválido: rechazar con 422
        if (!\preg_match(self::UUID_V4, $key)) {
            return $this->response->json(
                ['ok' => false, 'error' => 'Idempotency-Key debe ser un UUID v4 válido.', 'code' => 'invalid_idempotency_key'],
                422,
            );
        }

        $redis = Cache::getRedis();

        // Redis no disponible: pasar transparentemente
        if ($redis === null) {
            return $handler->handle($request);
        }

        $redisKey = self::KEY_PREFIX . $key;
        $cached = $redis->get($redisKey);

        // Cache hit: reproducir respuesta almacenada
        if ($cached !== false && \is_string($cached)) {
            $stored = \json_decode($cached, true);
            if (\is_array($stored)) {
                return $this->replayResponse($stored, $key);
            }
        }

        // Cache miss: ejecutar handler
        $response = $handler->handle($request);

        // Almacenar sólo respuestas exitosas (< 400)
        if ($response->getStatusCode() < 400) {
            $stored = \json_encode([
                'status' => $response->getStatusCode(),
                'body' => (string) $response->getBody(),
                'location' => $response->getHeaderLine('Location'),
            ]);

            if ($stored !== false) {
                $redis->setex($redisKey, self::TTL, $stored);
            }
        }

        return $response->withHeader('Idempotency-Key', $key);
    }

    /**
     * Reconstruye una PSR-7 ResponseInterface a partir de los datos almacenados.
     *
     * @param array{status: int, body: string, location: string} $stored
     */
    private function replayResponse(array $stored, string $key): ResponseInterface
    {
        $factory = new Psr17Factory();
        $response = $factory->createResponse($stored['status'])
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withHeader('Idempotency-Key', $key)
            ->withHeader('X-Idempotent-Replay', 'true');

        if (isset($stored['location']) && $stored['location'] !== '') {
            $response = $response->withHeader('Location', $stored['location']);
        }

        $response->getBody()->write($stored['body']);

        return $response;
    }
}
