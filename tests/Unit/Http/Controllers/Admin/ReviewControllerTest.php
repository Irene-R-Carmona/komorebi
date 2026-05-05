<?php

/**
 * ¿Qué pruebas aquí?
 * Smoke test de Admin\ReviewController (SSR): verifica construcción y método público.
 *
 * ¿Qué me quieres demostrar?
 * Que el controlador SSR expone index() y acepta ReviewModerationServiceInterface.
 * Las mutaciones (approve/reject/delete) viven en Api\V1\Admin\ReviewApiController.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se elimina index() o cambia la firma del constructor.
 */

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Admin;

use App\Http\Controllers\Admin\ReviewController;
use App\Services\Contracts\ReviewModerationServiceInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Support\ControllerTestCase;

#[CoversClass(ReviewController::class)]
final class ReviewControllerTest extends ControllerTestCase
{
    public function test_class_has_expected_methods(): void
    {
        $this->assertTrue(\method_exists(ReviewController::class, 'index'));
    }

    public function test_instance_can_be_created_with_stub(): void
    {
        $controller = new ReviewController(
            $this->createStub(ReviewModerationServiceInterface::class)
        );
        $this->assertInstanceOf(ReviewController::class, $controller);
    }
}
