<?php

/**
 * ¿Qué pruebas aquí?
 * Verifica el contrato PSR-7 de Manager/CafeController.
 *
 * ¿Qué me quieres demostrar?
 * Que updateCapacity() devuelve 403 cuando el usuario no tiene café asignado,
 * y que devuelve 400 cuando la capacidad es inválida.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se elimina la validación de café asignado o de capacidad en updateCapacity().
 */

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Manager;

use App\Core\Http\ResponseFactory;
use App\Http\Controllers\Manager\CafeController;
use Nyholm\Psr7\ServerRequest;
use Tests\Support\ControllerTestCase;

final class CafeControllerTest extends ControllerTestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    private function makeController(): CafeController
    {
        return new CafeController(response: new ResponseFactory());
    }

    public function test_update_capacity_returns_403_when_no_cafe_assigned(): void
    {
        $_SESSION['user_id'] = 1;
        // Sin user_cafe_id → cafeId será null

        $result = $this->makeController()->updateCapacity(
            new ServerRequest('POST', '/manager/cafe/capacity')
        );

        $this->assertSame(403, $result->getStatusCode());
        $body = json_decode((string) $result->getBody(), true);
        $this->assertFalse($body['success']);
    }

    public function test_update_capacity_returns_400_when_capacity_is_zero(): void
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['user_cafe_id'] = 5;

        $result = $this->makeController()->updateCapacity(
            (new ServerRequest('POST', '/manager/cafe/capacity'))
                ->withParsedBody(['capacity_max' => 0])
        );

        $this->assertSame(400, $result->getStatusCode());
    }

    public function test_class_has_expected_methods(): void
    {
        $this->assertTrue(method_exists(CafeController::class, 'show'));
        $this->assertTrue(method_exists(CafeController::class, 'updateCapacity'));
        $this->assertTrue(method_exists(CafeController::class, 'updateSchedule'));
        $this->assertTrue(method_exists(CafeController::class, 'updateSettings'));
    }
}
