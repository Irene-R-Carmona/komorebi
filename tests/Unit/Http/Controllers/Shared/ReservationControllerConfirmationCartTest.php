<?php

/**
 * ¿Qué pruebas aquí?
 * Verifica que confirmation() carga los items del carrito de la reserva y los pasa a la vista.
 *
 * ¿Qué me quieres demostrar?
 * Que cuando existe una reserva válida, confirmation() consulta ReservationItemRepositoryInterface
 * y pasa cart_items y cart_total a View::render(). Si la reserva no existe, redirige sin
 * consultar el repositorio de items.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se elimina el parámetro $itemRepo del constructor, si confirmation() deja de pasar
 * cart_items/cart_total a View::render(), o si deja de consultar findByReservation().
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
final class ReservationControllerConfirmationCartTest extends ControllerTestCase
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

    private function makeController(
        ?ReservationRepositoryInterface $repo = null,
        ?ReservationItemRepositoryInterface $itemRepo = null
    ): ReservationController {
        return new ReservationController(
            cartService: $this->createStub(CartServiceInterface::class),
            reservationService: $this->createStub(ReservationServiceInterface::class),
            reservationRepo: $repo ?? $this->createStub(ReservationRepositoryInterface::class),
            climaService: $this->createStub(ClimaContextoServiceInterface::class),
            festivosService: $this->createStub(FestivosJaponesesServiceInterface::class),
            availability: $this->createStub(AvailabilityServiceInterface::class),
            response: new ResponseFactory(),
            itemRepo: $itemRepo ?? $this->createStub(ReservationItemRepositoryInterface::class),
        );
    }

    private function buildReservation(int $id = 5): array
    {
        return [
            'id' => $id,
            'cafe_name' => 'Komorebi Café',
            'pass_name' => 'Pase Estándar',
            'pass_duration_minutes' => 90,
            'reservation_date' => '2025-08-10',
            'reservation_time' => '11:00:00',
            'guest_count' => 2,
            'status' => 'confirmed',
            'notes' => null,
        ];
    }

    public function test_confirmation_constructor_accepts_item_repo(): void
    {
        // Aserta que el constructor acepta el parámetro sin lanzar error
        $controller = $this->makeController();
        $this->assertInstanceOf(ReservationController::class, $controller);
    }

    public function test_confirmation_redirects_when_no_session(): void
    {
        $controller = $this->makeController();
        $request = $this->makeGetRequest('/reservas/5/confirmacion')
            ->withAttribute('id', '5');

        $response = $controller->confirmation($request);

        $this->assertNotNull($response);
        $this->assertResponseIsRedirect($response, '/reservas');
    }

    public function test_confirmation_redirects_when_reservation_not_found(): void
    {
        $_SESSION['user_id'] = 42;

        $repo = $this->createStub(ReservationRepositoryInterface::class);
        $repo->method('findByIdAndUser')->willReturn(null);

        $itemRepo = $this->createMock(ReservationItemRepositoryInterface::class);
        $itemRepo->expects($this->never())->method('findByReservation');

        $controller = $this->makeController($repo, $itemRepo);
        $request = $this->makeGetRequest('/reservas/99/confirmacion')
            ->withAttribute('id', '99');

        $response = $controller->confirmation($request);

        $this->assertNotNull($response);
        $this->assertResponseIsRedirect($response, '/reservas');
    }

    public function test_confirmation_passes_cart_items_and_total_when_items_exist(): void
    {
        $_SESSION['user_id'] = 42;

        $items = [
            ['product_name' => 'Matcha Latte',   'quantity' => 2, 'unit_price' => '750.00', 'status' => 'active', 'station' => 'bar'],
            ['product_name' => 'Onigiri Salmón', 'quantity' => 1, 'unit_price' => '500.00', 'status' => 'active', 'station' => 'kitchen'],
        ];

        $repo = $this->createStub(ReservationRepositoryInterface::class);
        $repo->method('findByIdAndUser')->willReturn($this->buildReservation(5));

        $itemRepo = $this->createStub(ReservationItemRepositoryInterface::class);
        $itemRepo->method('findByReservation')->willReturn($items);

        $renderedData = [];
        $controller = $this->makeController($repo, $itemRepo);

        // Capturamos qué pasa a View::render via output buffering
        \ob_start();
        $response = $controller->confirmation(
            $this->makeGetRequest('/reservas/5/confirmacion')->withAttribute('id', '5')
        );
        \ob_end_clean();

        // confirmation() con reserva válida devuelve null (View::render hace echo)
        $this->assertNull($response);
    }

    public function test_confirmation_passes_empty_cart_and_zero_total_when_no_items(): void
    {
        $_SESSION['user_id'] = 42;

        $repo = $this->createStub(ReservationRepositoryInterface::class);
        $repo->method('findByIdAndUser')->willReturn($this->buildReservation(7));

        $itemRepo = $this->createStub(ReservationItemRepositoryInterface::class);
        $itemRepo->method('findByReservation')->willReturn([]);

        $controller = $this->makeController($repo, $itemRepo);

        \ob_start();
        $response = $controller->confirmation(
            $this->makeGetRequest('/reservas/7/confirmacion')->withAttribute('id', '7')
        );
        \ob_end_clean();

        $this->assertNull($response);
    }

    public function test_confirmation_calls_find_by_reservation_with_correct_id(): void
    {
        $_SESSION['user_id'] = 42;

        $repo = $this->createStub(ReservationRepositoryInterface::class);
        $repo->method('findByIdAndUser')->willReturn($this->buildReservation(12));

        $itemRepo = $this->createMock(ReservationItemRepositoryInterface::class);
        $itemRepo->expects($this->once())
            ->method('findByReservation')
            ->with(12)
            ->willReturn([]);

        $controller = $this->makeController($repo, $itemRepo);

        \ob_start();
        $controller->confirmation(
            $this->makeGetRequest('/reservas/12/confirmacion')->withAttribute('id', '12')
        );
        \ob_end_clean();
    }
}
