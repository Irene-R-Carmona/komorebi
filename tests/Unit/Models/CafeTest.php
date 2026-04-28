<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Cafe;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * ¿Qué pruebas aquí? Constantes de dominio del modelo Cafe.
 * ¿Qué me quieres demostrar? Que los valores de categoría no cambian silenciosamente.
 * ¿Qué va a fallar en este test si se cambia el código? Cualquier renombre o eliminación de constante.
 */
#[CoversClass(Cafe::class)]
final class CafeTest extends TestCase
{
    public function testCategoryConstants(): void
    {
        $this->assertSame('lounge', Cafe::CATEGORY_LOUNGE);
        $this->assertSame('playroom', Cafe::CATEGORY_PLAYROOM);
        $this->assertSame('farm', Cafe::CATEGORY_FARM);
        $this->assertSame('zen', Cafe::CATEGORY_ZEN);
    }

    public function testValidCategoriesContainsAllFour(): void
    {
        $this->assertCount(4, Cafe::VALID_CATEGORIES);
        $this->assertContains(Cafe::CATEGORY_LOUNGE, Cafe::VALID_CATEGORIES);
        $this->assertContains(Cafe::CATEGORY_PLAYROOM, Cafe::VALID_CATEGORIES);
        $this->assertContains(Cafe::CATEGORY_FARM, Cafe::VALID_CATEGORIES);
        $this->assertContains(Cafe::CATEGORY_ZEN, Cafe::VALID_CATEGORIES);
    }

    public function testValidCategoriesDoesNotContainUnknownCategory(): void
    {
        $this->assertNotContains('outdoor', Cafe::VALID_CATEGORIES);
    }
}
