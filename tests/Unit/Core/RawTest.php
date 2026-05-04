<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * La clase Raw es un value object que envuelve strings ya seguros para evitar
 * el doble-escapado del motor de vistas.
 *
 * ¿Qué me quieres demostrar?
 * Que Raw::json() codifica correctamente con flags anti-XSS, que Raw::html()
 * y Raw::safe() simplemente envuelven el valor, y que decodeJsonArray()
 * devuelve [] ante cualquier entrada no válida.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Cualquier cambio en los flags de json_encode, en el comportamiento de
 * decodeJsonArray ante errores, o en la lógica de __toString.
 */

namespace Tests\Unit\Core;

use App\Core\Raw;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Raw::class)]
final class RawTest extends TestCase
{
    // ──────────────────────────────────────────────────────────
    // Constructor / __toString
    // ──────────────────────────────────────────────────────────

    public function testConstructorStoresValue(): void
    {
        $raw = new Raw('hello');
        self::assertSame('hello', $raw->value);
    }

    public function testToStringReturnsValue(): void
    {
        $raw = new Raw('world');
        self::assertSame('world', (string) $raw);
    }

    // ──────────────────────────────────────────────────────────
    // Raw::json()
    // ──────────────────────────────────────────────────────────

    public function testJsonEncodesArrayToJsonString(): void
    {
        $raw = Raw::json(['key' => 'value']);
        /** @phpstan-ignore staticMethod.alreadyNarrowedType */
        self::assertInstanceOf(Raw::class, $raw);
        $decoded = \json_decode((string) $raw, true);
        self::assertSame(['key' => 'value'], $decoded);
    }

    public function testJsonEscapesAngleBracketsForXss(): void
    {
        $raw = Raw::json(['html' => '<script>']);
        // JSON_HEX_TAG debe escapar < y >
        self::assertStringNotContainsString('<script>', (string) $raw);
        self::assertStringContainsString('\\u003C', (string) $raw);
    }

    public function testJsonEscapesAmpersandForXss(): void
    {
        $raw = Raw::json(['a' => 'foo&bar']);
        self::assertStringNotContainsString('&', (string) $raw);
        self::assertStringContainsString('\\u0026', (string) $raw);
    }

    public function testJsonEscapesSingleQuoteForXss(): void
    {
        $raw = Raw::json(['a' => "it's"]);
        self::assertStringNotContainsString("'", (string) $raw);
        self::assertStringContainsString('\\u0027', (string) $raw);
    }

    public function testJsonEscapesDoubleQuoteInsideValue(): void
    {
        $raw = Raw::json(['a' => 'say "hi"']);
        // JSON_HEX_QUOT escapa " dentro de los valores
        self::assertStringContainsString('\\u0022', (string) $raw);
    }

    public function testJsonThrowsJsonExceptionOnInvalidData(): void
    {
        $this->expectException(JsonException::class);
        $resource = \fopen('php://memory', 'r');

        try {
            Raw::json(['res' => $resource]);
        } finally {
            \fclose($resource);
        }
    }

    public function testJsonHandlesEmptyArray(): void
    {
        $raw = Raw::json([]);
        self::assertSame('[]', (string) $raw);
    }

    public function testJsonHandlesNestedData(): void
    {
        $raw = Raw::json(['a' => ['b' => 1]]);
        $decoded = \json_decode((string) $raw, true);
        self::assertSame(['a' => ['b' => 1]], $decoded);
    }

    // ──────────────────────────────────────────────────────────
    // Raw::html()
    // ──────────────────────────────────────────────────────────

    public function testHtmlWrapsStringAsIs(): void
    {
        $raw = Raw::html('<b>bold</b>');
        /** @phpstan-ignore staticMethod.alreadyNarrowedType */
        self::assertInstanceOf(Raw::class, $raw);
        self::assertSame('<b>bold</b>', (string) $raw);
    }

    public function testHtmlEmptyString(): void
    {
        $raw = Raw::html('');
        self::assertSame('', (string) $raw);
    }

    // ──────────────────────────────────────────────────────────
    // Raw::safe()
    // ──────────────────────────────────────────────────────────

    public function testSafeWrapsStringAsIs(): void
    {
        $raw = Raw::safe('already &amp; safe');
        /** @phpstan-ignore staticMethod.alreadyNarrowedType */
        self::assertInstanceOf(Raw::class, $raw);
        self::assertSame('already &amp; safe', (string) $raw);
    }

    // ──────────────────────────────────────────────────────────
    // Raw::decodeJsonArray()
    // ──────────────────────────────────────────────────────────

    public function testDecodeJsonArrayReturnsArray(): void
    {
        $result = Raw::decodeJsonArray('{"x":1,"y":2}');
        self::assertSame(['x' => 1, 'y' => 2], $result);
    }

    public function testDecodeJsonArrayReturnEmptyOnInvalidJson(): void
    {
        $result = Raw::decodeJsonArray('not-json');
        self::assertSame([], $result);
    }

    public function testDecodeJsonArrayReturnEmptyOnJsonScalar(): void
    {
        // json_decode de un número devuelve int, no array
        $result = Raw::decodeJsonArray('42');
        self::assertSame([], $result);
    }

    public function testDecodeJsonArrayReturnEmptyOnJsonNull(): void
    {
        $result = Raw::decodeJsonArray('null');
        self::assertSame([], $result);
    }

    public function testDecodeJsonArrayHandlesNestedArray(): void
    {
        $result = Raw::decodeJsonArray('{"items":[1,2,3]}');
        self::assertSame(['items' => [1, 2, 3]], $result);
    }

    // ──────────────────────────────────────────────────────────
    // Raw::decodeJsonObject()
    // ──────────────────────────────────────────────────────────

    public function testDecodeJsonObjectBehavesLikeDecodeJsonArray(): void
    {
        $json = '{"a":1}';
        self::assertSame(
            Raw::decodeJsonArray($json),
            Raw::decodeJsonObject($json)
        );
    }

    public function testDecodeJsonObjectReturnEmptyOnInvalidJson(): void
    {
        $result = Raw::decodeJsonObject('{bad json}');
        self::assertSame([], $result);
    }
}
