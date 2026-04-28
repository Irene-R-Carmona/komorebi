<?php

declare(strict_types=1);

namespace Tests\Unit\Models\Traits;

use App\Models\Traits\HasUuid;
use PHPUnit\Framework\TestCase;

/**
 * ¿Qué pruebas aquí? Los métodos protegidos del trait HasUuid.
 * ¿Qué me quieres demostrar? Que generateUuid produce UUIDs v4 válidos y que isValidUuid discrimina correctamente.
 * ¿Qué va a fallar en este test si se cambia el código? Cambios en el formato del UUID generado o en el patrón de validación.
 */
final class HasUuidTest extends TestCase
{
    private object $harness;

    protected function setUp(): void
    {
        $this->harness = new class {
            use HasUuid;

            public function callGenerateUuid(): string
            {
                return $this->generateUuid();
            }

            public function callIsValidUuid(string $uuid): bool
            {
                return $this->isValidUuid($uuid);
            }
        };
    }

    // ── generateUuid ─────────────────────────────────────────────

    public function testGenerateUuidMatchesUuidV4Format(): void
    {
        $uuid = $this->harness->callGenerateUuid();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $uuid,
            'UUID generado no sigue el formato v4 RFC4122'
        );
    }

    public function testGenerateUuidProducesUniquesOnSubsequentCalls(): void
    {
        $uuid1 = $this->harness->callGenerateUuid();
        $uuid2 = $this->harness->callGenerateUuid();

        $this->assertNotSame($uuid1, $uuid2);
    }

    public function testGenerateUuidHasCorrectLength(): void
    {
        $uuid = $this->harness->callGenerateUuid();

        $this->assertSame(36, \strlen($uuid));
    }

    // ── isValidUuid ──────────────────────────────────────────────

    public function testIsValidUuidReturnsTrueForValidV4Uuid(): void
    {
        $this->assertTrue($this->harness->callIsValidUuid('550e8400-e29b-4d00-a456-426614174000'));
        $this->assertTrue($this->harness->callIsValidUuid('a1b2c3d4-e5f6-4a7b-8c9d-0e1f2a3b4c5d'));
    }

    public function testIsValidUuidReturnsTrueForUuidGeneratedByTrait(): void
    {
        $uuid = $this->harness->callGenerateUuid();

        $this->assertTrue($this->harness->callIsValidUuid($uuid));
    }

    public function testIsValidUuidReturnsFalseForEmptyString(): void
    {
        $this->assertFalse($this->harness->callIsValidUuid(''));
    }

    public function testIsValidUuidReturnsFalseForInvalidFormat(): void
    {
        $this->assertFalse($this->harness->callIsValidUuid('not-a-uuid'));
        $this->assertFalse($this->harness->callIsValidUuid('550e8400-e29b-3d00-a456-426614174000')); // versión 3, no 4
        $this->assertFalse($this->harness->callIsValidUuid('550e8400e29b4d00a456426614174000')); // sin guiones
    }
}
