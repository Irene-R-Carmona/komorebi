<?php

/**
 * ¿Qué pruebas aquí?
 * Contrato básico de Api/V1/TimeSlotController: existencia de clase y métodos.
 *
 * ¿Qué me quieres demostrar?
 * Que la clase existe con sus métodos disponibles (available y stats).
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se eliminan o renombran los métodos del controlador de time slots.
 *
 * Nota: No se prueba instanciación porque el constructor llama a Database::getConnection()
 * directamente (TODO documentado en el propio controlador — plan6-controller-di).
 */

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\TimeSlotController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TimeSlotController::class)]
final class TimeSlotControllerTest extends TestCase
{
    public function test_class_exists(): void
    {
        $this->assertTrue(\class_exists(TimeSlotController::class));
    }

    public function test_expected_methods_exist(): void
    {
        $this->assertTrue(\method_exists(TimeSlotController::class, 'available'));
        $this->assertTrue(\method_exists(TimeSlotController::class, 'stats'));
    }
}
