<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Core\Http\ResponseFactory;
use App\Http\Controllers\Api\AbstractApiController;
use App\Services\Contracts\WaitlistServiceInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * WaitlistController - API v1 para gestión de lista de espera
 *
 * Endpoints:
 * - POST /api/v1/waitlists                       - Unirse a lista de espera
 * - GET  /api/v1/waitlists/{token}               - Consultar posición
 * - POST /api/v1/waitlists/{token}/confirmations - Confirmar promoción
 */
final class WaitlistController extends AbstractApiController
{
    public function __construct(
        ResponseFactory $response,
        private readonly WaitlistServiceInterface $service,
    ) {
        parent::__construct($response);
    }

    /**
     * POST /api/v1/waitlists
     *
     * Añadir usuario autenticado a lista de espera cuando no hay disponibilidad.
     * El user_id se lee del atributo de la request (puesto por ApiAuthMiddleware).
     */
    public function join(ServerRequestInterface $request): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        if ($userId === null) {
            return $this->unauthorized('Debes iniciar sesión');
        }

        $body = (array) ($request->getParsedBody() ?? []);

        if (!isset($body['time_slot_id']) || !\is_numeric($body['time_slot_id'])) {
            return $this->unprocessable('time_slot_id requerido y debe ser numérico');
        }

        $data = [
            'guest_count' => (int) ($body['guest_count'] ?? 1),
            'contact_email' => (string) ($body['contact_email'] ?? ''),
            'contact_phone' => (string) ($body['contact_phone'] ?? ''),
            'special_requests' => (string) ($body['special_requests'] ?? ''),
        ];

        $result = $this->service->joinWaitlist((int) $body['time_slot_id'], (int) $userId, $data);

        if (!$result->ok) {
            return $this->unprocessable($result->error ?? 'Error al unirse a la lista de espera');
        }

        $waitlistData = (array) ($result->data ?? []);
        $position = (int) ($waitlistData['position'] ?? 0);

        return $this->created([
            'id' => (int) ($waitlistData['id'] ?? 0),
            'token' => (string) ($waitlistData['token'] ?? ''),
            'position' => $position,
            'message' => "Te has unido a la lista de espera en posición {$position}",
        ]);
    }

    /**
     * GET /api/v1/waitlists/{token}
     *
     * Consultar posición actual en la lista de espera.
     */
    public function position(ServerRequestInterface $request): ResponseInterface
    {
        $token = (string) ($request->getAttribute('token') ?? '');

        if ($token === '') {
            return $this->unprocessable('Token requerido');
        }

        $result = $this->service->getWaitlistStatus($token);

        if (!$result->ok) {
            return $this->notFound($result->error ?? 'Token no encontrado');
        }

        $data = (array) ($result->data ?? []);
        if (isset($data['position'])) {
            $data['position'] = (int) $data['position'];
        }

        return $this->success($data);
    }

    /**
     * POST /api/v1/waitlists/{token}/confirmations
     *
     * Confirmar promoción desde lista de espera (crear reserva).
     */
    public function confirm(ServerRequestInterface $request): ResponseInterface
    {
        $token = (string) ($request->getAttribute('token') ?? '');

        if ($token === '') {
            return $this->unprocessable('Token requerido');
        }

        $body = (array) ($request->getParsedBody() ?? []);
        $result = $this->service->confirmPromotion($token, $body);

        if (!$result->ok) {
            return $this->unprocessable($result->error ?? 'Error al confirmar la promoción');
        }

        $data = (array) ($result->data ?? []);

        return $this->success([
            'reservation_id' => (int) ($data['reservation_id'] ?? 0),
            'message' => 'Reserva confirmada exitosamente',
        ]);
    }
}
