<?php

/**
 * ¿Qué pruebas aquí?
 * Smoke tests de User\CartController.
 *
 * ¿Qué me quieres demostrar?
 * Que el controller existe y tiene el método index().
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se renombra la clase o el método index del CartController.
 */

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\User;

use App\Http\Controllers\User\CartController;
use PHPUnit\Framework\TestCase;

final class CartControllerTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function test_class_exists_with_index_method(): void
    {
        $this->assertTrue(class_exists(CartController::class));
        $this->assertTrue(method_exists(CartController::class, 'index'));
    }
}
