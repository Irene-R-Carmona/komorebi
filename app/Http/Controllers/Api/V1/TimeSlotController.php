<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Core\Container;
use App\Core\Http\ResponseFactory;
use App\Repositories\Contracts\TimeSlotRepositoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Controlador API para Time Slots (FASE 2)
 */
final class TimeSlotController
{
    private TimeSlotRepositoryInterface $timeSlotRepo;

    private ResponseFactory $response;

    public function __construct(?TimeSlotRepositoryInterface $timeSlotRepo = null, ?ResponseFactory $response = null)
    {
        $this->timeSlotRepo = $timeSlotRepo ?? Container::make(TimeSlotRepositoryInterface::class);
        $this->response     = $response ?? new ResponseFactory();
    }

    /**
     * GET /api/v1/time-slots/available
     *
     * Listar slots disponibles para un café
     */
    public function available(ServerRequestInterface $request): ResponseInterface
    {
        $queryParams = $request->getQueryParams();

        $cafeId    = (int) ($queryParams['cafe_id'] ?? 1);
        $startDate = $queryParams['start_date'] ?? \date('Y-m-d');
        $endDate   = $queryParams['end_date'] ?? \date('Y-m-d', \strtotime('+7 days'));
        $minSpots  = (int) ($queryParams['min_spots'] ?? 1);

        $slots = $this->timeSlotRepo->findAvailableRange($cafeId, $startDate, $endDate, $minSpots);

        return $this->response->json([
            'ok' => true,
            'data' => [
                'cafe_id'    => $cafeId,
                'date_range' => ['start' => $startDate, 'end' => $endDate],
                'total_slots' => \count($slots),
                'slots' => \array_map(function ($slot) {
                    $slot     = \is_array($slot) ? $slot : [];
                    $slotTime = (string) ($slot['slot_time'] ?? '');
                    $available = isset($slot['available_spots']) ? (int) $slot['available_spots'] : 0;
                    $capacity  = isset($slot['total_capacity']) ? (int) $slot['total_capacity'] : 0;
                    $occupancy = isset($slot['occupancy_percentage']) ? (float) $slot['occupancy_percentage'] : 0.0;

                    return [
                        'id'       => isset($slot['id']) ? (int) $slot['id'] : 0,
                        'date'     => (string) ($slot['slot_date'] ?? ''),
                        'time'     => \substr($slotTime, 0, 5),
                        'available' => $available,
                        'capacity' => $capacity,
                        'occupancy' => $occupancy . '%',
                        'status'   => (string) ($slot['availability_status'] ?? ''),
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

        $cafeId    = (int) ($queryParams['cafe_id'] ?? 1);
        $startDate = $queryParams['start_date'] ?? \date('Y-m-d');
        $endDate   = $queryParams['end_date'] ?? \date('Y-m-d', \strtotime('+7 days'));

        $data = $this->timeSlotRepo->getOccupancyStats($cafeId, $startDate, $endDate);

        return $this->response->json(['ok' => true, 'data' => $data], 200);
    }
}
