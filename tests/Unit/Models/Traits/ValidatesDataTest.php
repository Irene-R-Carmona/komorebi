<?php

declare(strict_types=1);

namespace Tests\Unit\Models\Traits;

use App\Models\Traits\ValidatesData;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * ¿Qué pruebas aquí? Los 5 métodos protegidos del trait ValidatesData.
 * ¿Qué me quieres demostrar? Que las validaciones lanzan RuntimeException en casos inválidos y retornan valores saneados cuando son correctos.
 * ¿Qué va a fallar en este test si se cambia el código? Cambios en los mensajes de excepción, en los límites de validación o en la lógica de sanitización.
 */
final class ValidatesDataTest extends TestCase
{
    private object $harness;

    protected function setUp(): void
    {
        $this->harness = new class () {
            use ValidatesData;

            public function callValidateRequired(array $data, array $fields): void
            {
                $this->validateRequired($data, $fields);
            }

            public function callValidateInArray(mixed $value, array $allowed, string $field): void
            {
                $this->validateInArray($value, $allowed, $field);
            }

            public function callValidatePositiveInt(mixed $value, string $field): int
            {
                return $this->validatePositiveInt($value, $field);
            }

            public function callSanitizeString(string $value, int $maxLength = 255): string
            {
                return $this->sanitizeString($value, $maxLength);
            }

            public function callSanitizeSlug(string $value): string
            {
                return $this->sanitizeSlug($value);
            }
        };
    }

    // ── validateRequired ─────────────────────────────────────────

    public function testValidateRequiredPassesWhenFieldsPresent(): void
    {
        $this->harness->callValidateRequired(['name' => 'Ana', 'email' => 'ana@example.com'], ['name', 'email']);

        $this->assertTrue(true); // no exception thrown
    }

    public function testValidateRequiredThrowsWhenFieldMissing(): void
    {
        $this->expectException(RuntimeException::class);
        $this->harness->callValidateRequired(['name' => 'Ana'], ['name', 'email']);
    }

    public function testValidateRequiredThrowsWhenFieldIsEmptyString(): void
    {
        $this->expectException(RuntimeException::class);
        $this->harness->callValidateRequired(['name' => ''], ['name']);
    }

    public function testValidateRequiredThrowsWhenFieldIsWhitespaceOnly(): void
    {
        $this->expectException(RuntimeException::class);
        $this->harness->callValidateRequired(['name' => '   '], ['name']);
    }

    // ── validateInArray ──────────────────────────────────────────

    public function testValidateInArrayPassesForValidValue(): void
    {
        $this->harness->callValidateInArray('admin', ['admin', 'user', 'manager'], 'role');

        $this->assertTrue(true);
    }

    public function testValidateInArrayThrowsForInvalidValue(): void
    {
        $this->expectException(RuntimeException::class);
        $this->harness->callValidateInArray('superuser', ['admin', 'user', 'manager'], 'role');
    }

    public function testValidateInArrayUsesStrictComparison(): void
    {
        // '1' !== 1 (strict)
        $this->expectException(RuntimeException::class);
        $this->harness->callValidateInArray('1', [1, 2, 3], 'id');
    }

    // ── validatePositiveInt ───────────────────────────────────────

    public function testValidatePositiveIntReturnsIntForValidValue(): void
    {
        $result = $this->harness->callValidatePositiveInt(5, 'quantity');

        $this->assertSame(5, $result);
    }

    public function testValidatePositiveIntReturnsIntForStringDigit(): void
    {
        $result = $this->harness->callValidatePositiveInt('10', 'quantity');

        $this->assertSame(10, $result);
    }

    public function testValidatePositiveIntThrowsForZero(): void
    {
        $this->expectException(RuntimeException::class);
        $this->harness->callValidatePositiveInt(0, 'quantity');
    }

    public function testValidatePositiveIntThrowsForNegativeValue(): void
    {
        $this->expectException(RuntimeException::class);
        $this->harness->callValidatePositiveInt(-3, 'quantity');
    }

    public function testValidatePositiveIntThrowsForNonNumericString(): void
    {
        $this->expectException(RuntimeException::class);
        $this->harness->callValidatePositiveInt('abc', 'quantity');
    }

    // ── sanitizeString ────────────────────────────────────────────

    public function testSanitizeStringTrimesWhitespace(): void
    {
        $result = $this->harness->callSanitizeString('  Neko Café  ');

        $this->assertSame('Neko Café', $result);
    }

    public function testSanitizeStringTruncatesToMaxLength(): void
    {
        $result = $this->harness->callSanitizeString('abcdefghij', 5);

        $this->assertSame('abcde', $result);
    }

    public function testSanitizeStringReturnsFullStringWhenUnderMaxLength(): void
    {
        $result = $this->harness->callSanitizeString('Short', 100);

        $this->assertSame('Short', $result);
    }

    // ── sanitizeSlug ─────────────────────────────────────────────

    public function testSanitizeSlugConvertsToLowercase(): void
    {
        $result = $this->harness->callSanitizeSlug('NekoCafe');

        $this->assertSame('nekocafe', $result);
    }

    public function testSanitizeSlugReplacesSpacesWithDashes(): void
    {
        $result = $this->harness->callSanitizeSlug('neko cafe');

        $this->assertSame('neko-cafe', $result);
    }

    public function testSanitizeSlugCollapseMultipleDashes(): void
    {
        $result = $this->harness->callSanitizeSlug('neko--cafe');

        $this->assertSame('neko-cafe', $result);
    }

    public function testSanitizeSlugRemovesSpecialCharacters(): void
    {
        $result = $this->harness->callSanitizeSlug('neko!@#café');

        $this->assertStringNotContainsString('!', $result);
        $this->assertStringNotContainsString('@', $result);
    }
}
