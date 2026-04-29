<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * AnimalVocabulary centraliza los valores válidos para ENUM de especies,
 * tipos de incidente y estados de incidente.
 *
 * ¿Qué me quieres demostrar?
 * Que las constantes contienen los valores esperados y que los métodos
 * isValid*() aceptan valores válidos y rechazan inválidos.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Cualquier cambio en las listas de valores o en la lógica de validación.
 */

namespace Tests\Unit\Domain;

use App\Domain\AnimalVocabulary;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AnimalVocabulary::class)]
final class AnimalVocabularyTest extends TestCase
{
    // ──────────────────────────────────────────────────────────
    // SPECIES
    // ──────────────────────────────────────────────────────────

    public function testSpeciesConstantIsNonEmpty(): void
    {
        self::assertNotEmpty(AnimalVocabulary::SPECIES);
    }

    public function testIsValidSpeciesAcceptsKnownValues(): void
    {
        foreach (AnimalVocabulary::SPECIES as $species) {
            self::assertTrue(
                AnimalVocabulary::isValidSpecies($species),
                "Expected '{$species}' to be valid"
            );
        }
    }

    public function testIsValidSpeciesRejectsUnknownValue(): void
    {
        self::assertFalse(AnimalVocabulary::isValidSpecies('dragon'));
    }

    public function testIsValidSpeciesRejectsEmptyString(): void
    {
        self::assertFalse(AnimalVocabulary::isValidSpecies(''));
    }

    public function testSpeciesContainsCatAndDog(): void
    {
        self::assertContains('cat', AnimalVocabulary::SPECIES);
        self::assertContains('dog', AnimalVocabulary::SPECIES);
    }

    // ──────────────────────────────────────────────────────────
    // INCIDENT_TYPES
    // ──────────────────────────────────────────────────────────

    public function testIncidentTypesConstantIsNonEmpty(): void
    {
        self::assertNotEmpty(AnimalVocabulary::INCIDENT_TYPES);
    }

    public function testIsValidIncidentTypeAcceptsKnownValues(): void
    {
        foreach (AnimalVocabulary::INCIDENT_TYPES as $type) {
            self::assertTrue(
                AnimalVocabulary::isValidIncidentType($type),
                "Expected '{$type}' to be valid"
            );
        }
    }

    public function testIsValidIncidentTypeRejectsUnknownValue(): void
    {
        self::assertFalse(AnimalVocabulary::isValidIncidentType('volcano'));
    }

    public function testIncidentTypesContainsBite(): void
    {
        self::assertContains('bite', AnimalVocabulary::INCIDENT_TYPES);
    }

    // ──────────────────────────────────────────────────────────
    // INCIDENT_STATUSES
    // ──────────────────────────────────────────────────────────

    public function testIncidentStatusesConstantIsNonEmpty(): void
    {
        self::assertNotEmpty(AnimalVocabulary::INCIDENT_STATUSES);
    }

    public function testIsValidIncidentStatusAcceptsKnownValues(): void
    {
        foreach (AnimalVocabulary::INCIDENT_STATUSES as $status) {
            self::assertTrue(
                AnimalVocabulary::isValidIncidentStatus($status),
                "Expected '{$status}' to be valid"
            );
        }
    }

    public function testIsValidIncidentStatusRejectsUnknownValue(): void
    {
        self::assertFalse(AnimalVocabulary::isValidIncidentStatus('archived'));
    }

    public function testIncidentStatusesContainsOpenAndResolved(): void
    {
        self::assertContains('open', AnimalVocabulary::INCIDENT_STATUSES);
        self::assertContains('resolved', AnimalVocabulary::INCIDENT_STATUSES);
    }
}
