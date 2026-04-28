<?php

/**
 * ¿Qué pruebas aquí?
 * Verifica el contrato PSR-7 de Api/WaitlistController.
 *
 * ¿Qué me quieres demostrar?
 * Que join() devuelve 401 cuando user_id no está en atributos de la request,
 * 422 sin time_slot_id, y 201 cuando los datos son válidos.
 * Que position() usa el atributo 'token' del router y devuelve 404/200 según el servicio.
 * Que confirm() usa el atributo 'token' del router y delega al servicio.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se restaura Container::make() o php://input directamente, si se lee user_id
 * del body en vez de los atributos de la request (IDOR), o si se eliminan las
 * validaciones de token/time_slot_id.
 */

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Api;

use App\Core\Http\ResponseFactory;
use App\Core\Result;
use App\Http\Controllers\Api\V1\WaitlistController;
use App\Services\Contracts\WaitlistServiceInterface;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Support\ControllerTestCase;

#[CoversClass(WaitlistController::class)]
final class WaitlistControllerTest extends ControllerTestCase
{
    private function makeController(): WaitlistController
    {
        return new WaitlistController(
            new ResponseFactory(),
            $this->createStub(WaitlistServiceInterface::class),
        );
    }

    public function test_class_has_expected_methods(): void
    {
        $this->assertTrue(\method_exists(WaitlistController::class, 'join'));
        $this->assertTrue(\method_exists(WaitlistController::class, 'position'));
        $this->assertTrue(\method_exists(WaitlistController::class, 'confirm'));
    }

    // ── join() ─────────────────────────────────────────────────────────────

    public function test_join_returns_401_when_not_authenticated(): void
    {
        $result = $this->makeController()->join(
            new ServerRequest('POST', '/api/v1/waitlists')
        );

        $this->assertSame(401, $result->getStatusCode());
    }

    public function test_join_returns_422_when_time_slot_id_missing(): void
    {
        $request = (new ServerRequest('POST', '/api/v1/waitlists'))
            ->withAttribute('user_id', 5)
            ->withParsedBody([]);

        $result = $this->makeController()->join($request);

        $this->assertSame(422, $result->getStatusCode());
    }

    public function test_join_returns_201_when_valid(): void
    {
        $service = $this->createStub(WaitlistServiceInterface::class);
        $service->method('joinWaitlist')->willReturn(
            Result::ok(['id' => 1, 'token' => 'tok123', 'position' => 2])
        );
        $controller = new WaitlistController(new ResponseFactory(), $service);

        $request = (new ServerRequest('POST', '/api/v1/waitlists'))
            ->withAttribute('user_id', 5)
            ->withParsedBody(['time_slot_id' => 10]);

        $result = $controller->join($request);

        $this->assertSame(201, $result->getStatusCode());
    }

    public function test_join_returns_422_when_service_fails(): void
    {
        $service = $this->createStub(WaitlistServiceInterface::class);
        $service->method('joinWaitlist')->willReturn(Result::fail('Ya en lista de espera'));
        $controller = new WaitlistController(new ResponseFactory(), $service);

        $request = (new ServerRequest('POST', '/api/v1/waitlists'))
            ->withAttribute('user_id', 5)
            ->withParsedBody(['time_slot_id' => 10]);

        $result = $controller->join($request);

        $this->assertSame(422, $result->getStatusCode());
    }

    // ── position() ─────────────────────────────────────────────────────────

    public function test_position_returns_422_when_token_empty(): void
    {
        $result = $this->makeController()->position(
            new ServerRequest('GET', '/api/v1/waitlists/')
        );

        $this->assertSame(422, $result->getStatusCode());
    }

    public function test_position_returns_404_when_service_fails(): void
    {
        $service = $this->createStub(WaitlistServiceInterface::class);
        $service->method('getWaitlistStatus')->willReturn(Result::fail('Token no encontrado'));
        $controller = new WaitlistController(new ResponseFactory(), $service);

        $request = (new ServerRequest('GET', '/api/v1/waitlists/tok123'))
            ->withAttribute('token', 'tok123');

        $result = $controller->position($request);

        $this->assertSame(404, $result->getStatusCode());
    }

    public function test_position_returns_200_when_token_valid(): void
    {
        $service = $this->createStub(WaitlistServiceInterface::class);
        $service->method('getWaitlistStatus')->willReturn(
            Result::ok(['position' => 2, 'status' => 'waiting'])
        );
        $controller = new WaitlistController(new ResponseFactory(), $service);

        $request = (new ServerRequest('GET', '/api/v1/waitlists/tok123'))
            ->withAttribute('token', 'tok123');

        $result = $controller->position($request);

        $this->assertSame(200, $result->getStatusCode());
    }

    // ── confirm() ──────────────────────────────────────────────────────────

    public function test_confirm_returns_422_when_token_empty(): void
    {
        $result = $this->makeController()->confirm(
            new ServerRequest('POST', '/api/v1/waitlists//confirmations')
        );

        $this->assertSame(422, $result->getStatusCode());
    }

    public function test_confirm_returns_422_when_service_fails(): void
    {
        $service = $this->createStub(WaitlistServiceInterface::class);
        $service->method('confirmPromotion')->willReturn(Result::fail('Token inválido o expirado'));
        $controller = new WaitlistController(new ResponseFactory(), $service);

        $request = (new ServerRequest('POST', '/api/v1/waitlists/tok123/confirmations'))
            ->withAttribute('token', 'tok123');

        $result = $controller->confirm($request);

        $this->assertSame(422, $result->getStatusCode());
    }

    public function test_confirm_returns_200_when_token_valid(): void
    {
        $service = $this->createStub(WaitlistServiceInterface::class);
        $service->method('confirmPromotion')->willReturn(
            Result::ok(['reservation_id' => 99])
        );
        $controller = new WaitlistController(new ResponseFactory(), $service);

        $request = (new ServerRequest('POST', '/api/v1/waitlists/tok123/confirmations'))
            ->withAttribute('token', 'tok123');

        $result = $controller->confirm($request);

        $this->assertSame(200, $result->getStatusCode());
    }
}
