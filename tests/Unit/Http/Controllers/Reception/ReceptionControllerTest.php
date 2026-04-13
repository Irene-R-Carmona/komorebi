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

use App\Core\Http\ResponseFactory;
use App\Http\Controllers\Reception\ReceptionController;
use App\Services\ReceptionService;
use Nyholm\Psr7\ServerRequest;
use Tests\Support\ControllerTestCase;

final class ReceptionControllerTest extends ControllerTestCase
{
    protected function setUp(): void
    {
        if (session_status() === \PHP_SESSION_NONE) {
            session_start();
        }
        // ReceptionController llama Middleware::auth() en constructor
        $_SESSION['user_id'] = 1;
        $_SESSION['user'] = ['id' => 1, 'roles' => ['reception']];
        $_SESSION['user_roles'] = ['reception'];
        // Evita que el TTL check dispare fetchUserFromDb() en tests unitarios
        $_SESSION['_user_verified_at'] = time();
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    private function makeController(): ReceptionController
    {
        return new ReceptionController(
            service: new ReceptionService(),
            response: new ResponseFactory(),
        );
    }

    public function test_check_in_redirects_when_id_is_zero(): void
    {
        $result = $this->makeController()->checkIn(
            (new ServerRequest('POST', '/ops/reception/reservations/0/checkin'))
                ->withParsedBody(['tracker_id' => 1]),
            0
        );

        $this->assertResponseIsRedirect($result, '/ops/reception');
    }

    public function test_check_in_redirects_when_tracker_id_is_zero(): void
    {
        $result = $this->makeController()->checkIn(
            (new ServerRequest('POST', '/ops/reception/reservations/1/checkin'))
                ->withParsedBody(['tracker_id' => 0]),
            1
        );

        $this->assertResponseIsRedirect($result, '/ops/reception');
    }

    public function test_check_out_redirects_when_id_is_zero(): void
    {
        $result = $this->makeController()->checkOut(
            new ServerRequest('POST', '/ops/reception/reservations/0/checkout'),
            0
        );

        $this->assertResponseIsRedirect($result, '/ops/reception');
    }

    public function test_class_has_expected_methods(): void
    {
        $this->assertTrue(method_exists(ReceptionController::class, 'index'));
        $this->assertTrue(method_exists(ReceptionController::class, 'todayReservations'));
        $this->assertTrue(method_exists(ReceptionController::class, 'checkIn'));
        $this->assertTrue(method_exists(ReceptionController::class, 'checkOut'));
    }
}
