<?php

/**
 * ¿Qué pruebas aquí?
 * Contrato básico de Public/NewsletterController: existencia de clase y métodos.
 *
 * ¿Qué me quieres demostrar?
 * Que la clase existe con todos sus métodos públicos.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se eliminan o renombran los métodos del controlador de newsletter.
 */

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Public;

use App\Http\Controllers\Public\NewsletterController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NewsletterController::class)]
final class NewsletterControllerTest extends TestCase
{
    public function test_class_exists(): void
    {
        $this->assertTrue(\class_exists(NewsletterController::class));
    }

    public function test_expected_methods_exist(): void
    {
        $this->assertTrue(\method_exists(NewsletterController::class, 'subscribe'));
        $this->assertTrue(\method_exists(NewsletterController::class, 'verify'));
        $this->assertTrue(\method_exists(NewsletterController::class, 'confirm'));
        $this->assertTrue(\method_exists(NewsletterController::class, 'unsubscribe'));
    }
}
