<?php

/**
 * ¿Qué pruebas aquí?
 * Verifica que Shared/ReservationController expone los métodos de vista esperados.
 *
 * ¿Qué me quieres demostrar?
 * Que el controlador web mantiene index(), confirmation() y userReservations()
 * tras la migración de create()/cancel() a Api\V1\ReservationController.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se eliminan los métodos de vista web del controlador compartido.
 */

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Shared;

use App\Http\Controllers\Shared\ReservationController;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Support\ControllerTestCase;

#[CoversClass(ReservationController::class)]
final class ReservationControllerTest extends ControllerTestCase
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

    public function test_class_has_expected_methods(): void
    {
        $this->assertTrue(\method_exists(ReservationController::class, 'index'));
        $this->assertTrue(\method_exists(ReservationController::class, 'confirmation'));
        $this->assertTrue(\method_exists(ReservationController::class, 'userReservations'));
        $this->assertFalse(\method_exists(ReservationController::class, 'create'), 'create() migrado a Api\V1\ReservationController');
        $this->assertFalse(\method_exists(ReservationController::class, 'cancel'), 'cancel() migrado a Api\V1\ReservationController');
    }
}
