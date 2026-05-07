<?php

/**
 * ¿Qué pruebas aquí?
 * Verifica que ReservationController expone cancelConfirm() con el contrato correcto.
 *
 * ¿Qué me quieres demostrar?
 * Que cancelConfirm() existe, redirige cuando no hay sesión o la reserva no pertenece
 * al usuario, y devuelve null cuando muestra la vista de confirmación.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se elimina cancelConfirm(), si cambia su firma, o si deja de proteger por sesión/ownership.
 */

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Shared;

use App\Core\Http\ResponseFactory;
use App\Http\Controllers\Shared\ReservationController;
use App\Repositories\Contracts\ReservationItemRepositoryInterface;
use App\Repositories\Contracts\ReservationRepositoryInterface;
use App\Services\Contracts\AvailabilityServiceInterface;
use App\Services\Contracts\CartServiceInterface;
use App\Services\Contracts\ClimaContextoServiceInterface;
use App\Services\Contracts\FestivosJaponesesServiceInterface;
use App\Services\Contracts\ReservationServiceInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Support\ControllerTestCase;

#[CoversClass(ReservationController::class)]
final class ReservationControllerCancelConfirmTest extends ControllerTestCase
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

    private function makeController(?ReservationRepositoryInterface $repo = null): ReservationController
    {
        return new ReservationController(
            cartService: $this->createStub(CartServiceInterface::class),
            reservationService: $this->createStub(ReservationServiceInterface::class),
            reservationRepo: $repo ?? $this->createStub(ReservationRepositoryInterface::class),
            climaService: $this->createStub(ClimaContextoServiceInterface::class),
            festivosService: $this->createStub(FestivosJaponesesServiceInterface::class),
            availability: $this->createStub(AvailabilityServiceInterface::class),
            response: new ResponseFactory(),
            itemRepo: $this->createStub(ReservationItemRepositoryInterface::class),
        );
    }

    public function test_cancel_confirm_method_exists(): void
    {
        $this->assertTrue(\method_exists(ReservationController::class, 'cancelConfirm'));
    }

    public function test_cancel_confirm_redirects_when_no_session(): void
    {
        // $_SESSION vacío → Session::userId() devuelve null
        $controller = $this->makeController();
        $request = $this->makeGetRequest('/reservas/mis-reservas/1/cancelar', [])
            ->withAttribute('id', '1');

        $response = $controller->cancelConfirm($request);

        $this->assertNotNull($response);
        $this->assertResponseIsRedirect($response, '/reservas/mis-reservas');
    }

    public function test_cancel_confirm_redirects_when_reservation_not_found(): void
    {
        $_SESSION['user_id'] = 42;

        $repo = $this->createStub(ReservationRepositoryInterface::class);
        $repo->method('findByIdAndUser')->willReturn(null);

        $controller = $this->makeController($repo);
        $request = $this->makeGetRequest('/reservas/mis-reservas/99/cancelar', [])
            ->withAttribute('id', '99');

        $response = $controller->cancelConfirm($request);

        $this->assertNotNull($response);
        $this->assertResponseIsRedirect($response, '/reservas/mis-reservas');
    }

    public function test_cancel_confirm_redirects_when_reservation_already_cancelled(): void
    {
        $_SESSION['user_id'] = 42;

        $repo = $this->createStub(ReservationRepositoryInterface::class);
        $repo->method('findByIdAndUser')->willReturn([
            'id' => 5,
            'status' => 'cancelled',
        ]);

        $controller = $this->makeController($repo);
        $request = $this->makeGetRequest('/reservas/mis-reservas/5/cancelar', [])
            ->withAttribute('id', '5');

        $response = $controller->cancelConfirm($request);

        $this->assertNotNull($response);
        $this->assertResponseIsRedirect($response, '/reservas/mis-reservas');
    }
}
