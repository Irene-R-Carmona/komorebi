<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Core\Database;
use App\Core\Http\ResponseFactory;
use App\Core\Result;
use App\Models\TimeSlot;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Controlador API para Time Slots (prueba FASE 2)
 */
final class TimeSlotController
{
    private TimeSlot $timeSlotModel;

    private ResponseFactory $response;

    public function __construct()
    {
        // TODO(plan6-controller-di): Eliminar Database::getConnection() directo para poder testear.
        //                            Inyectar TimeSlotRepositoryInterface vía constructor requerido.
        //                            Ver docs/superpowers/plans/2026-04-10-plan6-controller-di.md
        $this->timeSlotModel = new TimeSlot(Database::getConnection());
        $this->response = new ResponseFactory();
    }

    /**
     * GET /api/v1/time-slots/available
     *
     * Listar slots disponibles para un café
     */
    public function available(ServerRequestInterface $request): ResponseInterface
    {
        $queryParams = $request->getQueryParams();

        // Parámetros por defecto
        $cafeId = (int) ($queryParams['cafe_id'] ?? 1);
        $startDate = $queryParams['start_date'] ?? \date('Y-m-d');
        $endDate = $queryParams['end_date'] ?? \date('Y-m-d', \strtotime('+7 days'));
        $minSpots = (int) ($queryParams['min_spots'] ?? 1);

        $result = $this->timeSlotModel->findAvailable($cafeId, $startDate, $endDate, $minSpots);

        if (!$result->isOk()) {
            return $this->response->problem(Result::fail($result->error ?? 'Error', 'server_error'), 500);
        }

        $slots = $result->data;
        if (!is_array($slots)) {
            $slots = [];
        }

        return $this->response->json([
            'ok' => true,
            'data' => [
                'cafe_id' => $cafeId,
                'date_range' => [
                    'start' => $startDate,
                    'end' => $endDate,
                ],
                'total_slots' => \count($slots),
                'slots' => \array_map(function ($slot) {
                    $slot = is_array($slot) ? $slot : [];

                    $slotTime = (string) ($slot['slot_time'] ?? '');
                    $available = isset($slot['available_spots']) ? (int) $slot['available_spots'] : 0;
                    $capacity = isset($slot['total_capacity']) ? (int) $slot['total_capacity'] : 0;
                    $occupancy = isset($slot['occupancy_percentage']) ? (float) $slot['occupancy_percentage'] : 0.0;

                    return [
                        'id' => isset($slot['id']) ? (int) $slot['id'] : 0,
                        'date' => (string) ($slot['slot_date'] ?? ''),
                        'time' => \substr($slotTime, 0, 5), // HH:MM
                        'available' => $available,
                        'capacity' => $capacity,
                        'occupancy' => $occupancy . '%',
                        'status' => (string) ($slot['availability_status'] ?? ''),
                        'duration' => (isset($slot['duration_minutes']) ? (int) $slot['duration_minutes'] : 0) . ' min',
                    ];
                }, $slots),
            ],
        ], 200);
    }

    /**
     * GET /api/v1/time-slots/stats
     *
     * Estadísticas de ocupación de un café
     */
    public function stats(ServerRequestInterface $request): ResponseInterface
    {
        $queryParams = $request->getQueryParams();

        $cafeId = (int) ($queryParams['cafe_id'] ?? 1);
        $startDate = $queryParams['start_date'] ?? \date('Y-m-d');
        $endDate = $queryParams['end_date'] ?? \date('Y-m-d', \strtotime('+7 days'));

        $result = $this->timeSlotModel->getOccupancyStats($cafeId, $startDate, $endDate);

        if (!$result->isOk()) {
            return $this->response->problem(Result::fail($result->error ?? 'Error', 'server_error'), 500);
        }

        return $this->response->json(['ok' => true, 'data' => $result->data], 200);
    }
}
