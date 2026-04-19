<?php

/**
 * ¿Qué pruebas aquí?
 * Verifica que Shared/ReviewController cumple el contrato PSR-7.
 *
 * ¿Qué me quieres demostrar?
 * Que create() lanza ValidationException cuando no hay sesión activa
 * (defensa de contexto sin necesitar acceso a BD).
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se elimina la comprobación de userId al inicio de create(),
 * o si cambia el tipo de excepción lanzada.
 */

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Shared;

use App\Exceptions\ValidationException;
use App\Http\Controllers\Shared\ReviewController;
use App\Repositories\Contracts\CafeRepositoryInterface;
use App\Services\Contracts\ReviewModerationServiceInterface;
use App\Services\Contracts\ReviewQueryServiceInterface;
use App\Services\Contracts\ReviewServiceInterface;
use Tests\Support\ControllerTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

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
            reviewService: $this->createStub(ReviewServiceInterface::class),
            queryService: $this->createStub(ReviewQueryServiceInterface::class),
            moderationService: $this->createStub(ReviewModerationServiceInterface::class),
            cafeRepo: $this->createStub(CafeRepositoryInterface::class),
        );
    }

    public function test_create_throws_validation_exception_when_not_authenticated(): void
    {
        $this->expectException(ValidationException::class);

        $this->makeController()->create($this->makeGetRequest());
    }

    public function test_class_has_expected_methods(): void
    {
        $this->assertTrue(\method_exists(ReviewController::class, 'create'));
        $this->assertTrue(\method_exists(ReviewController::class, 'update'));
        $this->assertTrue(\method_exists(ReviewController::class, 'delete'));
        $this->assertTrue(\method_exists(ReviewController::class, 'pending'));
        $this->assertTrue(\method_exists(ReviewController::class, 'approve'));
        $this->assertTrue(\method_exists(ReviewController::class, 'reject'));
    }
}
