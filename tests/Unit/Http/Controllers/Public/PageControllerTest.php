<?php

/**
 * ¿Qué pruebas aquí?
 * Contrato básico de Public/PageController: instanciación y existencia de métodos.
 *
 * ¿Qué me quieres demostrar?
 * Que el controlador puede instanciarse sin dependencias externas
 * y que expone los métodos para cada página estática.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se eliminan los métodos historia/faq/contacto o se añade un constructor con deps requeridas.
 */

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Public;

use App\Http\Controllers\Public\PageController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PageController::class)]
final class PageControllerTest extends TestCase
{
    public function test_class_exists(): void
    {
        $this->assertTrue(\class_exists(PageController::class));
    }

    public function test_expected_methods_exist(): void
    {
        $this->assertTrue(\method_exists(PageController::class, 'historia'));
        $this->assertTrue(\method_exists(PageController::class, 'faq'));
        $this->assertTrue(\method_exists(PageController::class, 'contacto'));
    }

    public function test_can_be_instantiated(): void
    {
        $this->assertInstanceOf(PageController::class, new PageController());
    }
}
