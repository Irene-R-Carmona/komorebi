<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * AvatarOptions define los avatares predefinidos del sistema y los métodos
 * para validar, obtener URL y generar la lista completa.
 *
 * ¿Qué me quieres demostrar?
 * Que isValid() distingue opciones válidas de inválidas, que toUrl() retorna
 * null para 'initials' y una ruta /images/avatars/ para presets, y que
 * toList() genera la estructura correcta con todos los avatares.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Cambios en las rutas de imagen, en la constante DEFAULT, o en la estructura
 * del array que devuelve toList().
 */

namespace Tests\Unit\Domain;

use App\Domain\AvatarOptions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AvatarOptions::class)]
final class AvatarOptionsTest extends TestCase
{
    // ──────────────────────────────────────────────────────────
    // Constants
    // ──────────────────────────────────────────────────────────

    public function testDefaultConstantIsInitials(): void
    {
        /** @phpstan-ignore staticMethod.alreadyNarrowedType */
        self::assertSame('initials', AvatarOptions::DEFAULT);
    }

    public function testOptionsConstantIsNonEmpty(): void
    {
        /** @phpstan-ignore staticMethod.alreadyNarrowedType */
        self::assertNotEmpty(AvatarOptions::OPTIONS);
    }

    public function testOptionsContainsInitials(): void
    {
        self::assertContains('initials', AvatarOptions::OPTIONS);
    }

    // ──────────────────────────────────────────────────────────
    // isValid()
    // ──────────────────────────────────────────────────────────

    public function testIsValidAcceptsAllDefinedOptions(): void
    {
        foreach (AvatarOptions::OPTIONS as $option) {
            self::assertTrue(
                AvatarOptions::isValid($option),
                "Expected '{$option}' to be valid"
            );
        }
    }

    public function testIsValidRejectsUnknownOption(): void
    {
        self::assertFalse(AvatarOptions::isValid('custom_upload'));
    }

    public function testIsValidRejectsEmptyString(): void
    {
        self::assertFalse(AvatarOptions::isValid(''));
    }

    // ──────────────────────────────────────────────────────────
    // toUrl()
    // ──────────────────────────────────────────────────────────

    public function testToUrlReturnsNullForInitials(): void
    {
        self::assertNull(AvatarOptions::toUrl('initials'));
    }

    public function testToUrlReturnsPathForPreset(): void
    {
        $url = AvatarOptions::toUrl('preset_1');
        self::assertNotNull($url);
        self::assertStringContainsString('/images/avatars/', $url);
        self::assertStringContainsString('preset_1', $url);
    }

    public function testToUrlEndsWithSvgExtension(): void
    {
        $url = AvatarOptions::toUrl('preset_2');
        self::assertNotNull($url);
        self::assertStringEndsWith('.svg', $url);
    }

    public function testToUrlDifferentPresets(): void
    {
        $url1 = AvatarOptions::toUrl('preset_1');
        $url2 = AvatarOptions::toUrl('preset_2');
        self::assertNotSame($url1, $url2);
    }

    // ──────────────────────────────────────────────────────────
    // toList()
    // ──────────────────────────────────────────────────────────

    public function testToListReturnsCorrectCount(): void
    {
        $list = AvatarOptions::toList();
        self::assertCount(\count(AvatarOptions::OPTIONS), $list);
    }

    public function testToListEachEntryHasRequiredKeys(): void
    {
        foreach (AvatarOptions::toList() as $entry) {
            self::assertArrayHasKey('id', $entry);
            self::assertArrayHasKey('url', $entry);
            self::assertArrayHasKey('label', $entry);
        }
    }

    public function testToListInitialsEntryHasNullUrl(): void
    {
        $list = AvatarOptions::toList();
        $initials = \array_values(\array_filter($list, fn (array $e) => $e['id'] === 'initials'));
        self::assertCount(1, $initials);
        self::assertNull($initials[0]['url']);
        self::assertSame('Iniciales', $initials[0]['label']);
    }

    public function testToListPresetEntriesHaveNonNullUrl(): void
    {
        foreach (AvatarOptions::toList() as $entry) {
            if ($entry['id'] !== 'initials') {
                self::assertNotNull($entry['url'], "URL should not be null for {$entry['id']}");
            }
        }
    }
}
