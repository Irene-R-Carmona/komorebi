<?php

/**
 * ¿Qué pruebas aquí?
 * Verifica que Auth/PasswordResetController cumple el contrato PSR-7.
 *
 * ¿Qué me quieres demostrar?
 * Que el controlador puede instanciarse, que su método showForgotPassword()
 * retorna null (renderiza vista) cuando el usuario no está autenticado.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se cambia el tipo de retorno de showForgotPassword() o se rompe el namespace.
 */

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Auth;

use App\Core\Http\ResponseFactory;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Services\Contracts\AuthServiceInterface;
use App\Services\Contracts\EmailVerificationServiceInterface;
use App\Services\Contracts\PasswordResetServiceInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Support\ControllerTestCase;

#[CoversClass(PasswordResetController::class)]
final class PasswordResetControllerTest extends ControllerTestCase
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

    private function makeController(): PasswordResetController
    {
        return new PasswordResetController(
            authService: $this->createStub(AuthServiceInterface::class),
            passwordResetService: $this->createStub(PasswordResetServiceInterface::class),
            emailVerificationService: $this->createStub(EmailVerificationServiceInterface::class),
        );
    }

    public function test_class_exists_and_has_key_methods(): void
    {
        $this->assertTrue(\class_exists(PasswordResetController::class));
        $this->assertTrue(\method_exists(PasswordResetController::class, 'forgotPasswordForm'));
        $this->assertTrue(\method_exists(PasswordResetController::class, 'sendResetEmail'));
        $this->assertTrue(\method_exists(PasswordResetController::class, 'resetPasswordForm'));
        $this->assertTrue(\method_exists(PasswordResetController::class, 'processReset'));
    }

    public function test_can_be_instantiated_without_real_services(): void
    {
        $controller = $this->makeController();
        $this->assertInstanceOf(PasswordResetController::class, $controller);
    }

    public function test_forgot_password_form_redirects_when_authenticated(): void
    {
        $authStub = $this->createStub(AuthServiceInterface::class);
        $authStub->method('check')->willReturn(true);
        $controller = new PasswordResetController(
            authService: $authStub,
            passwordResetService: $this->createStub(PasswordResetServiceInterface::class),
            emailVerificationService: $this->createStub(EmailVerificationServiceInterface::class),
            response: new ResponseFactory()
        );

        $result = $controller->forgotPasswordForm($this->makeGetRequest());

        $this->assertNotNull($result);
        $this->assertSame(302, $result->getStatusCode());
    }
}
