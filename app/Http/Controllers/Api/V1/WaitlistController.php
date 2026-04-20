<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Core\Container;
use App\Core\Http\ResponseFactory;
use App\Core\Result;
use App\Services\Contracts\WaitlistServiceInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * WaitlistController - API v1 para gestión de lista de espera
 *
 * Endpoints:
 * - POST /api/v1/waitlist/join - Unirse a lista de espera
 * - GET /api/v1/waitlist/position/{token} - Consultar posición
 * - POST /api/v1/waitlist/confirm/{token} - Confirmar promoción
 */
final class WaitlistController
{
    private WaitlistServiceInterface $service;

    private ResponseFactory $response;

    public function __construct()
    {
        $this->service = Container::make(WaitlistServiceInterface::class);
        $this->response = new ResponseFactory();
    }

    /**
     * POST /api/v1/waitlist/join
     *
     * Añadir usuario a lista de espera cuando no hay disponibilidad
     *
     * Body:
     * {
     *   "time_slot_id": 123,
     *   "user_id": 45,
     *   "guest_count": 2,
     *   "contact_email": "user@example.com",
     *   "contact_phone": "+34666123456",
     *   "special_requests": "Mesa junto a ventana"
     * }
     *
     * Response 201:
     * {
     *   "success": true,
     *   "data": {
     *     "id": 789,
     *     "token": "abc123def456...",
     *     "position": 3,
     *     "message": "Te has unido a la lista de espera en posición 3"
     *   }
     * }
     */
    public function join(): ResponseInterface
    {
        $raw = @\file_get_contents('php://input');
        $raw = $raw === false ? '' : $raw;
        $input = \json_decode($raw, true) ?? [];

        if (!isset($input['time_slot_id'], $input['user_id'])) {
            return $this->response->problem(
                Result::fail('Faltan campos requeridos: time_slot_id, user_id', 'bad_request'),
                400
            );
        }

        $timeSlotId = (int) $input['time_slot_id'];
        $userId = (int) $input['user_id'];

        $data = [
            'guest_count' => (int) ($input['guest_count'] ?? 1),
            'contact_email' => (string) ($input['contact_email'] ?? ''),
            'contact_phone' => (string) ($input['contact_phone'] ?? ''),
            'special_requests' => (string) ($input['special_requests'] ?? ''),
        ];

        $result = $this->service->joinWaitlist($timeSlotId, $userId, $data);

        if (!$result->ok) {
            return $this->response->problem($result, 400);
        }

        $waitlistData = (array) ($result->data ?? []);
        $position = isset($waitlistData['position']) ? (int) $waitlistData['position'] : 0;

        return $this->response->json([
            'ok' => true,
            'data' => [
                'id' => isset($waitlistData['id']) ? (int) $waitlistData['id'] : 0,
                'token' => isset($waitlistData['token']) ? (string) $waitlistData['token'] : '',
                'position' => $position,
                'message' => "Te has unido a la lista de espera en posición {$position}",
            ],
        ], 201);
    }

    /**
     * GET /api/v1/waitlist/position/{token}
     *
     * Consultar posición actual en la lista de espera
     *
     * Response 200:
     * {
     *   "success": true,
     *   "data": {
     *     "position": 2,
     *     "status": "waiting",
     *     "estimated_wait_minutes": 30,
     *     "time_slot": {
     *       "date": "2026-02-15",
     *       "time": "14:00:00",
     *       "cafe_name": "Neko no Niwa"
     *     }
     *   }
     * }
     */
    public function position(string $token): ResponseInterface
    {
        if (empty($token)) {
            return $this->response->problem(
                Result::fail('Token requerido', 'bad_request'),
                400
            );
        }

        $result = $this->service->getWaitlistStatus($token);

        if (!$result->ok) {
            return $this->response->problem($result, 404);
        }

        $data = (array) ($result->data ?? []);
        if (isset($data['position'])) {
            $data['position'] = (int) $data['position'];
        }

        return $this->response->json(['ok' => true, 'data' => $data], 200);
    }

    /**
     * POST /api/v1/waitlist/confirm/{token}
     *
     * Confirmar promoción desde lista de espera (crear reserva)
     *
     * Response 200:
     * {
     *   "success": true,
     *   "data": {
     *     "reservation_id": 456,
     *     "message": "Reserva confirmada exitosamente"
     *   }
     * }
     */
    public function confirm(string $token): ResponseInterface
    {
        if (empty($token)) {
            return $this->response->problem(
                Result::fail('Token requerido', 'bad_request'),
                400
            );
        }

        $raw = @\file_get_contents('php://input');
        $raw = $raw === false ? '' : $raw;
        $input = \json_decode($raw, true) ?? [];

        $result = $this->service->confirmPromotion($token, $input);

        if (!$result->ok) {
            return $this->response->problem($result, 400);
        }

        $data = (array) ($result->data ?? []);

        return $this->response->json([
            'ok' => true,
            'data' => [
                'reservation_id' => isset($data['reservation_id']) ? (int) $data['reservation_id'] : 0,
                'message' => 'Reserva confirmada exitosamente',
            ],
        ], 200);
    }
}
