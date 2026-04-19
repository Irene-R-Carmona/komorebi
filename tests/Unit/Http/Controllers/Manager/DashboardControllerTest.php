<?php

/**
 * ¿Qué pruebas aquí?
 * Verifica la estructura básica de Manager/DashboardController.
 *
 * ¿Qué me quieres demostrar?
 * Que el controlador puede instanciarse con sus dependencias opcionales
 * y que expone el método index esperado.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se elimina el método index o se cambia la firma del constructor.
 */

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Manager;

use App\Http\Controllers\Manager\DashboardController;
use ReflectionClass;
use Tests\Support\ControllerTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(DashboardController::class)]
final class DashboardControllerTest extends ControllerTestCase
{
    public function test_class_exists(): void
    {
        $this->assertTrue(\class_exists(DashboardController::class));
    }

    public function test_index_method_exists(): void
    {
        $this->assertTrue(\method_exists(DashboardController::class, 'index'));
    }

    public function test_constructor_accepts_optional_dependencies(): void
    {
        // No construir con args por defecto — DashboardService conecta a DB eagerly.
        // Verificamos solo la firma del constructor mediante reflexión.
        $rc = new ReflectionClass(DashboardController::class);
        $params = $rc->getConstructor()?->getParameters() ?? [];
        foreach ($params as $param) {
            $this->assertTrue($param->isOptional(), "Param {$param->getName()} should be optional");
        }
    }
}
