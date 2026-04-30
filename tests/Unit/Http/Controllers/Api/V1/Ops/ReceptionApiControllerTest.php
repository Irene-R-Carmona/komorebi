<?php

/**
 * ¿Qué pruebas aquí?
 * Verifica que Api\V1\Ops\ReceptionApiController delega a ReceptionServiceInterface
 * con la validación de inputs correcta.
 *
 * ¿Qué me quieres demostrar?
 * Que todayReservations() retorna 403 sin café asignado y 200 con reservas.
 * Que checkIn() valida id y tracker_id, retorna 400 con datos inválidos y 200 en éxito.
 * Que checkOut() valida id, retorna 400 inválido y 200 en éxito.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se elimina la guard de cafe_id, o cambia la validación de id/tracker_id,
 * o si el mapeo de errores del servicio cambia (422 vs otros códigos).
 */

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Api\V1\Ops;

use App\Core\Http\ResponseFactory;
use App\Core\Result;
use App\Http\Controllers\Api\V1\Ops\ReceptionApiController;
use App\Services\Contracts\ReceptionServiceInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Support\ControllerTestCase;

#[CoversClass(ReceptionApiController::class)]
final class ReceptionApiControllerTest extends ControllerTestCase
{
    protected function setUp(): void
    {
        if (\session_status() === \PHP_SESSION_NONE) {
            \session_start();
        }
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    private function makeController(?ReceptionServiceInterface $service = null): ReceptionApiController
    {
        $service ??= $this->createStub(ReceptionServiceInterface::class);
        $service->method('processCheckin')->willReturn(Result::ok());
        $service->method('processCheckout')->willReturn(Result::ok());

        return new ReceptionApiController(new ResponseFactory(), $service);
    }

    // — todayReservations —

    public function test_todayReservations_returns_403_without_cafe(): void
    {
        $request = $this->makeGetRequest('/api/v1/ops/reception/reservations');
        $response = $this->makeController()->todayReservations($request);

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_todayReservations_returns_200_with_reservations(): void
    {
        $this->asUser(userId: 1, role: 'reception', cafeId: 5);

        $service = $this->createStub(ReceptionServiceInterface::class);
        $service->method('getPendingArrivals')->willReturn([['id' => 1, 'guests' => 2]]);
        $service->method('processCheckin')->willReturn(Result::ok());
        $service->method('processCheckout')->willReturn(Result::ok());

        $request = $this->makeGetRequest('/api/v1/ops/reception/reservations');
        $response = new ReceptionApiController(new ResponseFactory(), $service)->todayReservations($request);

        $this->assertSame(200, $response->getStatusCode());
    }

    // — checkIn —

    public function test_checkIn_returns_400_with_invalid_id(): void
    {
        $request = $this->makePostRequest('/api/v1/ops/reception/reservations/0/checkin', ['tracker_id' => 3]);
        $response = $this->makeController()->checkIn($request, 0);

        $this->assertSame(400, $response->getStatusCode());
    }

    public function test_checkIn_returns_400_without_tracker_id(): void
    {
        $this->asUser(userId: 1, role: 'reception', cafeId: 5);

        $request = $this->makePostRequest('/api/v1/ops/reception/reservations/1/checkin', ['tracker_id' => 0]);
        $response = $this->makeController()->checkIn($request, 1);

        $this->assertSame(400, $response->getStatusCode());
    }

    public function test_checkIn_returns_422_on_service_failure(): void
    {
        $service = $this->createStub(ReceptionServiceInterface::class);
        $service->method('processCheckin')->willReturn(Result::fail('Tracker ocupado', 'tracker_busy'));
        $service->method('processCheckout')->willReturn(Result::ok());

        $request = $this->makePostRequest('/api/v1/ops/reception/reservations/1/checkin', ['tracker_id' => 3]);
        $response = new ReceptionApiController(new ResponseFactory(), $service)->checkIn($request, 1);

        $this->assertSame(422, $response->getStatusCode());
    }

    public function test_checkIn_returns_200_with_valid_input(): void
    {
        $request = $this->makePostRequest('/api/v1/ops/reception/reservations/1/checkin', ['tracker_id' => 3]);
        $response = $this->makeController()->checkIn($request, 1);

        $this->assertSame(200, $response->getStatusCode());
    }

    // — checkOut —

    public function test_checkOut_returns_400_with_invalid_id(): void
    {
        $request = $this->makePostRequest('/api/v1/ops/reception/reservations/0/checkout', []);
        $response = $this->makeController()->checkOut($request, 0);

        $this->assertSame(400, $response->getStatusCode());
    }

    public function test_checkOut_returns_422_on_service_failure(): void
    {
        $service = $this->createStub(ReceptionServiceInterface::class);
        $service->method('processCheckin')->willReturn(Result::ok());
        $service->method('processCheckout')->willReturn(Result::fail('No se encontró la reserva', 'not_found'));

        $request = $this->makePostRequest('/api/v1/ops/reception/reservations/7/checkout', []);
        $response = new ReceptionApiController(new ResponseFactory(), $service)->checkOut($request, 7);

        $this->assertSame(422, $response->getStatusCode());
    }

    public function test_checkOut_returns_200_with_valid_id(): void
    {
        $request = $this->makePostRequest('/api/v1/ops/reception/reservations/7/checkout', []);
        $response = $this->makeController()->checkOut($request, 7);

        $this->assertSame(200, $response->getStatusCode());
    }
}
