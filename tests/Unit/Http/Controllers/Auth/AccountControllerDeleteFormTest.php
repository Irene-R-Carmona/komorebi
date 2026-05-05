<?php

/**
 * ¿Qué pruebas aquí?
 * Verifica que AccountController expone showDeleteForm() con el contrato correcto.
 *
 * ¿Qué me quieres demostrar?
 * Que showDeleteForm() existe y redirige al login cuando el usuario no está autenticado.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se elimina showDeleteForm(), si cambia su firma, o si deja de verificar autenticación.
 */

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Auth;

use App\Core\Http\ResponseFactory;
use App\Http\Controllers\Auth\AccountController;
use App\Services\Contracts\AccountDeletionServiceInterface;
use App\Services\Contracts\AuthServiceInterface;
use App\Services\Contracts\FileUploadServiceInterface;
use App\Services\Contracts\SessionManagementServiceInterface;
use App\Services\Contracts\UserAccountServiceInterface;
use App\Services\Contracts\UserProfileServiceInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Support\ControllerTestCase;

#[CoversClass(AccountController::class)]
final class AccountControllerDeleteFormTest extends ControllerTestCase
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

    private function makeController(?AuthServiceInterface $auth = null): AccountController
    {
        return new AccountController(
            authService: $auth ?? $this->createStub(AuthServiceInterface::class),
            fileUploadService: $this->createStub(FileUploadServiceInterface::class),
            profileService: $this->createStub(UserProfileServiceInterface::class),
            accountService: $this->createStub(UserAccountServiceInterface::class),
            response: new ResponseFactory(),
            accountDeletionService: $this->createStub(AccountDeletionServiceInterface::class),
            sessionService: $this->createStub(SessionManagementServiceInterface::class),
        );
    }

    public function test_show_delete_form_method_exists(): void
    {
        $this->assertTrue(\method_exists(AccountController::class, 'showDeleteForm'));
    }

    public function test_show_delete_form_redirects_when_not_authenticated(): void
    {
        $auth = $this->createStub(AuthServiceInterface::class);
        $auth->method('check')->willReturn(false);

        $controller = $this->makeController($auth);
        $request = $this->makeGetRequest('/account/delete', []);

        $response = $controller->showDeleteForm($request);

        $this->assertNotNull($response);
        $this->assertResponseIsRedirect($response, '/login');
    }
}
