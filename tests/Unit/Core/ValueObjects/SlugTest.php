<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? Construcción y rechazo del VO Slug.
 * ¿Qué me quieres demostrar? Que Slug solo acepta [a-z0-9-]+ y no mayúsculas ni espacios.
 * ¿Qué va a fallar en este test si se cambia el código? Si se acepta un slug con mayúsculas o espacios.
 */

use App\Core\ValueObjects\Slug;
use App\Exceptions\ValidationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Slug::class)]
final class SlugTest extends TestCase
{
    public function testValidSlugIsAccepted(): void
    {
        $slug = new Slug('komorebi-cafe-tokyo');
        $this->assertSame('komorebi-cafe-tokyo', $slug->getValue());
    }

    public function testSingleWordSlugIsAccepted(): void
    {
        $slug = new Slug('komorebi');
        $this->assertSame('komorebi', $slug->getValue());
    }

    public function testSlugWithNumbersIsAccepted(): void
    {
        $slug = new Slug('cafe-42');
        $this->assertSame('cafe-42', $slug->getValue());
    }

    public function testUppercaseSlugThrows(): void
    {
        $this->expectException(ValidationException::class);
        new Slug('Cafe-Tokyo');
    }

    public function testSlugWithSpacesThrows(): void
    {
        $this->expectException(ValidationException::class);
        new Slug('cafe tokyo');
    }

    public function testEmptySlugThrows(): void
    {
        $this->expectException(ValidationException::class);
        new Slug('');
    }
}
