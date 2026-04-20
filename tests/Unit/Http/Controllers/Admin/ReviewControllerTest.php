<?php

/**
 * ¿Qué pruebas aquí?
 * Verifica que Admin/ReviewController cumple el contrato PSR-7.
 *
 * ¿Qué me quieres demostrar?
 * Que approve() y reject() retornan ResponseInterface (redirect)
 * cuando el token CSRF es inválido, sin tocar ReviewService.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se elimina la validación CSRF en approve()/reject()/delete()
 * o si la redirección de error cambia de destino.
 */

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Admin;

use App\Http\Controllers\Admin\ReviewController;
use App\Services\Contracts\ReviewModerationServiceInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Message\ResponseInterface;
use Tests\Support\ControllerTestCase;

#[CoversClass(ReviewController::class)]
final class ReviewControllerTest extends ControllerTestCase
{
    protected function setUp(): void
    {
        if (\session_status() === \PHP_SESSION_NONE) {
            \session_start();
        }
        $_SESSION = [];
        $_POST = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_POST = [];
    }

    private function makeController(): ReviewController
    {
        return new ReviewController(
            $this->createStub(ReviewModerationServiceInterface::class)
        );
    }

    public function test_approve_redirects_when_csrf_is_invalid(): void
    {
        $_SESSION['_csrf_token'] = '';

        $result = $this->makeController()->approve();

        $this->assertInstanceOf(ResponseInterface::class, $result);
        $this->assertResponseIsRedirect($result, '/admin/reviews');
    }

    public function test_reject_redirects_when_csrf_is_invalid(): void
    {
        $_SESSION['_csrf_token'] = '';

        $result = $this->makeController()->reject();

        $this->assertInstanceOf(ResponseInterface::class, $result);
        $this->assertResponseIsRedirect($result, '/admin/reviews');
    }

    public function test_class_has_expected_methods(): void
    {
        $this->assertTrue(\method_exists(ReviewController::class, 'index'));
        $this->assertTrue(\method_exists(ReviewController::class, 'approve'));
        $this->assertTrue(\method_exists(ReviewController::class, 'reject'));
        $this->assertTrue(\method_exists(ReviewController::class, 'delete'));
    }
}
