<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * CareLogVocabulary centraliza los valores válidos para tipos de actividad
 * y estados de ánimo en los registros de cuidado animal.
 *
 * ¿Qué me quieres demostrar?
 * Que las constantes contienen los valores esperados y que los métodos
 * isValid*() aceptan valores del vocabulario y rechazan valores arbitrarios.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Cualquier cambio en las listas ACTIVITY_TYPES o MOOD_VALUES, o en la
 * lógica de validación.
 */

namespace Tests\Unit\Domain;

use App\Domain\CareLogVocabulary;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CareLogVocabulary::class)]
final class CareLogVocabularyTest extends TestCase
{
    // ──────────────────────────────────────────────────────────
    // ACTIVITY_TYPES
    // ──────────────────────────────────────────────────────────

    public function testActivityTypesConstantIsNonEmpty(): void
    {
        self::assertNotEmpty(CareLogVocabulary::ACTIVITY_TYPES);
    }

    public function testIsValidActivityTypeAcceptsKnownValues(): void
    {
        foreach (CareLogVocabulary::ACTIVITY_TYPES as $type) {
            self::assertTrue(
                CareLogVocabulary::isValidActivityType($type),
                "Expected '{$type}' to be valid"
            );
        }
    }

    public function testIsValidActivityTypeRejectsUnknownValue(): void
    {
        self::assertFalse(CareLogVocabulary::isValidActivityType('napping'));
    }

    public function testIsValidActivityTypeRejectsEmptyString(): void
    {
        self::assertFalse(CareLogVocabulary::isValidActivityType(''));
    }

    public function testActivityTypesContainsFeedingAndGrooming(): void
    {
        self::assertContains('feeding', CareLogVocabulary::ACTIVITY_TYPES);
        self::assertContains('grooming', CareLogVocabulary::ACTIVITY_TYPES);
    }

    // ──────────────────────────────────────────────────────────
    // MOOD_VALUES
    // ──────────────────────────────────────────────────────────

    public function testMoodValuesConstantIsNonEmpty(): void
    {
        self::assertNotEmpty(CareLogVocabulary::MOOD_VALUES);
    }

    public function testIsValidMoodAcceptsKnownValues(): void
    {
        foreach (CareLogVocabulary::MOOD_VALUES as $mood) {
            self::assertTrue(
                CareLogVocabulary::isValidMood($mood),
                "Expected '{$mood}' to be valid"
            );
        }
    }

    public function testIsValidMoodRejectsUnknownValue(): void
    {
        self::assertFalse(CareLogVocabulary::isValidMood('ecstatic'));
    }

    public function testIsValidMoodRejectsEmptyString(): void
    {
        self::assertFalse(CareLogVocabulary::isValidMood(''));
    }

    public function testMoodValuesContainsCalmAndHappy(): void
    {
        self::assertContains('calm', CareLogVocabulary::MOOD_VALUES);
        self::assertContains('happy', CareLogVocabulary::MOOD_VALUES);
    }
}
