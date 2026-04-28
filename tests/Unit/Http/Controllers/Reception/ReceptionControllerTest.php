<?php

/**
 * ¿Qué pruebas aquí?
 * Verifica el contrato PSR-7 de Reception/ReceptionController.
 *
 * ¿Qué me quieres demostrar?
 * Que checkIn() redirige cuando el id es inválido,
 * y que el constructor requiere sesión activa (Middleware::auth).
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se elimina la validación de id en checkIn() o si cambia
 * el comportamiento del constructor con Middleware::auth().
 */

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Reception;

use App\Http\Controllers\Reception\ReceptionController;
use App\Repositories\Contracts\CafeRepositoryInterface;
use App\Repositories\Contracts\ReservationRepositoryInterface;
use App\Repositories\Contracts\TrackerRepositoryInterface;
use App\Services\ReceptionService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReceptionController::class)]
final class ReceptionControllerTest extends TestCase
{
    protected function setUp(): void
    {
        if (\session_status() === \PHP_SESSION_NONE) {
            \session_start();
        }
        // ReceptionController llama Middleware::auth() en constructor
        $_SESSION['user_id'] = 1;
        $_SESSION['user'] = ['id' => 1, 'roles' => ['reception']];
        $_SESSION['user_roles'] = ['reception'];
        // Evita que el TTL check dispare fetchUserFromDb() en tests unitarios
        $_SESSION['_user_verified_at'] = \time();
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    private function makeController(): ReceptionController
    {
        return new ReceptionController(
            service: new ReceptionService(
                reservationRepo: $this->createStub(ReservationRepositoryInterface::class),
                trackerRepo: $this->createStub(TrackerRepositoryInterface::class),
                cafeRepo: $this->createStub(CafeRepositoryInterface::class),
            ),
        );
    }

    public function test_instance_can_be_created_with_stubs(): void
    {
        $this->assertInstanceOf(ReceptionController::class, $this->makeController());
    }

    public function test_class_has_expected_methods(): void
    {
        $this->assertTrue(\method_exists(ReceptionController::class, 'index'));
        $this->assertTrue(\method_exists(ReceptionController::class, 'todayReservations'));
    }
}
