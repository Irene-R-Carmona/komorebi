<?php

/**
 * ¿Qué pruebas aquí?
 * Verifica que Api\ReservationController valida parámetros antes de llamar al servicio.
 *
 * ¿Qué me quieres demostrar?
 * Que getAvailableSlots() retorna 422 sin el parámetro date.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se elimina la validación del parámetro date en getAvailableSlots().
 */

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Api;

use App\Core\Http\ResponseFactory;
use App\Http\Controllers\Api\V1\ReservationController;
use App\Services\Contracts\ReservationServiceInterface;
use App\Services\Contracts\TimeSlotServiceInterface;
use Tests\Support\ControllerTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ReservationController::class)]
final class ReservationControllerTest extends ControllerTestCase
{
    private function makeController(): ReservationController
    {
        return new ReservationController(
            new ResponseFactory(),
            $this->createMock(ReservationServiceInterface::class),
            $this->createMock(TimeSlotServiceInterface::class),
        );
    }

    public function test_get_available_slots_returns_422_without_date(): void
    {
        $response = $this->makeController()->getAvailableSlots(
            $this->makeGetRequest('/api/reservations/available')
        );

        $this->assertSame(422, $response->getStatusCode());
        $body = \json_decode((string) $response->getBody(), true);
        $this->assertSame(422, $body['status']);
    }

    public function test_get_available_slots_calls_service_with_valid_date(): void
    {
        $timeSlotService = $this->createMock(TimeSlotServiceInterface::class);
        $timeSlotService->method('getAvailableSlots')->willReturn([]);

        $controller = new ReservationController(
            new ResponseFactory(),
            $this->createMock(ReservationServiceInterface::class),
            $timeSlotService,
        );

        $response = $controller->getAvailableSlots(
            $this->makeGetRequest('/api/reservations/available', ['date' => '2026-05-01'])
        );

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_class_has_expected_methods(): void
    {
        $this->assertTrue(\method_exists(ReservationController::class, 'getAvailableSlots'));
    }
}
