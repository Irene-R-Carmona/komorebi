<?php

/**
 * ¿Qué pruebas aquí?
 * Verifica que Kitchen/KitchenController cumple el contrato SSR esperado.
 *
 * ¿Qué me quieres demostrar?
 * Que el controlador se puede instanciar con stubs y expone los métodos
 * de visualización del KDS (index, history, activeOrders).
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se elimina alguno de los métodos SSR del controlador,
 * o si el constructor deja de aceptar KitchenServiceInterface.
 */

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Kitchen;

use App\Core\Http\ResponseFactory;
use App\Http\Controllers\Kitchen\KitchenController;
use App\Services\Contracts\KitchenServiceInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(KitchenController::class)]
final class KitchenControllerTest extends TestCase
{
    protected function setUp(): void
    {
        if (\session_status() === \PHP_SESSION_NONE) {
            \session_start();
        }
        $_SESSION['user_id'] = 1;
        $_SESSION['user'] = ['id' => 1, 'name' => 'Chef', 'roles' => ['kitchen']];
        $_SESSION['user_roles'] = ['kitchen'];
        $_SESSION['_user_verified_at'] = \time();
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    private function makeController(): KitchenController
    {
        return new KitchenController(
            service: $this->createStub(KitchenServiceInterface::class),
            response: new ResponseFactory(),
        );
    }

    public function test_instance_can_be_created_with_stubs(): void
    {
        $this->assertInstanceOf(KitchenController::class, $this->makeController());
    }

    public function test_class_has_expected_methods(): void
    {
        $this->assertTrue(\method_exists(KitchenController::class, 'index'));
        $this->assertTrue(\method_exists(KitchenController::class, 'history'));
        $this->assertTrue(\method_exists(KitchenController::class, 'activeOrders'));
    }
}
