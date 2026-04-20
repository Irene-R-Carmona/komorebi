<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? Construcción y rechazo del VO Uuid.
 * ¿Qué me quieres demostrar? Que Uuid solo acepta UUIDs v4 válidos.
 * ¿Qué va a fallar en este test si se cambia el código? Si se acepta un UUID con formato inválido.
 */

use App\Core\ValueObjects\Uuid;
use App\Exceptions\ValidationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Uuid::class)]
final class UuidTest extends TestCase
{
    public function testValidUuidIsAccepted(): void
    {
        $uuid = new Uuid('550e8400-e29b-41d4-a716-446655440000');
        $this->assertSame('550e8400-e29b-41d4-a716-446655440000', $uuid->getValue());
    }

    public function testInvalidUuidThrows(): void
    {
        $this->expectException(ValidationException::class);
        new Uuid('not-a-uuid');
    }

    public function testUuidWithWrongVersionThrows(): void
    {
        // UUID v1 – no es v4
        $this->expectException(ValidationException::class);
        new Uuid('6ba7b810-9dad-11d1-80b4-00c04fd430c8');
    }

    public function testEmptyUuidThrows(): void
    {
        $this->expectException(ValidationException::class);
        new Uuid('');
    }

    public function testToStringMatchesGetValue(): void
    {
        $id = '550e8400-e29b-41d4-a716-446655440000';
        $uuid = new Uuid($id);
        $this->assertSame($id, (string) $uuid);
    }
}
