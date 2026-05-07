<?php

/**
 * ¿Qué pruebas aquí?
 * Verifica que Database::validateCharset() lanza RuntimeException para charsets inválidos.
 *
 * ¿Qué me quieres demostrar?
 * Que valores malformados de charset no pueden inyectarse en SET NAMES.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se elimina la validación o se cambia la whitelist de charsets.
 */

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Database;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(Database::class)]
final class DatabaseCharsetValidationTest extends TestCase
{
    public function test_valid_charset_utf8mb4_passes(): void
    {
        Database::validateCharset('utf8mb4', 'utf8mb4_unicode_ci');
        $this->assertTrue(true);
    }

    public function test_invalid_charset_with_semicolon_throws(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Charset inválido');

        Database::validateCharset('utf8mb4; DROP TABLE users', 'utf8mb4_unicode_ci');
    }

    public function test_invalid_charset_with_space_throws(): void
    {
        $this->expectException(RuntimeException::class);

        Database::validateCharset('utf8 mb4', 'utf8mb4_unicode_ci');
    }

    public function test_invalid_collation_with_dash_injection_throws(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Collation inválida');

        Database::validateCharset('utf8mb4', 'utf8mb4_unicode_ci--');
    }

    public function test_valid_charsets_whitelist(): void
    {
        $validCharsets = ['utf8mb4', 'utf8', 'latin1', 'ascii'];
        foreach ($validCharsets as $charset) {
            Database::validateCharset($charset, 'utf8mb4_unicode_ci');
        }
        $this->assertCount(4, $validCharsets);
    }
}
