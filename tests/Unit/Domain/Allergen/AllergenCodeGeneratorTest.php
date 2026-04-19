<?php

/**
 * ¿Qué prueba aquí? La generación de códigos cortos de alérgenos desde nombres arbitrarios.
 * ¿Qué me quieres demostrar? fromName() normaliza, translitea y trunca correctamente,
 *   y maneja nombres vacíos o con solo caracteres especiales sin explotar.
 * ¿Qué va a fallar en este test si se cambia el código? Si se cambia la longitud máxima (10),
 *   el charset de salida (A-Z0-9), el fallback ('ALLERGEN') o la lógica de transliteración.
 */

declare(strict_types=1);

namespace Tests\Unit\Domain\Allergen;

use App\Domain\Allergen\AllergenCodeGenerator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(AllergenCodeGenerator::class)]
final class AllergenCodeGeneratorTest extends TestCase
{
    public function testFromNameSimpleAscii(): void
    {
        $this->assertSame('GLUTEN', AllergenCodeGenerator::fromName('Gluten'));
    }

    public function testFromNameUppercasesOutput(): void
    {
        $this->assertSame('LACTEOS', AllergenCodeGenerator::fromName('lacteos'));
    }

    public function testFromNameTruncatesAt10Characters(): void
    {
        $code = AllergenCodeGenerator::fromName('Crustaceos y mariscos derivados');
        $this->assertLessThanOrEqual(10, \strlen($code));
        $this->assertSame('CRUSTACEOS', $code);
    }

    public function testFromNameTransliteratesSpanishAccents(): void
    {
        $code = AllergenCodeGenerator::fromName('Lácteos');
        $this->assertMatchesRegularExpression('/^[A-Z0-9]+$/', $code);
        $this->assertLessThanOrEqual(10, \strlen($code));
    }

    public function testFromNameEmptyStringReturnsFallback(): void
    {
        $this->assertSame('ALLERGEN', AllergenCodeGenerator::fromName(''));
    }

    public function testFromNameOnlySpecialCharsReturnsFallback(): void
    {
        $this->assertSame('ALLERGEN', AllergenCodeGenerator::fromName('!@#$%^&*()'));
    }

    public function testFromNameOnlySpacesReturnsFallback(): void
    {
        $this->assertSame('ALLERGEN', AllergenCodeGenerator::fromName('   '));
    }

    #[DataProvider('nameProvider')]
    public function testFromNameOutputIsAlwaysValidCode(string $name): void
    {
        $code = AllergenCodeGenerator::fromName($name);
        $this->assertMatchesRegularExpression('/^[A-Z0-9]+$/', $code);
        $this->assertGreaterThan(0, \strlen($code));
        $this->assertLessThanOrEqual(10, \strlen($code));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nameProvider(): array
    {
        return [
            'simple'       => ['Cacahuetes'],
            'accented'     => ['Sésamo'],
            'with spaces'  => ['Frutos secos'],
            'mixed'        => ['Huevos (y derivados)'],
            'numbers'      => ['Alérgeno 9'],
            'only numbers' => ['123'],
            // Caracteres japoneses no son transliterables a ASCII → devuelve ALLERGEN (fallback)
            'japanese'     => ['アレルゲン'],
        ];
    }
}

