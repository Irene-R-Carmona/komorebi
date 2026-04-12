<?php

/**
 * ¿Qué pruebas aquí?
 * Verifica que Shared/ReservationController cumple el contrato PSR-7.
 *
 * ¿Qué me quieres demostrar?
 * Que create() lanza ValidationException cuando no hay sesión activa
 * (protección defensiva antes de tocar el servicio ni la BD).
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se elimina la verificación de autenticación al inicio de create(),
 * o si se cambia por una redirección en lugar de una excepción.
 */

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Shared;

use App\Core\Http\ResponseFactory;
use App\Exceptions\ValidationException;
use App\Http\Controllers\Shared\ReservationController;
use App\Repositories\Contracts\CafeRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\ReservationRepositoryInterface;
use App\Services\Contracts\EmailServiceInterface;
use App\Services\Contracts\InvoicePDFServiceInterface;
use App\Services\ReservationService;
use Tests\Support\ControllerTestCase;

final class ReservationControllerTest extends ControllerTestCase
{
    protected function setUp(): void
    {
        if (session_status() === \PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    private function makeController(): ReservationController
    {
        $reservations = new ReservationService(
            $this->createStub(ReservationRepositoryInterface::class),
            $this->createStub(CafeRepositoryInterface::class),
            $this->createStub(ProductRepositoryInterface::class),
            $this->createStub(InvoicePDFServiceInterface::class),
            $this->createStub(EmailServiceInterface::class),
        );
        return new ReservationController(
            reservationService: $reservations,
            response: new ResponseFactory(),
        );
    }

    public function test_create_throws_validation_exception_when_not_authenticated(): void
    {
        $this->expectException(ValidationException::class);

        $this->makeController()->create();
    }

    public function test_class_has_expected_methods(): void
    {
        $this->assertTrue(method_exists(ReservationController::class, 'index'));
        $this->assertTrue(method_exists(ReservationController::class, 'create'));
    }
}
