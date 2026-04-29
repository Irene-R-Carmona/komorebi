<?php

/**
 * ¿Qué pruebas aquí?
 * Verifica que Admin/ReviewController cumple el contrato PSR-7 como API REST.
 *
 * ¿Qué me quieres demostrar?
 * Que approve() y reject() retornan JSON 200 con ok:true cuando el servicio
 * tiene éxito, y JSON 422 cuando el servicio falla.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se cambia la firma de approve()/reject() (ya no aceptan int),
 * si el JSON response cambia de estructura, o si el código HTTP cambia.
 */

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Admin;

use App\Core\Result;
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

    private function makeController(?ReviewModerationServiceInterface $service = null): ReviewController
    {
        return new ReviewController(
            $service ?? $this->createStub(ReviewModerationServiceInterface::class)
        );
    }

    public function test_approve_returns_json_ok_when_service_succeeds(): void
    {
        $stub = $this->createStub(ReviewModerationServiceInterface::class);
        $stub->method('approveReview')->willReturn(Result::ok());

        $response = $this->makeController($stub)->approve(1);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertResponseIsJson($response, 200);
    }

    public function test_approve_returns_422_when_service_fails(): void
    {
        $stub = $this->createStub(ReviewModerationServiceInterface::class);
        $stub->method('approveReview')->willReturn(Result::fail('Error al aprobar reseña'));

        $response = $this->makeController($stub)->approve(1);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame(422, $response->getStatusCode());
    }

    public function test_reject_returns_json_ok_when_service_succeeds(): void
    {
        $stub = $this->createStub(ReviewModerationServiceInterface::class);
        $stub->method('rejectReview')->willReturn(Result::ok());

        $response = $this->makeController($stub)->reject(1);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertResponseIsJson($response, 200);
    }

    public function test_reject_returns_422_when_service_fails(): void
    {
        $stub = $this->createStub(ReviewModerationServiceInterface::class);
        $stub->method('rejectReview')->willReturn(Result::fail('Error al rechazar reseña'));

        $response = $this->makeController($stub)->reject(1);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame(422, $response->getStatusCode());
    }

    public function test_class_has_expected_methods(): void
    {
        $this->assertTrue(\method_exists(ReviewController::class, 'index'));
        $this->assertTrue(\method_exists(ReviewController::class, 'approve'));
        $this->assertTrue(\method_exists(ReviewController::class, 'reject'));
        $this->assertTrue(\method_exists(ReviewController::class, 'delete'));
    }
}
