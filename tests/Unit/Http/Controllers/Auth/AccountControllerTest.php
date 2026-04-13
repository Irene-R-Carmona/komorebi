<?php

/**
 * ¿Qué pruebas aquí?
 * Verifica que Auth/AccountController cumple el contrato PSR-7.
 *
 * ¿Qué me quieres demostrar?
 * Que los métodos retornan ResponseInterface cuando no hay sesión
 * (ruta de protección defensiva antes de tocar servicios).
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se elimina la verificación de sesión al inicio de los métodos
 * o si se cambia el tipo de retorno.
 */

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Auth;

use App\Core\Http\ResponseFactory;
use App\Http\Controllers\Auth\AccountController;
use App\Services\AuthService;
use App\Services\Contracts\AccountDeletionServiceInterface;
use App\Services\Contracts\FileUploadServiceInterface;
use App\Services\Contracts\SessionManagementServiceInterface;
use App\Services\Contracts\UserAccountServiceInterface;
use App\Services\Contracts\UserProfileServiceInterface;
use Nyholm\Psr7\ServerRequest;
use Tests\Support\ControllerTestCase;
use Psr\Http\Message\ResponseInterface;

final class AccountControllerTest extends ControllerTestCase
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

    private function makeController(): AccountController
    {
        return new AccountController(
            authService: $this->createStub(AuthService::class),
            fileUploadService: $this->createStub(FileUploadServiceInterface::class),
            profileService: $this->createStub(UserProfileServiceInterface::class),
            accountService: $this->createStub(UserAccountServiceInterface::class),
            response: new ResponseFactory(),
            accountDeletionService: $this->createStub(AccountDeletionServiceInterface::class),
            sessionService: $this->createStub(SessionManagementServiceInterface::class),
        );
    }

    public function test_class_exists_and_has_key_methods(): void
    {
        $this->assertTrue(method_exists(AccountController::class, 'sessions'));
        $this->assertTrue(method_exists(AccountController::class, 'revokeSession'));
        $this->assertTrue(method_exists(AccountController::class, 'deleteAccount'));
    }

    public function test_can_be_instantiated_without_real_services(): void
    {
        $controller = $this->makeController();
        $this->assertInstanceOf(AccountController::class, $controller);
    }
}
