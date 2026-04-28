<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Allergen;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * ¿Qué pruebas aquí? Constantes de dominio del modelo Allergen.
 * ¿Qué me quieres demostrar? Que los valores de severidad no cambian silenciosamente.
 * ¿Qué va a fallar en este test si se cambia el código? Cualquier renombre o eliminación de constante.
 */
#[CoversClass(Allergen::class)]
final class AllergenTest extends TestCase
{
    public function testSeverityConstants(): void
    {
        $this->assertSame('low', Allergen::SEVERITY_LOW);
        $this->assertSame('medium', Allergen::SEVERITY_MEDIUM);
        $this->assertSame('high', Allergen::SEVERITY_HIGH);
    }

    public function testValidSeveritiesContainsAllThree(): void
    {
        $this->assertCount(3, Allergen::VALID_SEVERITIES);
        $this->assertContains(Allergen::SEVERITY_LOW, Allergen::VALID_SEVERITIES);
        $this->assertContains(Allergen::SEVERITY_MEDIUM, Allergen::VALID_SEVERITIES);
        $this->assertContains(Allergen::SEVERITY_HIGH, Allergen::VALID_SEVERITIES);
    }

    public function testValidSeveritiesDoesNotContainUnknown(): void
    {
        $this->assertNotContains('critical', Allergen::VALID_SEVERITIES);
    }
}
