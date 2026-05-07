<?php

/**
 * ¿Qué pruebas aquí?
 * Contrato básico de Public/WaitlistViewController: instanciación sin DI container.
 *
 * ¿Qué me quieres demostrar?
 * Que el controlador puede instanciarse con la interfaz de servicio inyectada.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se cambia la firma del constructor o se añade una dependencia no nullable.
 */

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Public;

use App\Http\Controllers\Public\WaitlistViewController;
use App\Services\Contracts\WaitlistServiceInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(WaitlistViewController::class)]
final class WaitlistViewControllerTest extends TestCase
{
    public function test_class_exists(): void
    {
        $this->assertTrue(\class_exists(WaitlistViewController::class));
    }

    public function test_expected_methods_exist(): void
    {
        $this->assertTrue(\method_exists(WaitlistViewController::class, 'status'));
        $this->assertTrue(\method_exists(WaitlistViewController::class, 'confirmView'));
        $this->assertTrue(\method_exists(WaitlistViewController::class, 'confirmSubmit'));
    }

    public function test_can_be_instantiated(): void
    {
        $controller = new WaitlistViewController(
            service: $this->createStub(WaitlistServiceInterface::class),
        );

        $this->assertInstanceOf(WaitlistViewController::class, $controller);
    }
}
