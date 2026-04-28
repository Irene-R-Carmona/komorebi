<?php

/**
 * ¿Qué pruebas aquí?
 * Verifica que Admin/ReviewController cumple el contrato SSR esperado.
 *
 * ¿Qué me quieres demostrar?
 * Que el controlador se puede instanciar con stubs y expone el método index().
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se elimina index() o cambia el constructor del controlador.
 */

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Admin;

use App\Http\Controllers\Admin\ReviewController;
use App\Services\Contracts\ReviewModerationServiceInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReviewController::class)]
final class ReviewControllerTest extends TestCase
{
    private function makeController(?ReviewModerationServiceInterface $service = null): ReviewController
    {
        return new ReviewController(
            $service ?? $this->createStub(ReviewModerationServiceInterface::class)
        );
    }

    public function test_instance_can_be_created_with_stubs(): void
    {
        $this->assertInstanceOf(ReviewController::class, $this->makeController());
    }

    public function test_class_has_expected_methods(): void
    {
        $this->assertTrue(\method_exists(ReviewController::class, 'index'));
    }
}
