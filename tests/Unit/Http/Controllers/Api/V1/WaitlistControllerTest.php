<?php

/**
 * ¿Qué pruebas aquí?
 * Contrato de Api/V1/WaitlistController: instanciación y existencia de métodos.
 *
 * ¿Qué me quieres demostrar?
 * Que el controlador puede instanciarse con dependencias inyectadas
 * y que expone los métodos join, position y confirm.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se cambia la firma del constructor o se eliminan los métodos públicos.
 */

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Api\V1;

use App\Core\Http\ResponseFactory;
use App\Http\Controllers\Api\V1\WaitlistController;
use App\Services\Contracts\WaitlistServiceInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(WaitlistController::class)]
final class WaitlistControllerTest extends TestCase
{
    public function test_class_exists(): void
    {
        $this->assertTrue(\class_exists(WaitlistController::class));
    }

    public function test_expected_methods_exist(): void
    {
        $this->assertTrue(\method_exists(WaitlistController::class, 'join'));
        $this->assertTrue(\method_exists(WaitlistController::class, 'position'));
        $this->assertTrue(\method_exists(WaitlistController::class, 'confirm'));
    }

    public function test_can_be_instantiated(): void
    {
        $controller = new WaitlistController(
            response: new ResponseFactory(),
            service: $this->createStub(WaitlistServiceInterface::class),
        );

        $this->assertInstanceOf(WaitlistController::class, $controller);
    }
}
