<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Core\Http\ResponseFactory;
use App\Exceptions\ValidationException;
use App\Http\Controllers\Api\AbstractApiController;
use App\Services\Contracts\ApiTokenServiceInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Gestión de tokens Bearer opacos para la API.
 *
 * Todos los endpoints requieren sesión web activa (ApiAuthMiddleware).
 * Los tokens generados pueden usarse como credencial Bearer en peticiones API.
 */
final class TokenController extends AbstractApiController
{
    public function __construct(
        ResponseFactory $response,
        private readonly ApiTokenServiceInterface $tokenService,
    ) {
        parent::__construct($response);
    }

    /**
     * GET /api/v1/tokens
     * Lista los tokens activos del usuario autenticado (sin exponer el hash).
     */
    public function list(ServerRequestInterface $request): ResponseInterface
    {
        $userId = (int) $request->getAttribute('user_id');
        $tokens = $this->tokenService->listForUser($userId);

        return $this->success(['tokens' => $tokens]);
    }

    /**
     * POST /api/v1/tokens
     * Genera un nuevo token. El plain text se devuelve UNA sola vez.
     */
    public function create(ServerRequestInterface $request): ResponseInterface
    {
        $body = (array) ($request->getParsedBody() ?? []);
        $name = \trim((string) ($body['name'] ?? ''));

        if ($name === '') {
            throw ValidationException::withMessage('El campo "name" es obligatorio.', 422);
        }

        if (\mb_strlen($name) > 100) {
            throw ValidationException::withMessage('El campo "name" no puede superar 100 caracteres.', 422);
        }

        $userId = (int) $request->getAttribute('user_id');
        $plain = $this->tokenService->generate($userId, $name);

        return $this->success(['token' => $plain, 'name' => $name], 201);
    }

    /**
     * DELETE /api/v1/tokens/{id}
     * Revoca un token propio. Ownership verificado en el servicio.
     */
    public function revoke(ServerRequestInterface $request): ResponseInterface
    {
        $tokenId = (int) $request->getAttribute('id');
        $userId = (int) $request->getAttribute('user_id');

        $result = $this->tokenService->revoke($tokenId, $userId);

        if (!$result->ok) {
            return $this->notFound($result->error ?? 'Token no encontrado.');
        }

        return $this->success(['revoked' => true]);
    }
}
