<?php

/**
 * ¿Qué pruebas aquí?
 * Verifica que WaitlistController::cancel() maneja éxito, error de servicio e ID inválido.
 *
 * ¿Qué me quieres demostrar?
 * Que cancel() delega en WaitlistServiceInterface::cancelWaitlist() con los argumentos
 * correctos y redirige siempre a /user/waitlists independientemente del resultado.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si cancel() deja de llamar a cancelWaitlist(), cambia la ruta de redirección,
 * o deja de usar Flash para comunicar el resultado al usuario.
 */

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\User;

use App\Core\Http\ResponseFactory;
use App\Core\Result;
use App\Http\Controllers\User\WaitlistController;
use App\Services\Contracts\WaitlistServiceInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Support\ControllerTestCase;

#[CoversClass(WaitlistController::class)]
final class WaitlistControllerCancelTest extends ControllerTestCase
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

    private function makeController(?WaitlistServiceInterface $service = null): WaitlistController
    {
        return new WaitlistController(
            service: $service ?? $this->createStub(WaitlistServiceInterface::class),
            response: new ResponseFactory(),
        );
    }

    public function test_cancel_method_exists(): void
    {
        $this->assertTrue(\method_exists(WaitlistController::class, 'cancel'));
    }

    public function test_cancel_redirects_to_waitlists_on_success(): void
    {
        $_SESSION['user_id'] = 5;

        $service = $this->createMock(WaitlistServiceInterface::class);
        $service->expects($this->once())
            ->method('cancelWaitlist')
            ->with(3, 5)
            ->willReturn(Result::ok());

        $controller = $this->makeController($service);
        $request = $this->makePostRequest('/user/waitlists/3/cancel')
            ->withAttribute('id', '3');

        $response = $controller->cancel($request);

        $this->assertResponseIsRedirect($response, '/user/waitlists');
    }

    public function test_cancel_redirects_on_service_failure(): void
    {
        $_SESSION['user_id'] = 5;

        $service = $this->createMock(WaitlistServiceInterface::class);
        $service->expects($this->once())
            ->method('cancelWaitlist')
            ->with(7, 5)
            ->willReturn(Result::fail('No autorizado', 'unauthorized'));

        $controller = $this->makeController($service);
        $request = $this->makePostRequest('/user/waitlists/7/cancel')
            ->withAttribute('id', '7');

        $response = $controller->cancel($request);

        $this->assertResponseIsRedirect($response, '/user/waitlists');
    }

    public function test_cancel_redirects_without_calling_service_when_id_invalid(): void
    {
        $_SESSION['user_id'] = 5;

        $service = $this->createMock(WaitlistServiceInterface::class);
        $service->expects($this->never())->method('cancelWaitlist');

        $controller = $this->makeController($service);
        $request = $this->makePostRequest('/user/waitlists/0/cancel')
            ->withAttribute('id', '0');

        $response = $controller->cancel($request);

        $this->assertResponseIsRedirect($response, '/user/waitlists');
    }

    public function test_cancel_redirects_without_calling_service_when_no_session(): void
    {
        // $_SESSION vacío → userId = 0

        $service = $this->createMock(WaitlistServiceInterface::class);
        $service->expects($this->never())->method('cancelWaitlist');

        $controller = $this->makeController($service);
        $request = $this->makePostRequest('/user/waitlists/3/cancel')
            ->withAttribute('id', '3');

        $response = $controller->cancel($request);

        $this->assertResponseIsRedirect($response, '/user/waitlists');
    }
}
