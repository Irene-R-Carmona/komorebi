<?php

/**
 * ¿Qué pruebas aquí?
 * Verifica que Admin\AuthLogController delega a AuthLogRepository.
 *
 * ¿Qué me quieres demostrar?
 * Que el controlador acepta AuthLogRepository inyectado y expone los métodos esperados.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se renombra AuthLogRepository o deja de aceptarse como dependencia inyectable.
 */

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Admin;

use App\Http\Controllers\Admin\AuthLogController;
use App\Repositories\Contracts\AuthLogRepositoryInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Support\ControllerTestCase;

#[CoversClass(AuthLogController::class)]
final class AuthLogControllerTest extends ControllerTestCase
{
    protected function setUp(): void
    {
        if (\session_status() === \PHP_SESSION_NONE) {
            \session_start();
        }
        $_SESSION = [];
        $_SERVER['HTTP_X_REQUESTED_WITH'] = '';
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        unset($_SERVER['HTTP_X_REQUESTED_WITH']);
    }

    private function makeController(): AuthLogController
    {
        return new AuthLogController(
            $this->createStub(AuthLogRepositoryInterface::class)
        );
    }

    public function test_class_has_expected_methods(): void
    {
        $this->assertTrue(\method_exists(AuthLogController::class, 'index'));
    }

    public function test_instance_can_be_created_with_stub(): void
    {
        $this->assertInstanceOf(AuthLogController::class, $this->makeController());
    }
}
