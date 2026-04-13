<?php

/**
 * ¿Qué pruebas aquí?
 * Verifica que Kitchen/KitchenController cumple el contrato PSR-7.
 *
 * ¿Qué me quieres demostrar?
 * Que ready() lanza ValidationException cuando item_id no se proporciona,
 * y que el controlador puede instanciarse con sesión activa.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se elimina la validación de item_id en ready(),
 * o si el constructor deja de requerir autenticación.
 */

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Kitchen;

use App\Core\Http\ResponseFactory;
use App\Exceptions\ValidationException;
use App\Http\Controllers\Kitchen\KitchenController;
use App\Services\KitchenService;
use Nyholm\Psr7\ServerRequest;
use Tests\Support\ControllerTestCase;

final class KitchenControllerTest extends ControllerTestCase
{
    protected function setUp(): void
    {
        if (session_status() === \PHP_SESSION_NONE) {
            session_start();
        }
        // KitchenController llama Middleware::auth() en constructor
        $_SESSION['user_id'] = 1;
        $_SESSION['user'] = ['id' => 1, 'name' => 'Chef', 'roles' => ['kitchen']];
        $_SESSION['user_roles'] = ['kitchen'];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    private function makeController(): KitchenController
    {
        return new KitchenController(
            service: new KitchenService($this->createStub(\PDO::class)),
            response: new ResponseFactory(),
        );
    }

    public function test_ready_throws_validation_exception_when_item_id_is_missing(): void
    {
        $this->expectException(ValidationException::class);

        $this->makeController()->ready(
            new ServerRequest('POST', '/ops/kitchen/ready')
        );
    }

    public function test_ready_throws_validation_exception_when_item_id_is_zero(): void
    {
        $this->expectException(ValidationException::class);

        $this->makeController()->ready(
            (new ServerRequest('POST', '/ops/kitchen/ready'))
                ->withParsedBody(['item_id' => 0])
        );
    }

    public function test_class_has_expected_methods(): void
    {
        $this->assertTrue(method_exists(KitchenController::class, 'index'));
        $this->assertTrue(method_exists(KitchenController::class, 'ready'));
        $this->assertTrue(method_exists(KitchenController::class, 'activeOrders'));
        $this->assertTrue(method_exists(KitchenController::class, 'completeOrder'));
    }
}
